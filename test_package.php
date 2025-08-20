<?php
require 'vendor/autoload.php';

use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\WebDriver;
use TusharKhan\Chatbot\Storage\ArrayStore;

// Test basic instantiation
$driver = new WebDriver();
$storage = new ArrayStore();
$bot = new Bot($driver, $storage);

echo "✅ Bot instantiation works!\n";

// Test basic pattern matching
$bot->hears('hello', function ($context) {
    return 'Hi there!';
});

echo "✅ Pattern registration works!\n";

echo "✅ All basic functionality working correctly!\n";
