# Message Processing Flow

This document describes the complete message processing flow in the Chatbot package, from receiving a message to sending a response.

## Overview

The message processing flow follows a clear pipeline that ensures proper routing, context management, and response generation. The flow is designed to be predictable, extensible, and maintainable.

## Flow Diagram

```
[Driver] → [Bot] → [Matcher] → [Handler] → [Response] → [Driver]
    ↓         ↓         ↓          ↓           ↓         ↓
 Receive   Process   Match     Execute    Generate   Send
 Message   Input    Pattern   Callback   Response   Output
```

## Detailed Flow

### 1. Message Reception
- **Entry Point**: Driver receives incoming message
- **Driver Types**: Web, Telegram, WhatsApp, or custom
- **Data Extraction**: Driver extracts message content and metadata
- **Context Creation**: Driver creates a Context object with message data

```php
// Example: WebDriver receiving a message
$context = new Context([
    'message' => $_POST['message'] ?? '',
    'user_id' => session_id(),
    'driver' => 'web'
]);
```

### 2. Bot Processing
- **Initialization**: Bot receives the context from driver
- **Storage Access**: Bot retrieves conversation state from storage
- **Conversation Loading**: Existing conversation loaded or new one created

```php
// Bot processes the incoming context
public function processMessage(Context $context): string
{
    $userId = $context->get('user_id');
    $message = $context->get('message');
    
    // Load or create conversation
    $conversation = $this->loadOrCreateConversation($userId);
    
    // Continue processing...
}
```

### 3. Message Matching
- **Pattern Matching**: Matcher evaluates message against registered patterns
- **Priority Handling**: Patterns checked in registration order
- **Parameter Extraction**: Parameters extracted from matched patterns
- **Fallback**: Default handler used if no patterns match

```php
// Matcher finds appropriate handler
$match = $this->matcher->match($message);
if ($match) {
    $handler = $match['handler'];
    $params = $match['params'];
} else {
    $handler = $this->fallbackHandler;
    $params = [];
}
```

### 4. Handler Execution
- **Context Preparation**: Context updated with conversation and parameters
- **Callback Execution**: Registered handler callback is invoked
- **Response Generation**: Handler generates appropriate response
- **State Management**: Conversation state updated if needed

```php
// Handler execution with full context
$context->set('conversation', $conversation);
$context->set('params', $params);

$response = call_user_func($handler, $context);
```

### 5. Response Processing
- **Response Validation**: Ensure response is valid string
- **Context Updates**: Save any context changes
- **Conversation Persistence**: Save conversation state to storage
- **Response Formatting**: Format response for specific driver

### 6. Message Delivery
- **Driver-Specific Formatting**: Format response for target platform
- **Delivery**: Send response through appropriate channel
- **Error Handling**: Handle delivery failures gracefully

## Pattern Matching Details

### Exact Matching
```php
$bot->hear('hello', function($context) {
    return 'Hello there!';
});
```
- Message "hello" matches exactly
- Case-insensitive by default
- No parameter extraction

### Wildcard Matching
```php
$bot->hear('my name is *', function($context) {
    $name = $context->get('params')[0];
    return "Nice to meet you, {$name}!";
});
```
- `*` matches any sequence of characters
- Captured content available in params array
- Multiple wildcards supported

### Regex Matching
```php
$bot->hear('/^order (\d+) (.+)$/', function($context) {
    $quantity = $context->get('params')[0];
    $item = $context->get('params')[1];
    return "Ordering {$quantity} {$item}";
});
```
- Full regex power available
- Capture groups become parameters
- Must start and end with `/`

## Context Flow

### Context Creation
```php
$context = new Context([
    'message' => $message,
    'user_id' => $userId,
    'driver' => $driverName
]);
```

### Context Enhancement
```php
// Bot adds conversation
$context->set('conversation', $conversation);

// Matcher adds parameters
$context->set('params', $extractedParams);

// Handler can add custom data
$context->set('custom_data', $someValue);
```

### Context Usage
```php
function orderHandler($context) {
    $message = $context->get('message');
    $params = $context->get('params');
    $conversation = $context->get('conversation');
    
    // Process and return response
    return $response;
}
```

## Conversation Management

### State Persistence
- Conversations automatically saved after each message
- State includes conversation data and metadata
- Storage layer handles persistence details

### Multi-turn Support
```php
// First turn
$conversation->remember('step', 'awaiting_name');

// Next turn
$step = $conversation->get('step');
if ($step === 'awaiting_name') {
    $conversation->remember('name', $message);
    $conversation->remember('step', 'awaiting_email');
    return 'What\'s your email?';
}
```

## Error Handling

### Pattern Matching Errors
- Invalid regex patterns logged and skipped
- Fallback handler ensures response is always generated
- Graceful degradation maintains user experience

### Handler Errors
- Exceptions caught and logged
- Fallback response sent to user
- Conversation state preserved

### Storage Errors
- Failed saves logged but don't block response
- Fallback to temporary state if needed
- User experience remains smooth

## Performance Considerations

### Pattern Optimization
- Simple patterns checked before complex regex
- Early termination when match found
- Efficient parameter extraction

### Storage Efficiency
- Lazy loading of conversation data
- Minimal data persistence
- Configurable storage backends

### Memory Management
- Context objects cleaned up after processing
- Conversation data cached appropriately
- Resource-conscious design

## Extension Points

### Custom Matchers
```php
class CustomMatcher extends Matcher {
    protected function matchPattern($pattern, $message) {
        // Custom matching logic
        return parent::matchPattern($pattern, $message);
    }
}
```

### Custom Handlers
```php
$bot->hear('custom', new CustomHandler());
```

### Middleware Support
```php
// Conceptual - could be added
$bot->middleware(function($context, $next) {
    // Pre-processing
    $response = $next($context);
    // Post-processing
    return $response;
});
```

This flow ensures that every message is processed consistently while providing flexibility for custom behavior at each step.
