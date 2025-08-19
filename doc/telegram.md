# Telegram Bot Implementation Guide

This guide shows how to create Telegram bots using the TelegramDriver with the Telegram Bot API.

## Prerequisites

1. **Create a Telegram Bot**:
   - Message @BotFather on Telegram
   - Use `/newbot` command and follow instructions
   - Get your bot token (format: `123456789:ABCdefGHIjklMNOpqrsTUVwxyz`)

2. **Environment Setup**:
```bash
TELEGRAM_BOT_TOKEN=your-telegram-bot-token-here
```

## Basic Implementation

```php
<?php
require_once 'vendor/autoload.php';

use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\TelegramDriver;
use TusharKhan\Chatbot\Storage\FileStore;

// Configuration
$botToken = $_ENV['TELEGRAM_BOT_TOKEN'];

// Initialize bot
$driver = new TelegramDriver($botToken);
$storage = new FileStore(__DIR__ . '/storage');
$bot = new Bot($driver, $storage);

// Handle /start command
$bot->hears(['/start', 'start'], function($context) {
    return "Welcome! 🤖 Type /help to see available commands.";
});

$bot->listen();
?>
```

## Webhook Setup

### Setting Up Webhook

```php
<?php
// Set webhook URL (run this once)
$botToken = 'YOUR_BOT_TOKEN';
$webhookUrl = 'https://yourserver.com/telegram-webhook.php';

$url = "https://api.telegram.org/bot{$botToken}/setWebhook";
$data = ['url' => $webhookUrl];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$result = curl_exec($ch);
curl_close($ch);

echo $result;
?>
```

### Webhook Endpoint

```php
<?php
// telegram-webhook.php
require_once 'vendor/autoload.php';

use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\TelegramDriver;
use TusharKhan\Chatbot\Storage\FileStore;

try {
    $driver = new TelegramDriver($_ENV['TELEGRAM_BOT_TOKEN']);
    $bot = new Bot($driver, new FileStore(__DIR__ . '/storage'));
    
    // Add your handlers
    $bot->hears('/help', function($context) {
        return "Available commands:\n/start - Start the bot\n/help - Show this message";
    });
    
    $bot->listen();
    
    http_response_code(200);
    echo "OK";
    
} catch (Exception $e) {
    error_log('Telegram webhook error: ' . $e->getMessage());
    http_response_code(200); // Always return 200 to Telegram
    echo "OK";
}
?>
```

## Bot Commands

### Basic Commands

```php
// Start command
$bot->hears(['/start', 'start'], function($context) {
    $user = $context->getData()['from'];
    $name = $user['first_name'] ?? 'User';
    
    return "Hello {$name}! 👋\n\nWelcome to our bot. Type /help for available commands.";
});

// Help command
$bot->hears(['/help', 'help'], function($context) {
    return "🤖 *Available Commands:*\n\n" .
           "/start - Start the bot\n" .
           "/help - Show this help\n" .
           "/weather [city] - Get weather\n" .
           "/joke - Get a random joke\n" .
           "/about - About this bot";
});

// About command
$bot->hears(['/about', 'about'], function($context) {
    return "🤖 *About This Bot*\n\n" .
           "Built with TusharKhan/Chatbot package\n" .
           "Version: 1.0.0\n" .
           "Framework: PHP";
});
```

### Interactive Commands

```php
// Weather command with parameter
$bot->hears('/weather {city}', function($context) {
    $city = $context->getParam('city');
    
    // In real implementation, call weather API
    return "🌤️ *Weather in " . ucfirst($city) . ":*\n\n" .
           "Temperature: 22°C\n" .
           "Condition: Sunny\n" .
           "Humidity: 65%\n" .
           "Wind: 10 km/h";
});

// Calculator
$bot->hears('/calc {expression}', function($context) {
    $expression = $context->getParam('expression');
    
    // Simple calculator (be careful with eval in production!)
    if (preg_match('/^[\d\+\-\*\/\(\)\s]+$/', $expression)) {
        try {
            $result = eval("return {$expression};");
            return "🧮 *Calculator*\n\n{$expression} = {$result}";
        } catch (Exception $e) {
            return "❌ Invalid expression";
        }
    }
    
    return "❌ Only numbers and basic operators allowed";
});
```

