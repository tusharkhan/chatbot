<?php

require_once __DIR__ . '/../vendor/autoload.php';

use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\SlackDriver;
use TusharKhan\Chatbot\Storage\FileStore;

/**
 * Real-World Slack Bot Implementation
 *
 * This example shows how to implement a production-ready Slack bot
 * for a customer support or team productivity use case.
 */

// Configuration - replace with your actual values
$botToken = $_ENV['SLACK_BOT_TOKEN'] ?? 'xoxb-your-bot-token-here';
$signingSecret = $_ENV['SLACK_SIGNING_SECRET'] ?? 'your-signing-secret-here';

// Initialize the bot with Slack driver and file storage
$driver = new SlackDriver($botToken, $signingSecret);
$storage = new FileStore(__DIR__ . '/storage');
$bot = new Bot($driver, $storage);

// Add middleware for logging
$bot->middleware(function($context) {
    error_log("Slack message: " . $context->getMessage() . " from: " . $context->getSenderId());
    return true;
});

// Main webhook endpoint for your Slack app
Route::post('/slack/webhook', function (\Illuminate\Http\Request $request) use ($bot) {
    try {
        // Get webhook data
        $webhookData = $request->all();
        $driver->setWebhookData($webhookData);

        // Process the message
        $bot->listen();

        return response()->json(['status' => 'ok']);

    } catch (\Exception $e) {
        Log::error('Slack webhook error:', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json(['error' => 'Internal server error'], 500);
    }
});

// Test endpoint for development
Route::post('/slack/test-command', function (\Illuminate\Http\Request $request) {
    $command = $request->input('command', '/help');

    echo "Testing Slack command: {$command}\n\n";

    // Simulate different command types
    if (strpos($command, '/ticket create') === 0) {
        echo "✅ Would create a support ticket\n";
        echo "Command format: /ticket create [description]\n";
        echo "Example: /ticket create My computer won't start\n";
    } elseif (strpos($command, '/schedule') === 0) {
        echo "📅 Would schedule a meeting\n";
        echo "Command format: /schedule [title] at [time]\n";
        echo "Example: /schedule Team standup at tomorrow 9am\n";
    } else {
        echo "ℹ️  Use /help to see all available commands\n";
    }

    return response()->json(['test' => 'completed']);
});

// Simple greeting
$bot->hears(['hello', 'hi', 'hey'], function($context) {
    return "Hello! 👋 How can I help you today?";
});

// Handle mentions
$bot->hears('help', function($context) {
    return [
        "Here's what I can do:",
        "• Say `hello` to greet me",
        "• Tell me your name: `my name is [your name]`",
        "• Start a survey: `survey`",
        "• Get help: `help`"
    ];
});

// Capture name
$bot->hears('my name is {name}', function($context) {
    $name = $context->getParam('name');
    $context->getConversation()->set('name', $name);
    return "Nice to meet you, $name! 😊";
});

// Start survey
$bot->hears('survey', function($context) {
    $context->getConversation()->setState('survey_rating');
    return "Let's start a quick survey! On a scale of 1-5, how satisfied are you with our service?";
});

// Handle survey rating
$bot->hears('/^[1-5]$/', function($context) {
    $conversation = $context->getConversation();

    if ($conversation->isInState('survey_rating')) {
        $rating = $context->getMessage();
        $conversation->set('rating', $rating);
        $conversation->setState('survey_feedback');
        return "Thanks for rating us $rating/5! Any additional feedback?";
    }

    return null; // Let other handlers process
});

// Handle survey feedback
$bot->hears('*', function($context) {
    $conversation = $context->getConversation();

    if ($conversation->isInState('survey_feedback')) {
        $feedback = $context->getMessage();
        $rating = $conversation->get('rating');
        $name = $conversation->get('name', 'Anonymous');

        // Clear survey state
        $conversation->setState(null);
        $conversation->remove('rating');

        return "Thank you, $name! Your rating ($rating/5) and feedback have been recorded: \"$feedback\"";
    }

    return null; // Let other handlers process
});

// Fallback for unmatched messages
$bot->fallback(function($context) {
    return "I didn't understand that. Type `help` to see what I can do! 🤖";
});

// Send response
if ($driver->hasMessage()) {
    // Response is automatically sent by the SlackDriver
    http_response_code(200);
    echo "OK";
} else {
    http_response_code(200);
    echo "No message to process";
}

