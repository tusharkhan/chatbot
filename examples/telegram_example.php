<?php

require_once __DIR__ . '/../vendor/autoload.php';

use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\TelegramDriver;
use TusharKhan\Chatbot\Storage\FileStore;

// Replace with your actual bot token
$botToken = 'YOUR_BOT_TOKEN_HERE';

// Initialize the bot
$driver = new TelegramDriver($botToken);
$storage = new FileStore(__DIR__ . '/storage');
$bot = new Bot($driver, $storage);

// Welcome message
$bot->hears(['/start', '/help'], function($context) {
    return [
        "🤖 Welcome to the Telegram Chatbot!",
        "",
        "Available commands:",
        "/help - Show this help message",
        "/menu - Show the main menu",
        "/weather - Get weather info",
        "/joke - Get a random joke",
        "/echo [text] - Echo your text back"
    ];
});

// Main menu
$bot->hears('/menu', function($context) {
    $keyboard = [
        ['🌤️ Weather', '😂 Joke'],
        ['💬 Echo', '📊 Stats'],
        ['❓ Help']
    ];
    
    $driver = $context->getDriver();
    if (method_exists($driver, 'sendKeyboard')) {
        $driver->sendKeyboard('Choose an option:', $keyboard);
        return null; // Don't send additional message
    }
    
    return "Choose an option:\n🌤️ Weather\n😂 Joke\n💬 Echo\n📊 Stats\n❓ Help";
});

// Handle keyboard responses
$bot->hears(['🌤️ Weather', '🌤 Weather', 'weather'], function($context) {
    $conversation = $context->getConversation();
    $conversation->setState('getting_weather');
    return "🌍 Please send me your city name to get the weather forecast:";
});

$bot->hears('😂 Joke', function($context) {
    $jokes = [
        "Why don't scientists trust atoms? Because they make up everything! 😄",
        "Why did the scarecrow win an award? He was outstanding in his field! 🌾",
        "Why don't eggs tell jokes? They'd crack each other up! 🥚",
        "What do you call a fake noodle? An impasta! 🍝",
        "Why did the math book look so sad? Because it had too many problems! 📚"
    ];
    
    return $jokes[array_rand($jokes)];
});

// Weather handling
$bot->hears('*', function($context) {
    $conversation = $context->getConversation();
    
    if ($conversation->isInState('getting_weather')) {
        $city = $context->getMessage();
        $conversation->setState(null);
        
        // In a real application, you would call a weather API here
        return [
            "🌤️ Weather for $city:",
            "Temperature: 22°C",
            "Condition: Partly cloudy",
            "Humidity: 65%",
            "Wind: 10 km/h",
            "",
            "Note: This is a demo response. In a real bot, you would integrate with a weather API."
        ];
    }
    
    return null; // Let other handlers process the message
});

// Echo command
$bot->hears('/echo {text}', function($context) {
    $text = $context->getParam('text');
    return "📢 You said: $text";
});

$bot->hears('💬 Echo', function($context) {
    $context->getConversation()->setState('echo_mode');
    return "💬 Echo mode activated! Send me any message and I'll echo it back. Send /cancel to stop.";
});

// Handle echo mode
$bot->hears('*', function($context) {
    $conversation = $context->getConversation();
    
    if ($conversation->isInState('echo_mode')) {
        $message = $context->getMessage();
        
        if ($message === '/cancel') {
            $conversation->setState(null);
            return "Echo mode cancelled. 👍";
        }
        
        return "📢 Echo: $message";
    }
    
    return null;
});

// Stats
$bot->hears(['📊 Stats', '/stats'], function($context) {
    $conversation = $context->getConversation();
    $history = $conversation->getHistory();
    $messageCount = count($history);
    $userName = $context->getDriver()->getUserInfo()['first_name'] ?? 'User';
    
    return [
        "📊 Your Stats:",
        "👤 Name: $userName",
        "💬 Messages sent: $messageCount",
        "🕐 Last active: " . date('Y-m-d H:i:s')
    ];
});

// Handle photos and other media
$bot->hears('*', function($context) {
    $data = $context->getDriver()->getData();
    
    if (isset($data['message']['photo'])) {
        return "📷 Nice photo! I can see you sent me an image.";
    }
    
    if (isset($data['message']['document'])) {
        return "📄 Thanks for the document!";
    }
    
    if (isset($data['message']['voice'])) {
        return "🎤 I received your voice message, but I can't process audio yet.";
    }
    
    return null;
});

// Fallback for unmatched messages
$bot->fallback(function($context) {
    return [
        "🤔 I didn't understand that command.",
        "Type /help to see available commands or /menu for quick options."
    ];
});

// Listen for incoming messages
$bot->listen();

// Note: To set up the webhook, create a separate file (webhook_setup.php) and run it once:
/*
<?php
require_once __DIR__ . '/../vendor/autoload.php';
use TusharKhan\Chatbot\Drivers\TelegramDriver;

$botToken = 'YOUR_BOT_TOKEN_HERE';
$webhookUrl = 'https://yourdomain.com/path/to/telegram_example.php';

$driver = new TelegramDriver($botToken);
$result = $driver->setWebhook($webhookUrl);

echo $result ? 'Webhook set successfully!' : 'Failed to set webhook.';
?>
*/
