<?php

require_once __DIR__ . '/../vendor/autoload.php';

use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Core\Context;
use TusharKhan\Chatbot\Drivers\TelegramDriver;
use TusharKhan\Chatbot\Storage\FileStore;

// Configuration - replace with your actual bot token
$botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? 'your-telegram-bot-token-here';

// Initialize the bot with Telegram driver and file storage
$driver = new TelegramDriver($botToken);
$storage = new FileStore(__DIR__ . '/storage');
$bot = new Bot($driver, $storage);

// Add middleware for logging
$bot->middleware(function ($context) {
    error_log("Telegram message: " . $context->getMessage() . " from: " . $context->getSenderId());
    return true;
});

// Handle /start command
$bot->hears(['/start', 'start'], function ($context) {
    $name = $context->getData()['from']['first_name'] ?? 'there';
    return "Welcome to our bot, $name! 🤖\n\nType /help to see what I can do.";
});

// Handle /help command
$bot->hears(['/help', 'help'], function ($context) {
    return [
        "🤖 *Bot Commands:*",
        "",
        "• `/start` - Start the bot",
        "• `/help` - Show this help message",
        "• `my name is [name]` - Tell me your name",
        "• `order` - Start food ordering",
        "• `weather [city]` - Get weather info",
        "• `joke` - Get a random joke",
        "• `/cancel` - Cancel current operation"
    ];
});

// Capture user name
$bot->hears('my name is {name}', function ($context) {
    $name = $context->getParam('name');
    $context->getConversation()->set('user_name', $name);
    return "Nice to meet you, $name! 😊\n\nNow I'll remember your name.";
});

// Start food ordering
$bot->hears(['order', '/order'], function ($context) {
    $context->getConversation()->setState('ordering_category');
    return "🍽️ *Food Ordering*\n\nWhat would you like to order?\n\n• Pizza 🍕\n• Burger 🍔\n• Salad 🥗\n• Drinks 🥤";
});

// Handle food category selection
$bot->hears(['pizza', 'burger', 'salad', 'drinks'], function ($context) {
    $conversation = $context->getConversation();

    if ($conversation->isInState('ordering_category')) {
        $category = ucfirst($context->getMessage());
        $conversation->set('order_category', $category);
        $conversation->setState('ordering_quantity');

        $emoji = ['Pizza' => '🍕', 'Burger' => '🍔', 'Salad' => '🥗', 'Drinks' => '🥤'];

        return "Great choice! {$emoji[$category]} $category\n\nHow many would you like? (Enter a number)";
    }

    return null; // Let other handlers process
});

// Handle quantity input
$bot->hears('/^\d+$/', function ($context) {
    $conversation = $context->getConversation();

    if ($conversation->isInState('ordering_quantity')) {
        $quantity = $context->getMessage();
        $category = $conversation->get('order_category');
        $userName = $conversation->get('user_name', 'Customer');

        $conversation->set('order_quantity', $quantity);
        $conversation->setState('ordering_confirm');

        return "📋 *Order Summary:*\n\n" .
            "Customer: $userName\n" .
            "Item: $category\n" .
            "Quantity: $quantity\n\n" .
            "Type `confirm` to place order or `cancel` to start over.";
    }

    return null; // Let other handlers process
});

// Confirm order
$bot->hears(['confirm', '/confirm'], function ($context) {
    $conversation = $context->getConversation();

    if ($conversation->isInState('ordering_confirm')) {
        $category = $conversation->get('order_category');
        $quantity = $conversation->get('order_quantity');
        $userName = $conversation->get('user_name', 'Customer');

        // Clear order state but keep user name
        $conversation->setState(null);
        $conversation->remove('order_category');
        $conversation->remove('order_quantity');

        return "✅ *Order Confirmed!*\n\n" .
            "Thank you, $userName!\n" .
            "Your order of $quantity $category will be ready in 20-30 minutes.\n\n" .
            "Order ID: #" . rand(1000, 9999);
    }

    return "There's nothing to confirm right now. Type `order` to start ordering!";
});

// Weather command
$bot->hears('weather {city}', function ($context) {
    $city = $context->getParam('city');
    // In a real implementation, you'd call a weather API
    return "🌤️ *Weather in " . ucfirst($city) . ":*\n\n" .
        "Temperature: 22°C\n" .
        "Condition: Partly Cloudy\n" .
        "Humidity: 65%\n" .
        "Wind: 5 km/h\n\n" .
        "_Note: This is a demo response_";
});

// Random joke
$bot->hears(['joke', '/joke'], function ($context) {
    $jokes = [
        "Why don't programmers like nature? It has too many bugs! 🐛",
        "How many programmers does it take to change a light bulb? None, that's a hardware problem! 💡",
        "Why do Java developers wear glasses? Because they can't C#! 👓",
        "A SQL query goes into a bar, walks up to two tables and asks: 'Can I join you?' 🍺",
        "Why did the developer go broke? Because he used up all his cache! 💰"
    ];

    return $jokes[array_rand($jokes)];
});

// Cancel operation
$bot->hears(['/cancel', 'cancel'], function ($context) {
    $conversation = $context->getConversation();
    $state = $conversation->getState();

    if ($state) {
        $conversation->setState(null);
        $conversation->remove('order_category');
        $conversation->remove('order_quantity');
        return "❌ Operation cancelled. How else can I help you?";
    }

    return "Nothing to cancel! Type /help to see what I can do.";
});

// Fallback for unmatched messages
$bot->fallback(function ($context) {
    return "🤔 I didn't understand that.\n\nType `/help` to see available commands!";
});

// Listen for incoming messages
$bot->listen();

// For webhook mode - just return success
if ($driver->hasMessage()) {
    http_response_code(200);
    echo "OK";
} else {
    http_response_code(200);
    echo "No message to process";
}
