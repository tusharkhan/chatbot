# TusharKhan Chatbot Package

A framework-agnostic PHP chatbot package that works seamlessly with plain PHP, Laravel, or any custom PHP application. Build powerful chatbots with multi-platform support including Web and Slack.

## 🚀 Features

- **Framework Agnostic**: Works with any PHP framework or plain PHP
- **Multi-Platform Support**: Web and Slack drivers included
- **Pattern Matching**: Flexible message routing with parameters, wildcards, and regex
- **Multi-turn Conversations**: Stateful conversations with context management
- **Storage Options**: File-based or in-memory storage (easily extensible)
- **Middleware Support**: Add custom processing logic
- **Fallback Handling**: Graceful handling of unmatched messages
- **Easy Setup**: No complex configuration required
- **Rich Messaging**: Support for buttons, menus, attachments, and interactive components
- **Modern Slack Features**: Events API, slash commands, and interactive components
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


### Slack Bot

```php
<?php
use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\SlackDriver;

$bot = new Bot(new SlackDriver('BOT_TOKEN', 'SIGNING_SECRET'));

$bot->hears('hello', function($context) {
    return 'Hello from Slack! 👋';
});

// Handle slash commands
$bot->hears('/weather {city}', function($context) {
    $city = $context->getParam('city');
    return "Weather for {$city}: 22°C, Sunny ☀️";
});

// Rich messages with interactive buttons
$bot->hears('menu', function($context) {
    $driver = $context->getDriver();
    $blocks = [
        [
            'type' => 'section',
            'text' => ['type' => 'mrkdwn', 'text' => 'Choose an option:']
        ],
        [
            'type' => 'actions',
            'elements' => [
                [
                    'type' => 'button',
                    'text' => ['type' => 'plain_text', 'text' => 'Option 1'],
                    'action_id' => 'option_1'
                ]
            ]
        ]
    ];
    $driver->sendRichMessage('Menu', $blocks);
    return null; // already sent a rich message
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

## 📄 JSON Conversation Files

Define entire conversation flows in JSON files for easier management and non-technical editing:

### Basic Setup

```php
use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\WebDriver;
use TusharKhan\Chatbot\Storage\FileStore;

$bot = new Bot(new WebDriver(), new FileStore());

// Load conversations from JSON file
$bot->loadConversations('conversations.json');

