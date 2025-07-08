<?php

require_once __DIR__ . '/../vendor/autoload.php';

use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\WebDriver;
use TusharKhan\Chatbot\Storage\ArrayStore;

// Simple example for plain PHP usage
$driver = new WebDriver();
$bot = new Bot($driver, new ArrayStore());

// Simple echo bot
$bot->hears('*', function($context) {
    return "You said: " . $context->getMessage();
});

$bot->listen();

// Output the response
foreach ($driver->getResponses() as $response) {
    echo "Bot: " . $response['message'] . "\n";
}
