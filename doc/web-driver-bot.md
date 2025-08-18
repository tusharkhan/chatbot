# Web Driver Bot Guide

This guide explains how to build a web-based chatbot using the WebDriver included in this package. The WebDriver lets you connect a simple web UI (form or AJAX) to the chatbot core with zero external dependencies.

## What You Will Build

- A minimal HTTP endpoint that accepts user messages
- Bot handlers with parameters, wildcards, and regex
- Persistent conversation state using FileStore
- JSON or HTML responses for easy integration with frontends
- Optional CLI usage for quick testing

## Prerequisites

- PHP 8.0+
- Composer
- A simple web server (Laravel, plain PHP with built-in server, etc.)

## Quick Start (Plain PHP)

Create a file like `public/chatbot.php`:

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\WebDriver;
use TusharKhan\Chatbot\Storage\FileStore;

$driver = new WebDriver();
$storage = new FileStore(__DIR__ . '/../storage/chatbot');
$bot = new Bot($driver, $storage);

// Handlers
$bot->hears('hello', function($context) {
    return 'Hello! How can I help you?';
});

$bot->hears('my name is {name}', function($context) {
    $name = $context->getParam('name');
    $context->getConversation()->set('name', $name);
    return "Nice to meet you, {$name}!";
});

$bot->hears('what is my name', function($context) {
    $name = $context->getConversation()->get('name', 'unknown');
    return "Your name is {$name}.";
});

// Fallback (optional)
$bot->fallback(function($context) {
    return "Sorry, I didn't understand that. Try 'hello'.";
});

// Process
$bot->listen();

// Output
// If using fetch/AJAX, return JSON:
$driver->outputJson();

// For form submissions or server-rendered pages, you can use:
// $driver->outputHtml();
```

## Using JSON Conversation Files

You can define flows in JSON and load them:

```php
$bot->loadConversations(__DIR__ . '/../conversations.json');
$bot->listen();
$driver->outputJson();
```

See README’s “JSON Conversation Files” for full structure and examples.

## Laravel Example (Controller or Route)

```php
use Illuminate\Support\Facades\Route;
use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\WebDriver;
use TusharKhan\Chatbot\Storage\FileStore;

Route::match(['GET', 'POST'], '/webbot', function() {
    $driver = new WebDriver();
    $storage = new FileStore(storage_path('app/chatbot'));
    $bot = new Bot($driver, $storage);

    $bot->hears('ping', fn($c) => 'pong');

    $bot->listen();

    return response()->json([
        'responses' => $driver->getResponses(),
        'status' => 'success',
    ]);
});
```

## Message Flow and Context

- WebDriver reads incoming data from JSON POST body, form fields, or query string:
  - message: user message
  - sender_id or user_id: optional; if not provided, WebDriver will create a session-based ID
- Bot::listen() orchestrates:
  - Extract message and sender id from driver
  - Match with registered patterns using Matcher
  - Build Context (message, senderId, conversation, driver)
  - Invoke handler and capture returned string (if any)
  - Send response back via driver->sendMessage
  - Persist conversation state with storage

Useful Context methods within handlers:
- getMessage(): string
- getSenderId(): string
- getConversation(): Conversation (set/get/clear state)
- getDriver(): WebDriver instance
- getParam(key): extracted pattern params (e.g., {name})

## WebDriver API Highlights

From `src/Drivers/WebDriver.php`:
- __construct(): auto-loads request or CLI args
- getMessage(): ?string
- getSenderId(): ?string
- sendMessage(string $message, ?string $senderId = null): bool
- getData(): array // all request data
- hasMessage(): bool
- getResponses(): array // for returning to client
- outputJson(): void // JSON response helper
- outputHtml(): void // simple HTML response helper
- clearResponses(): void

### Supported Inputs

- JSON POST: `{ "message": "hi", "sender_id": "user-1" }`
- Form fields: `message`, `sender_id` (or `user_id`)
- Query string: `?message=hi&sender_id=user-1`
- Session: if no sender_id is provided, a session-based id is generated

### CLI Mode

For quick testing without HTTP:

```bash
php public/chatbot.php "hello there" user123
```

- `$argv[1]` = message
- `$argv[2]` = senderId (optional). If omitted, WebDriver generates `cli_<pid>`.

## Testing Tips

- You can unit test your handlers by instantiating WebDriver and calling sendMessage, then asserting `getResponses()`.
- See `tests/Drivers/WebDriverTest.php` for examples:
  - Ensuring messages are collected
  - Clearing responses between tests

## Common Pitfalls

- Not returning your bot responses: Make sure your handler returns a string or sends messages via the driver.
- Missing sender_id: For persistent conversation per user, supply a stable sender_id or rely on sessions.
- Mixed content types: If using AJAX, set Content-Type to `application/json` to let WebDriver parse JSON body.

## Next Steps

- Add more complex flows via JSON conversation files
- Introduce middleware for logging and authentication
- Swap storage to ArrayStore for ephemeral sessions or implement your own StorageInterface