$bot->listen();
```

### JSON Structure

Create a `conversations.json` file with the following structure:

```json
{
  "conversations": [
    {
      "pattern": "hello",
      "response": "Hi there! How can I help you?"
    },
    {
      "pattern": "my name is {name}",
      "response": {
        "text": "Nice to meet you, {name}! I'll remember your name.",
        "actions": [
          {
            "type": "set",
            "key": "name",
            "value": "{name}"
          }
        ]
      }
    },
    {
      "pattern": "what is my name",
      "response": "Your name is {conversation.name}",
      "conditions": [
        {
          "type": "conversation",
          "key": "name",
          "operator": "exists"
        }
      ]
    }
  ]
}
```

### Advanced Features

#### Random Responses
```json
{
  "pattern": "hello",
  "response": {
    "random": [
      "Hi there!",
      "Hello! Nice to meet you!",
      "Hey! How are you doing?"
    ]
  }
}
```

#### Conditional Responses
```json
{
  "pattern": "check status",
  "response": "You are an adult",
  "conditions": [
    {
      "type": "conversation",
      "key": "age",
      "operator": ">",
      "value": "17"
    }
  ]
}
```

#### Actions (Set Variables)
```json
{
  "pattern": "set age {age}",
  "response": {
    "text": "Age set to {age}",
    "actions": [
      {
        "type": "set",
        "key": "age",
        "value": "{age}"
      }
    ]
  }
}
```

#### Actions (Increment Counters)
```json
{
  "pattern": "increment",
  "response": {
    "text": "Counter incremented!",
    "actions": [
      {
        "type": "increment",
        "key": "counter"
      }
    ]
  }
}
```

### Placeholder Types

#### Parameter Placeholders
- `{name}` - Extracts parameters from the message pattern
- `{age}` - Any parameter defined in the pattern

#### Conversation Placeholders
- `{conversation.name}` - Retrieves stored conversation data
- `{conversation.age}` - Any key stored in the conversation

### Condition Operators

| Operator | Description | Example |
|----------|-------------|---------|
| `=` or `==` | Equal to | `"operator": "=", "value": "admin"` |
| `!=` | Not equal to | `"operator": "!=", "value": "guest"` |
| `>` | Greater than | `"operator": ">", "value": "18"` |
| `<` | Less than | `"operator": "<", "value": "65"` |
| `contains` | Contains substring | `"operator": "contains", "value": "pizza"` |
| `exists` | Value exists and is not empty | `"operator": "exists"` |

### Condition Types

#### Conversation Conditions
Check stored conversation data:
```json
{
  "type": "conversation",
  "key": "user_role",
  "operator": "=",
  "value": "admin"
}
```

#### Parameter Conditions
Check extracted parameters:
```json
{
  "type": "param",
  "key": "product",
  "operator": "contains",
  "value": "premium"
}
```

### Complete Example

```json
{
  "conversations": [
    {
      "pattern": "start shopping",
      "response": {
        "text": "Welcome to our store! What would you like to buy?",
        "actions": [
          {
            "type": "set",
            "key": "shopping",
            "value": "true"
          }
        ]
      }
    },
    {
      "pattern": "buy {product}",
      "response": {
        "text": "Added {product} to your cart! Total items: {conversation.cart_count}",
        "actions": [
          {
            "type": "increment",
            "key": "cart_count"
          },
          {
            "type": "set",
            "key": "last_product",
            "value": "{product}"
          }
        ]
      },
      "conditions": [
        {
          "type": "conversation",
          "key": "shopping",
          "operator": "=",
          "value": "true"
        }
      ]
    },
    {
      "pattern": "checkout",
      "response": "Great! You have {conversation.cart_count} items. Your last item was {conversation.last_product}",
      "conditions": [
        {
          "type": "conversation",
          "key": "cart_count",
          "operator": ">",
          "value": "0"
        }
      ]
    }
  ]
}
```

### Benefits of JSON Conversations

- **Easy Management**: Non-technical team members can edit conversations
- **Version Control**: Track changes in conversation flows
- **Rapid Prototyping**: Quickly test conversation flows without code changes
- **Conditional Logic**: Complex branching based on user state
- **Data Persistence**: Automatic storage and retrieval of conversation data
- **Scalability**: Organize large conversation trees efficiently

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

- `examples/slack.php` - Full Slack bot with events, slash commands, interactivity, and persistence

Additional guides in doc/:
- `doc/web-driver-bot.md` - WebDriver bot guide (plain PHP and Laravel)
- `doc/slack-bot-example.md` - Slack bot setup and real-world example
- `doc/vanilla-php-bot.md` - Vanilla PHP endpoints for WebDriver and Slack

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

## 🌐 Supported Platforms

### Web Driver
- HTTP/AJAX requests
- Form submissions
- Session-based conversations
- JSON/HTML responses

### Slack API
- **Events API**: Real-time message events
- **Slash Commands**: Custom bot commands
- **Interactive Components**: Buttons, menus, and forms
- **Rich Messaging**: Block Kit for rich layouts
- **Mentions & DMs**: App mentions and direct messages
- **Reactions**: Add/remove emoji reactions
- **User Management**: Get user and channel information

Supported Slack features in this package:
- ✅ Message Events (`message`, `app_mention`)
- ✅ Interactive Components (buttons, selects)
- ✅ Slash Commands (`/command`)
- ✅ Rich Text Formatting (Block Kit)
- ✅ Ephemeral Messages (private responses)
- ✅ Reactions and Emoji
- ✅ User and Channel Information
- ✅ Message Updates and Deletions
- ✅ Webhook Signature Verification

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

## 🌟 Star History

If you find this package useful, please consider giving it a star on GitHub!

---

Made with ❤️ by [Tushar Khan](https://github.com/tusharkhan)
