# TusharKhan Chatbot Package

A framework-agnostic PHP chatbot package that works seamlessly with plain PHP, Laravel, CodeIgniter, or any custom PHP application. Build powerful chatbots with multi-platform support including Web, Telegram, and WhatsApp.

## 🚀 Features

- **Framework Agnostic**: Works with any PHP framework or plain PHP
- **Multi-Platform Support**: Web, Telegram, WhatsApp drivers included
- **Pattern Matching**: Flexible message routing with parameters, wildcards, and regex
- **Multi-turn Conversations**: Stateful conversations with context management
- **Storage Options**: File-based or in-memory storage (easily extensible)
- **Middleware Support**: Add custom processing logic
- **Fallback Handling**: Graceful handling of unmatched messages
- **Easy Setup**: No complex configuration required
- **Fully Tested**: Comprehensive unit test coverage

## 📦 Installation

Install via Composer:

```bash
composer require tusharkhan/chatbot
```

## 🎯 Quick Start

### Basic Web Chatbot

```php
<?php
require_once 'vendor/autoload.php';

use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\WebDriver;
use TusharKhan\Chatbot\Storage\FileStore;

// Initialize bot
$driver = new WebDriver();
$storage = new FileStore();
$bot = new Bot($driver, $storage);

// Add message handlers
$bot->hears('hello', function($context) {
    return 'Hello! How can I help you?';
});

$bot->hears('my name is {name}', function($context) {
    $name = $context->getParam('name');
    $context->getConversation()->set('name', $name);
    return "Nice to meet you, $name!";
});

// Listen for messages
$bot->listen();

// Output responses
$driver->outputJson(); // For AJAX
// or $driver->outputHtml(); // For form submissions
?>
```

### Telegram Bot

```php
<?php
use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\TelegramDriver;

$bot = new Bot(new TelegramDriver('YOUR_BOT_TOKEN'));

$bot->hears('/start', function($context) {
    return 'Welcome to my Telegram bot!';
});

$bot->listen();
?>
```

### WhatsApp Business Bot

```php
<?php
use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\WhatsAppDriver;

$bot = new Bot(new WhatsAppDriver('ACCESS_TOKEN', 'PHONE_NUMBER_ID'));

$bot->hears('hello', function($context) {
    return 'Hello from WhatsApp Business!';
});

$bot->listen();
?>
```

## 🔧 Pattern Matching

The bot supports various pattern matching types:

### Exact Match
```php
$bot->hears('hello', $handler);
```

### Wildcards
```php
$bot->hears('hello*', $handler); // Matches "hello world", "hello there", etc.
```

### Parameters
```php
$bot->hears('my name is {name}', function($context) {
    $name = $context->getParam('name');
    return "Hello, $name!";
});
```

### Multiple Patterns
```php
$bot->hears(['hello', 'hi', 'hey'], $handler);
```

### Regular Expressions
```php
$bot->hears('/^\d+$/', $handler); // Matches numbers only
```

### Custom Functions
```php
$bot->hears(function($message) {
    return strpos($message, 'urgent') !== false;
}, $handler);
```

## 💬 Conversation Management

Handle multi-turn conversations with ease:

```php
// Start a conversation flow
$bot->hears('order pizza', function($context) {
    $context->getConversation()->setState('ordering');
    return 'What size pizza? (small/medium/large)';
});

// Handle the next step
$bot->hears(['small', 'medium', 'large'], function($context) {
    $conversation = $context->getConversation();
    
    if ($conversation->isInState('ordering')) {
        $size = $context->getMessage();
        $conversation->set('pizza_size', $size);
        $conversation->setState('toppings');
        return "Great! What toppings for your $size pizza?";
    }
});

// Continue the flow...
$bot->hears('*', function($context) {
    $conversation = $context->getConversation();
    
    if ($conversation->isInState('toppings')) {
        $toppings = $context->getMessage();
        $size = $conversation->get('pizza_size');
        
        $conversation->clear(); // End conversation
        return "Order confirmed: $size pizza with $toppings!";
    }
});
```

## 🔌 Middleware

Add custom processing logic:

```php
// Logging middleware
$bot->middleware(function($context) {
    error_log("Message: " . $context->getMessage());
    return true; // Continue processing
});

// Authentication middleware
$bot->middleware(function($context) {
    $userId = $context->getSenderId();
    if (!isUserAuthenticated($userId)) {
        $context->getDriver()->sendMessage('Please login first');
        return false; // Stop processing
    }
    return true;
});
```

