# TusharKhan Chatbot Package

A framework-agnostic PHP chatbot package that works seamlessly with plain PHP, Laravel, or any custom PHP application. Build powerful chatbots with multi-platform support including Web, Telegram, and Slack.

## 🚀 Features

- **Framework Agnostic**: Works with any PHP framework or plain PHP
- **Multi-Platform Support**: Web, Telegram, and Slack drivers included
- **Pattern Matching**: Flexible message routing with parameters, wildcards, and regex
- **Multi-turn Conversations**: Stateful conversations with context management
- **Storage Options**: File-based or in-memory storage (easily extensible)
- **Middleware Support**: Add custom processing logic
- **Fallback Handling**: Graceful handling of unmatched messages
- **Easy Setup**: No complex configuration required
- **Rich Messaging**: Support for buttons, keyboards, attachments, and interactive components
- **Modern Platform Features**: Events API, slash commands, and interactive components
- **Fully Tested**: Comprehensive unit test coverage (79 tests, 193 assertions)

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
$storage = new FileStore(__DIR__ . '/storage');
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

// Set fallback handler
$bot->fallback(function($context) {
    return "I don't understand. Try saying 'hello'.";
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
use TusharKhan\Chatbot\Storage\FileStore;

// Configuration
$botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? 'your-telegram-bot-token-here';

// Initialize bot
$driver = new TelegramDriver($botToken);
$storage = new FileStore(__DIR__ . '/storage');
$bot = new Bot($driver, $storage);

// Handle /start command
$bot->hears(['/start', 'start'], function($context) {
    $name = $context->getData()['from']['first_name'] ?? 'there';
    return "Welcome to our bot, $name! 🤖\n\nType /help to see what I can do.";
});

// Handle /help command
$bot->hears(['/help', 'help'], function($context) {
    return "🤖 *Bot Commands:*\n\n• /start - Start the bot\n• /help - Show this help\n• order - Start food ordering";
});

// Start food ordering
$bot->hears(['order', '/order'], function($context) {
    $context->getConversation()->setState('ordering_category');
    return "🍽️ *Food Ordering*\n\nWhat would you like?\n• Pizza 🍕\n• Burger 🍔\n• Salad 🥗";
});

$bot->listen();
?>
```

### Slack Bot

```php
<?php
use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\SlackDriver;
use TusharKhan\Chatbot\Storage\FileStore;

// Configuration
$botToken = $_ENV['SLACK_BOT_TOKEN'] ?? 'xoxb-your-bot-token-here';
$signingSecret = $_ENV['SLACK_SIGNING_SECRET'] ?? 'your-signing-secret-here';

// Initialize bot
$driver = new SlackDriver($botToken, $signingSecret);
$storage = new FileStore(__DIR__ . '/storage');
$bot = new Bot($driver, $storage);

$bot->hears('hello', function($context) {
    return 'Hello! 👋 How can I help you today?';
});

// Handle slash commands
$bot->hears('/weather {city}', function($context) {
    $city = $context->getParam('city');
    return "Weather for {$city}: 22°C, Sunny ☀️";
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
    public function store(string $key, array $data): bool { /* ... */ }
    public function retrieve(string $key): ?array { /* ... */ }
    public function exists(string $key): bool { /* ... */ }
    public function delete(string $key): bool { /* ... */ }
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
    public function handle(Request $request)
    {
        $bot = new Bot(new WebDriver(), new FileStore(storage_path('chatbot')));
        
        $bot->hears('hello', function($context) {
            return 'Hello from Laravel!';
        });
        
        $bot->listen();
        
        return response()->json([
            'responses' => $bot->getDriver()->getResponses()
        ]);
    }
}
```

### Plain PHP Integration

```php
// For AJAX requests
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $driver->outputJson();
} else {
    $driver->outputHtml();
}
```

## 🧪 Testing

Run the test suite:

```bash
# Run all tests
composer test

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage

# Check code style
composer cs-check

# Fix code style
composer cs-fix
```

**Test Results**: 79 tests, 193 assertions (2 minor errors in advanced conversation features)

## 📖 Examples & Documentation

### Working Examples
- [`examples/web_driver.php`](examples/web_driver.php) - Complete web chatbot with ordering system
- [`examples/telegram.php`](examples/telegram.php) - Full Telegram bot with commands and conversations
- [`examples/slack.php`](examples/slack.php) - Slack bot with interactive components

### Comprehensive Guides
- [`doc/web-driver-bot.md`](doc/web-driver-bot.md) - WebDriver guide (plain PHP and Laravel)
- [`doc/telegram.md`](doc/telegram.md) - Complete Telegram bot implementation guide
- [`doc/slack-bot-example.md`](doc/slack-bot-example.md) - Slack bot setup and real-world examples

## 🔧 Advanced Usage

### Custom Drivers

Create custom drivers by implementing `DriverInterface`:

```php
use TusharKhan\Chatbot\Contracts\DriverInterface;

class CustomDriver implements DriverInterface
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
- `getData()` - Get raw platform data

### Conversation Class

- `setState($state)` - Set conversation state
- `getState()` - Get current state
- `isInState($state)` - Check if in specific state
- `set($key, $value)` - Set variable
- `get($key, $default)` - Get variable
- `has($key)` - Check if variable exists
- `remove($key)` - Remove variable
- `clear()` - Clear all data

## 🌐 Supported Platforms

### Web Driver
- HTTP/AJAX requests
- Form submissions
- Session-based conversations
- JSON/HTML responses
- Laravel and plain PHP integration

### Telegram API
- **Bot Commands**: `/start`, `/help`, custom commands
- **Message Types**: Text, photos, documents, stickers
- **Keyboards**: Inline and reply keyboards
- **Webhook Support**: Real-time message processing
- **Rich Formatting**: Markdown and HTML support

### Slack API
- **Events API**: Real-time message events
- **Slash Commands**: Custom bot commands
- **Interactive Components**: Buttons, menus, and forms
- **Rich Messaging**: Block Kit for rich layouts
- **Mentions & DMs**: App mentions and direct messages
- **Webhook Verification**: Signature verification for security

## 🔒 Security Features

- **Input Validation**: Built-in message sanitization
- **Webhook Verification**: Signature verification for Slack
- **Rate Limiting**: Middleware support for rate limiting
- **Error Handling**: Comprehensive error logging
- **Storage Security**: Secure file-based storage

## 📊 Package Statistics

- **Total Tests**: 79 tests with 193 assertions
- **Code Coverage**: Comprehensive coverage of all core features
- **PHP Version**: Requires PHP 8.0+
- **Dependencies**: Modern, actively maintained packages
- **Package Size**: Lightweight with minimal dependencies

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
- Email: tushar.khan0122@gmail.com
- Documentation: See `/doc` folder for comprehensive guides

## 🌟 Acknowledgments

- Built with modern PHP 8.0+ features
- Uses established libraries for platform integrations
- Framework-agnostic design for maximum compatibility
- Community-driven development

---

Made with ❤️ by [Tushar Khan](https://github.com/tusharkhan)