## Conversation Flows

### Multi-step Conversations

```php
// Start survey
$bot->hears(['/survey', 'survey'], function($context) {
    $context->getConversation()->setState('survey_name');
    return "📋 *Customer Survey*\n\nFirst, what's your name?";
});

// Collect name
$bot->hears('*', function($context) {
    $conversation = $context->getConversation();
    
    if ($conversation->isInState('survey_name')) {
        $name = $context->getMessage();
        $conversation->set('name', $name);
        $conversation->setState('survey_rating');
        
        return "Nice to meet you, {$name}! 😊\n\n" .
               "On a scale of 1-10, how would you rate our service?";
    }
    
    return null; // Let other handlers process
});

// Collect rating
$bot->hears('/^([1-9]|10)$/', function($context) {
    $conversation = $context->getConversation();
    
    if ($conversation->isInState('survey_rating')) {
        $rating = $context->getMessage();
        $name = $conversation->get('name');
        
        $conversation->set('rating', $rating);
        $conversation->setState('survey_feedback');
        
        return "Thank you, {$name}! You rated us {$rating}/10.\n\n" .
               "Any additional feedback? (or type 'skip')";
    }
    
    return null;
});

// Collect feedback
$bot->hears('*', function($context) {
    $conversation = $context->getConversation();
    
    if ($conversation->isInState('survey_feedback')) {
        $feedback = $context->getMessage();
        $name = $conversation->get('name');
        $rating = $conversation->get('rating');
        
        // Clear conversation
        $conversation->clear();
        
        if ($feedback !== 'skip') {
            $message = "✅ *Survey Complete!*\n\n" .
                      "Thank you, {$name}!\n" .
                      "Rating: {$rating}/10\n" .
                      "Feedback: {$feedback}";
        } else {
            $message = "✅ *Survey Complete!*\n\n" .
                      "Thank you, {$name}!\n" .
                      "Rating: {$rating}/10";
        }
        
        return $message;
    }
    
    return null;
});
```

## Rich Messages and Keyboards

### Inline Keyboards

```php
$bot->hears('/menu', function($context) {
    $driver = $context->getDriver();
    
    // Create inline keyboard
    $keyboard = [
        [
            ['text' => '🍕 Pizza', 'callback_data' => 'order_pizza'],
            ['text' => '🍔 Burger', 'callback_data' => 'order_burger']
        ],
        [
            ['text' => '🥗 Salad', 'callback_data' => 'order_salad'],
            ['text' => '🥤 Drinks', 'callback_data' => 'order_drinks']
        ],
        [
            ['text' => '❌ Cancel', 'callback_data' => 'cancel']
        ]
    ];
    
    $driver->sendMessage(
        "🍽️ *Our Menu*\n\nWhat would you like to order?",
        null,
        ['inline_keyboard' => $keyboard]
    );
    
    return null; // Message already sent
});

// Handle callback queries (button presses)
$bot->hears('callback_query', function($context) {
    $data = $context->getData();
    $callbackData = $data['callback_query']['data'] ?? '';
    
    switch ($callbackData) {
        case 'order_pizza':
            return "🍕 Great choice! Pizza selected.";
        case 'order_burger':
            return "🍔 Excellent! Burger selected.";
        case 'order_salad':
            return "🥗 Healthy choice! Salad selected.";
        case 'order_drinks':
            return "🥤 Drinks selected.";
        case 'cancel':
            return "❌ Order cancelled.";
        default:
            return "Unknown selection.";
    }
});
```

### Reply Keyboards

```php
$bot->hears('/keyboard', function($context) {
    $driver = $context->getDriver();
    
    $keyboard = [
        [
            ['text' => '🏠 Home'],
            ['text' => '⚙️ Settings']
        ],
        [
            ['text' => '📞 Contact'],
            ['text' => '❓ Help']
        ]
    ];
    
    $driver->sendMessage(
        "Choose an option:",
        null,
        [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ]
    );
    
    return null;
});
```

