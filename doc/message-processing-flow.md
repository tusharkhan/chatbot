# Message Processing Flow

This document describes the complete message processing flow in the Chatbot package, from receiving a message to sending a response, aligned with the current codebase.

## Overview

The message processing flow follows a clear pipeline that ensures proper routing, context management, and response generation. The flow is designed to be predictable, extensible, and maintainable.

## Flow Diagram

```
[Driver] → [Bot::listen] → [Matcher] → [Handler] → [Bot -> Driver::sendMessage] → [Output]
  Receive       Orchestrate     Match        Execute            Send via driver        Return
```

## Detailed Flow

### 1. Message Reception (Driver)
- Entry Point: A Driver reads the inbound request/input
  - WebDriver: reads JSON body, form data, or query string; supports CLI args
  - SlackDriver: parses php://input or provided array for Events, Slash Commands, and Interactivity
- Data Extraction: Driver exposes message and metadata via DriverInterface methods
  - getMessage(): ?string
  - getSenderId(): ?string
  - getData(): array
  - hasMessage(): bool

### 2. Bot Orchestration
- Initialization: Application creates `new Bot($driver, $storage)` and registers handlers via `$bot->hears()`
- Processing: `Bot::listen()` pulls the message from the driver, builds a Context, and coordinates matching and response
- Storage Access: Bot retrieves and persists conversation state via StorageInterface (e.g., FileStore)

```php
$bot->hears('hello', function($context) {
    return 'Hello there!';
});

$bot->listen();
```

### 3. Message Matching (Matcher)
- Pattern Matching: `Matcher->match($message, $pattern)` supports strings, arrays, callables, and regex
- Parameter Extraction: `Matcher->extractParams($message, $pattern)` fills context params for patterns with `{placeholders}` or regex groups
- Priority Handling: Patterns are checked in the order registered
- Fallback: If nothing matches, optional `$bot->fallback()` handler is used

```php
if ($this->matcher->match($message, $pattern)) {
    $params = $this->matcher->extractParams($message, $pattern);
    // call handler with context
}
```

### 4. Handler Execution
- Context provides:
  - getMessage(), getSenderId(), getDriver(), getConversation()
  - getParams(), getParam($key, $default = null)
- Handler returns either:
  - a string (preferred) → Bot sends with `driver->sendMessage()`
  - or null after directly using driver methods (e.g., Slack rich messages)
- Conversation state can be updated via `getConversation()->set/get/clear()`

```php
$bot->hears('my name is {name}', function($context) {
    $name = $context->getParam('name');
    $context->getConversation()->set('name', $name);
    return "Nice to meet you, {$name}!";
});
```

### 5. Response Processing & Persistence
- If a non-empty string is returned by the handler, Bot calls `$driver->sendMessage($response, $senderId)`
- Bot persists the updated conversation state via `StorageInterface` (e.g., `FileStore::setConversation($userId, $data)`)

### 6. Output Delivery
- WebDriver:
  - Responses can be retrieved via `$driver->getResponses()` and output using `$driver->outputJson()` or `$driver->outputHtml()`
- SlackDriver:
  - Sends via Slack Web API helpers (e.g., `sendMessage`, `sendRichMessage`, `sendEphemeralMessage`); HTTP handler should respond 200 OK promptly

## Pattern Matching Details (Aligned with Matcher)

### Exact Matching
```php
$bot->hears('hello', function($context) {
    return 'Hello there!';
});
```

### Wildcards
```php
$bot->hears('hello*', function($context) {
    return 'Hi!';
});
```

### Parameters
```php
$bot->hears('my name is {name}', function($context) {
    return 'Nice to meet you, ' . $context->getParam('name');
});
```

### Regular Expressions
```php
$bot->hears('/^order\s+(\d+)\s+(.+)$/', function($context) {
    [$qty, $item] = $context->getParams(); // or getParam(0), getParam(1) if implemented
    return "Ordering {$qty} {$item}";
});
```

### Multiple Patterns
```php
$bot->hears(['hello', 'hi', 'hey'], function($context) {
    return 'Hello!';
});
```

### Custom Callable Pattern
```php
$bot->hears(function($message) {
    return stripos($message, 'urgent') !== false;
}, function($context) {
    return 'I detected an urgent message!';
});
```

## Context Flow (Conceptual)

- Bot builds a Context object with message, senderId, driver, storage-backed conversation, and extracted params
- Handlers use Context getters to read data and update conversation state

```php
$bot->fallback(function($context) {
    $msg = trim($context->getMessage() ?? '');
    if ($msg === '') return null; // ignore empty
    return "Sorry, I didn't understand that.";
});
```

## Conversation Management

- Conversation state is per-sender (senderId) and persisted via StorageInterface
- Typical operations: set(key, value), get(key, default), clear(), setState(), isInState()

```php
$bot->hears('order pizza', function($context) {
    $context->getConversation()->setState('ordering');
    return 'What size pizza? (small/medium/large)';
});
```

## Error Handling

- Use middleware to add logging and error guarding
```php
$bot->middleware(function($context) {
    // log incoming
    return true; // continue
});
```
- Handlers can return null to skip sending
- Slack handlers should keep HTTP responses fast (200 OK), and send messages via driver helpers

## Performance Considerations

- Keep pattern lists concise; order from most specific to least specific
- Prefer parameter and wildcard patterns over complex regex when possible
- Use FileStore for simple persistence; swap to faster stores for production if needed

## Notes on Drivers

- WebDriver Inputs: JSON body, form fields, or query string; session-based sender ID if missing
- SlackDriver Inputs: Events API, Slash Commands, Interactivity payloads; optional signature verification; reactions/mentions normalized for matching

This flow mirrors the current Bot, Matcher, and driver behavior for accurate implementation and troubleshooting.