## 🗄️ Storage Options

### File Storage (Persistent)
```php
use TusharKhan\Chatbot\Storage\FileStore;

$storage = new FileStore('/path/to/storage/directory');
$bot = new Bot($driver, $storage);
```

### Array Storage (In-Memory)
```php
use TusharKhan\Chatbot\Storage\ArrayStore;

$storage = new ArrayStore();
$bot = new Bot($driver, $storage);
```

### Custom Storage
Implement the `StorageInterface` for custom storage solutions:

```php
use TusharKhan\Chatbot\Contracts\StorageInterface;

class DatabaseStore implements StorageInterface
{
    // Implement required methods...
}
```

## 🌐 Framework Integration

### Laravel Integration

```php
// In a Laravel Controller
use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\WebDriver;

class ChatbotController extends Controller
{
    public function handle()
    {
        $bot = new Bot(new WebDriver());
        
        $bot->hears('hello', function($context) {
            return 'Hello from Laravel!';
        });
        
        $bot->listen();
        
        return response()->json([
            'responses' => $bot->driver()->getResponses()
        ]);
    }
}
```

### CodeIgniter Integration

```php
// In a CodeIgniter Controller
class Chatbot extends CI_Controller
{
    public function index()
    {
        require_once APPPATH . 'vendor/autoload.php';
        
        use TusharKhan\Chatbot\Core\Bot;
        use TusharKhan\Chatbot\Drivers\WebDriver;
        
        $bot = new Bot(new WebDriver());
        
        $bot->hears('hello', function($context) {
            return 'Hello from CodeIgniter!';
        });
        
        $bot->listen();
        
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'responses' => $bot->driver()->getResponses()
            ]));
    }
}
```

## 🧪 Testing

Run the test suite:

```bash
composer test
```

Run with coverage:

```bash
./vendor/bin/phpunit --coverage-html coverage
```

## 📖 Examples

Check the `examples/` directory for complete working examples:

- `simple_example.php` - Basic usage
- `web_example.php` - Full web chatbot with HTML interface
- `telegram_example.php` - Telegram bot with commands and keyboards
- `whatsapp_example.php` - WhatsApp Business bot

## 🔧 Advanced Usage

### Custom Drivers

Create custom drivers by implementing `DriverInterface`:

```php
use TusharKhan\Chatbot\Contracts\DriverInterface;

class SlackDriver implements DriverInterface
{
    public function getMessage(): ?string { /* ... */ }
    public function getSenderId(): ?string { /* ... */ }
    public function sendMessage(string $message, ?string $senderId = null): bool { /* ... */ }
    public function getData(): array { /* ... */ }
    public function hasMessage(): bool { /* ... */ }
}
```

### Error Handling

```php
$bot->middleware(function($context) {
    try {
        return true;
    } catch (Exception $e) {
        error_log('Chatbot error: ' . $e->getMessage());
        $context->getDriver()->sendMessage('Sorry, something went wrong.');
        return false;
    }
});
```

## 📚 API Reference

### Bot Class

- `hears($pattern, $handler)` - Add message handler
- `fallback($handler)` - Set fallback handler
- `middleware($middleware)` - Add middleware
- `listen()` - Process incoming messages
- `say($message, $senderId)` - Send message
- `conversation()` - Get current conversation
- `driver()` - Get driver instance
- `storage()` - Get storage instance

### Context Class

- `getMessage()` - Get incoming message
- `getSenderId()` - Get sender ID
- `getConversation()` - Get conversation instance
- `getDriver()` - Get driver instance
- `getParams()` - Get extracted parameters
- `getParam($key, $default)` - Get specific parameter

### Conversation Class

- `setState($state)` - Set conversation state
- `getState()` - Get current state
- `isInState($state)` - Check if in specific state
- `set($key, $value)` - Set variable
- `get($key, $default)` - Get variable
- `has($key)` - Check if variable exists
- `remove($key)` - Remove variable
- `clear()` - Clear all data
- `addMessage($type, $message)` - Add to history
- `getHistory()` - Get message history

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙋‍♂️ Support

- Create an [Issue](https://github.com/tusharkhan/chatbot/issues) for bug reports
- Start a [Discussion](https://github.com/tusharkhan/chatbot/discussions) for questions
- Email: contact@tusharkhan.dev

## 🌟 Star History

If you find this package useful, please consider giving it a star on GitHub!

---

Made with ❤️ by [Tushar Khan](https://github.com/tusharkhan)