## File Handling

### Sending Files

```php
$bot->hears('/photo', function($context) {
    $driver = $context->getDriver();
    
    // Send photo from URL
    $driver->sendPhoto(
        'https://example.com/image.jpg',
        'Here is your photo! 📸'
    );
    
    return null;
});

$bot->hears('/document', function($context) {
    $driver = $context->getDriver();
    
    // Send document
    $driver->sendDocument(
        '/path/to/document.pdf',
        'Here is your document 📄'
    );
    
    return null;
});
```

### Receiving Files

```php
$bot->hears('photo_received', function($context) {
    $data = $context->getData();
    $photo = $data['message']['photo'] ?? null;
    
    if ($photo) {
        $fileId = end($photo)['file_id']; // Get highest resolution
        
        // Get file info and download URL
        $driver = $context->getDriver();
        $fileInfo = $driver->getFile($fileId);
        
        return "📸 Photo received! File size: " . $fileInfo['file_size'] . " bytes";
    }
    
    return "No photo found.";
});
```

## Error Handling and Security

### Input Validation

```php
$bot->middleware(function($context) {
    $message = $context->getMessage();
    
    // Block spam or inappropriate content
    $blockedWords = ['spam', 'advertisement'];
    foreach ($blockedWords as $word) {
        if (stripos($message, $word) !== false) {
            $context->getDriver()->sendMessage('This type of content is not allowed.');
            return false; // Stop processing
        }
    }
    
    // Rate limiting (simple example)
    $userId = $context->getSenderId();
    $lastMessage = $context->getConversation()->get('last_message_time', 0);
    $currentTime = time();
    
    if ($currentTime - $lastMessage < 1) { // 1 second between messages
        $context->getDriver()->sendMessage('Please wait a moment before sending another message.');
        return false;
    }
    
    $context->getConversation()->set('last_message_time', $currentTime);
    
    return true; // Continue processing
});
```

### Error Logging

```php
$bot->middleware(function($context) {
    try {
        // Log all messages for analytics
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $context->getSenderId(),
            'message' => $context->getMessage(),
            'chat_type' => $context->getData()['message']['chat']['type'] ?? 'unknown'
        ];
        
        error_log('Telegram bot: ' . json_encode($logData));
        
        return true;
        
    } catch (Exception $e) {
        error_log('Telegram bot error: ' . $e->getMessage());
        return false;
    }
});
```

## Deployment

### Production Checklist

- [ ] Set up webhook with HTTPS
- [ ] Configure proper error logging
- [ ] Implement rate limiting
- [ ] Set up monitoring and alerts
- [ ] Test all commands thoroughly
- [ ] Configure file storage permissions
- [ ] Set up backup for conversation data

### Performance Tips

1. **Use webhooks instead of polling** for better performance
2. **Implement proper caching** for frequently accessed data
3. **Use queues for heavy operations** to avoid timeouts
4. **Monitor API rate limits** and implement backoff strategies
5. **Optimize file storage** for media handling

## Testing Your Bot

### Local Testing

```php
// test-bot.php
require_once 'vendor/autoload.php';

// Simulate webhook data for testing
$testData = [
    'message' => [
        'message_id' => 1,
        'from' => [
            'id' => 12345,
            'first_name' => 'Test',
            'username' => 'testuser'
        ],
        'chat' => [
            'id' => 12345,
            'type' => 'private'
        ],
        'date' => time(),
        'text' => '/start'
    ]
];

$driver = new TelegramDriver($_ENV['TELEGRAM_BOT_TOKEN'], $testData);
$bot = new Bot($driver, new ArrayStore());

// Add your handlers
$bot->hears('/start', function($context) {
    return "Test successful! 🎉";
});

$bot->listen();

// Check responses
var_dump($driver->getResponses());
```

This comprehensive guide covers all aspects of Telegram bot development with the TusharKhan/Chatbot package, from basic setup to advanced features and production deployment.
