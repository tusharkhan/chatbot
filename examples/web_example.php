<?php

require_once __DIR__ . '/../vendor/autoload.php';

use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\WebDriver;
use TusharKhan\Chatbot\Storage\FileStore;

// Initialize the bot with Web driver and file storage
$driver = new WebDriver();
$storage = new FileStore(__DIR__ . '/storage');
$bot = new Bot($driver, $storage);

// Add middleware for logging
$bot->middleware(function($context) {
    error_log("Received message: " . $context->getMessage() . " from: " . $context->getSenderId());
    return true; // Continue processing
});

// Simple greeting
$bot->hears(['hello', 'hi', 'hey'], function($context) {
    return "Hello! How can I help you today?";
});

// Capture name
$bot->hears('my name is {name}', function($context) {
    $name = $context->getParam('name');
    $context->getConversation()->set('name', $name);
    return "Nice to meet you, $name! What would you like to do?";
});

// Start ordering process
$bot->hears(['order', 'i want to order'], function($context) {
    $context->getConversation()->setState('ordering');
    return "Great! What would you like to order? We have pizza, burger, and salad.";
});

// Handle ordering state
$bot->hears(['pizza', 'burger', 'salad'], function($context) {
    $conversation = $context->getConversation();
    
    if ($conversation->isInState('ordering')) {
        $item = $context->getMessage();
        $conversation->set('order_item', $item);
        $conversation->setState('quantity');
        return "How many $item would you like?";
    }
    
    return "You need to start an order first. Say 'order' to begin.";
});

// Handle quantity
$bot->hears('/^\d+$/', function($context) {
    $conversation = $context->getConversation();
    
    if ($conversation->isInState('quantity')) {
        $quantity = $context->getMessage();
        $item = $conversation->get('order_item');
        $name = $conversation->get('name', 'there');
        
        $conversation->set('quantity', $quantity);
        $conversation->setState('confirm');
        
        return [
            "Perfect, $name!",
            "You want $quantity $item.",
            "Type 'confirm' to place the order or 'cancel' to start over."
        ];
    }
    
    return null; // Let other handlers process numbers
});

// Confirm order
$bot->hears('confirm', function($context) {
    $conversation = $context->getConversation();
    
    if ($conversation->isInState('confirm')) {
        $item = $conversation->get('order_item');
        $quantity = $conversation->get('quantity');
        $name = $conversation->get('name', 'Customer');
        
        // Clear the order state
        $conversation->setState(null);
        $conversation->remove('order_item');
        $conversation->remove('quantity');
        
        return "Order confirmed! $name, your $quantity $item will be ready in 15 minutes.";
    }
    
    return "There's nothing to confirm right now.";
});

// Cancel order
$bot->hears('cancel', function($context) {
    $conversation = $context->getConversation();
    $conversation->clear();
    return "Order cancelled. How else can I help you?";
});

// Help command
$bot->hears(['help', 'what can you do'], function($context) {
    return [
        "Here's what I can help you with:",
        "• Say 'hello' to greet me",
        "• Tell me your name: 'my name is [your name]'",
        "• Start ordering: 'order' or 'i want to order'",
        "• Get help: 'help'",
        "• Cancel current process: 'cancel'"
    ];
});

// Fallback for unmatched messages
$bot->fallback(function($context) {
    return "I didn't understand that. Type 'help' to see what I can do!";
});

// Listen for incoming messages
$bot->listen();

// Output responses as JSON (for AJAX requests)
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    $driver->outputJson();
} else {
    // Output responses as HTML (for form submissions)
    $driver->outputHtml();
}
