# Web Driver Bot Guide

This guide shows how to create web-based chatbots using the WebDriver for HTTP/AJAX applications.

## Basic Setup

```php
<?php
require_once 'vendor/autoload.php';

use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\WebDriver;
use TusharKhan\Chatbot\Storage\FileStore;

// Initialize bot
$driver = new WebDriver();
$storage = new FileStore(__DIR__ . '/storage');
$bot = new Bot($driver, $storage);

// Add message handlers
$bot->hears('hello', function($context) {
    return 'Hello! How can I help you?';
});

// Listen for messages
$bot->listen();

// Output responses
$driver->outputJson(); // For AJAX
// or $driver->outputHtml(); // For form submissions
?>
```

## Plain PHP Integration

### HTML Form Example

```html
<!DOCTYPE html>
<html>
<head>
    <title>Chatbot Example</title>
</head>
<body>
    <div id="chat-container">
        <div id="messages"></div>
        <form id="chat-form">
            <input type="text" id="message-input" placeholder="Type your message...">
            <button type="submit">Send</button>
        </form>
    </div>

    <script>
        document.getElementById('chat-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const message = document.getElementById('message-input').value;
            if (!message.trim()) return;
            
            // Add user message to chat
            addMessage('user', message);
            
            // Send to bot
            fetch('bot.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'message=' + encodeURIComponent(message)
            })
            .then(response => response.json())
            .then(data => {
                if (data.responses) {
                    data.responses.forEach(response => {
                        addMessage('bot', response);
                    });
                }
            });
            
            document.getElementById('message-input').value = '';
        });
        
        function addMessage(sender, message) {
            const messagesDiv = document.getElementById('messages');
            const messageDiv = document.createElement('div');
            messageDiv.className = sender;
            messageDiv.textContent = message;
            messagesDiv.appendChild(messageDiv);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }
    </script>
</body>
</html>
```

## Laravel Integration

### Controller Implementation

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\WebDriver;
use TusharKhan\Chatbot\Storage\FileStore;

class ChatbotController extends Controller
{
    public function handle(Request $request)
    {
        $driver = new WebDriver();
        $storage = new FileStore(storage_path('chatbot'));
        $bot = new Bot($driver, $storage);
        
        // Add your message handlers here
        $bot->hears('hello', function($context) {
            return 'Hello from Laravel!';
        });
        
        $bot->listen();
        
        return response()->json([
            'responses' => $driver->getResponses()
        ]);
    }
}
```

### Routes

```php
// routes/web.php
Route::post('/chatbot', [ChatbotController::class, 'handle']);
```

## Advanced Features

### Session Management

The WebDriver automatically handles session management for conversation persistence:

```php
// Sessions are automatically managed
$bot->hears('remember {info}', function($context) {
    $info = $context->getParam('info');
    $context->getConversation()->set('remembered_info', $info);
    return "I'll remember: $info";
});

$bot->hears('what do you remember', function($context) {
    $info = $context->getConversation()->get('remembered_info');
    return $info ? "I remember: $info" : "I don't remember anything yet.";
});
```

### Error Handling

```php
$bot->middleware(function($context) {
    try {
        return true; // Continue processing
    } catch (Exception $e) {
        error_log('Chatbot error: ' . $e->getMessage());
        $context->getDriver()->sendMessage('Sorry, something went wrong.');
        return false; // Stop processing
    }
});
```

### Custom Response Formatting

```php
// Return multiple messages
$bot->hears('menu', function($context) {
    return [
        "Here's our menu:",
        "1. Pizza - $12",
        "2. Burger - $8",
        "3. Salad - $6"
    ];
});

// Return structured data for frontend
$bot->hears('status', function($context) {
    $driver = $context->getDriver();
    $driver->addResponse([
        'type' => 'status',
        'data' => [
            'online' => true,
            'users' => 42,
            'uptime' => '2 days'
        ]
    ]);
    return null;
});
```

## Deployment Tips

1. **Storage Directory**: Ensure the storage directory is writable
2. **Error Logging**: Configure proper error logging for production
3. **Security**: Validate and sanitize all inputs
4. **Performance**: Consider using Redis or database storage for high-traffic applications

## Example Use Cases

- Customer support chat widgets
- Interactive forms and surveys
- Game bots for web games
- Educational chatbots
- E-commerce assistance bots
