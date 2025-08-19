# Slack Bot Setup and Implementation Guide

This guide shows how to create production-ready Slack bots using the SlackDriver.

## Prerequisites

1. **Slack App Setup**:
   - Create a new Slack app at https://api.slack.com/apps
   - Enable Events API and set your webhook URL
   - Add bot token scopes: `app_mentions:read`, `chat:write`, `im:read`, `im:write`
   - Install the app to your workspace

2. **Environment Variables**:
```bash
SLACK_BOT_TOKEN=xoxb-your-bot-token-here
SLACK_SIGNING_SECRET=your-signing-secret-here
```

## Basic Implementation

```php
<?php
require_once 'vendor/autoload.php';

use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\SlackDriver;
use TusharKhan\Chatbot\Storage\FileStore;

// Configuration
$botToken = $_ENV['SLACK_BOT_TOKEN'];
$signingSecret = $_ENV['SLACK_SIGNING_SECRET'];

// Initialize bot
$driver = new SlackDriver($botToken, $signingSecret);
$storage = new FileStore(__DIR__ . '/storage');
$bot = new Bot($driver, $storage);

// Basic message handling
$bot->hears('hello', function($context) {
    return 'Hello! 👋 How can I help you?';
});

$bot->listen();
?>
```

## Webhook Endpoint Setup

### Laravel Route Example

```php
// routes/api.php
Route::post('/slack/webhook', function (Request $request) {
    $botToken = env('SLACK_BOT_TOKEN');
    $signingSecret = env('SLACK_SIGNING_SECRET');
    
    $driver = new SlackDriver($botToken, $signingSecret);
    $storage = new FileStore(storage_path('chatbot'));
    $bot = new Bot($driver, $storage);
    
    // Add your message handlers
    $bot->hears('help', function($context) {
        return 'Available commands: help, status, about';
    });
    
    $bot->listen();
    
    return response('OK', 200);
});
```

### Plain PHP Webhook

```php
<?php
// webhook.php
require_once 'vendor/autoload.php';

use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\SlackDriver;
use TusharKhan\Chatbot\Storage\FileStore;

try {
    $driver = new SlackDriver(
        $_ENV['SLACK_BOT_TOKEN'],
        $_ENV['SLACK_SIGNING_SECRET']
    );
    
    $bot = new Bot($driver, new FileStore(__DIR__ . '/storage'));
    
    // Your message handlers here
    $bot->hears('ping', function($context) {
        return 'pong! 🏓';
    });
    
    $bot->listen();
    
    http_response_code(200);
    echo 'OK';
    
} catch (Exception $e) {
    error_log('Slack webhook error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Error';
}
?>
```

## Advanced Features

### Slash Commands

```php
// Handle slash commands
$bot->hears('/weather {city}', function($context) {
    $city = $context->getParam('city');
    
    // Call weather API (example)
    $weather = getWeatherData($city);
    
    return "🌤️ Weather in {$city}: {$weather['temp']}°C, {$weather['condition']}";
});

$bot->hears('/ticket create {description}', function($context) {
    $description = $context->getParam('description');
    $userId = $context->getSenderId();
    
    // Create ticket in your system
    $ticketId = createSupportTicket($userId, $description);
    
    return "✅ Support ticket #{$ticketId} created successfully!";
});
```

### Interactive Components

```php
// Send messages with buttons
$bot->hears('menu', function($context) {
    $driver = $context->getDriver();
    
    $blocks = [
        [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => 'Choose an option:'
            ]
        ],
        [
            'type' => 'actions',
            'elements' => [
                [
                    'type' => 'button',
                    'text' => ['type' => 'plain_text', 'text' => 'Option 1'],
                    'action_id' => 'option_1',
                    'value' => 'option_1'
                ],
                [
                    'type' => 'button',
                    'text' => ['type' => 'plain_text', 'text' => 'Option 2'],
                    'action_id' => 'option_2',
                    'value' => 'option_2'
                ]
            ]
        ]
    ];
    
    $driver->sendRichMessage('Choose your option', $blocks);
    return null; // Message already sent
});

// Handle button clicks
$bot->hears('button_clicked', function($context) {
    $data = $context->getData();
    $actionId = $data['actions'][0]['action_id'] ?? null;
    
    switch ($actionId) {
        case 'option_1':
            return 'You selected Option 1! ✅';
        case 'option_2':
            return 'You selected Option 2! ✅';
        default:
            return 'Unknown option selected.';
    }
});
```

### Rich Message Formatting

```php
// Using Slack Block Kit
$bot->hears('report', function($context) {
    $driver = $context->getDriver();
    
    $blocks = [
        [
            'type' => 'header',
            'text' => [
                'type' => 'plain_text',
                'text' => '📊 Daily Report'
            ]
        ],
        [
            'type' => 'section',
            'fields' => [
                [
                    'type' => 'mrkdwn',
                    'text' => '*Sales:* $12,340'
                ],
                [
                    'type' => 'mrkdwn',
                    'text' => '*Orders:* 45'
                ],
                [
                    'type' => 'mrkdwn',
                    'text' => '*Customers:* 32'
                ],
                [
                    'type' => 'mrkdwn',
                    'text' => '*Growth:* +15%'
                ]
            ]
        ],
        [
            'type' => 'divider'
        ],
        [
            'type' => 'context',
            'elements' => [
                [
                    'type' => 'mrkdwn',
                    'text' => 'Generated at ' . date('Y-m-d H:i:s')
                ]
            ]
        ]
    ];
    
    $driver->sendRichMessage(null, $blocks);
    return null;
});
```

### User Management

```php
// Get user information
$bot->hears('who am i', function($context) {
    $driver = $context->getDriver();
    $userId = $context->getSenderId();
    
    try {
        $userInfo = $driver->getUserInfo($userId);
        $name = $userInfo['user']['real_name'] ?? 'Unknown';
        $email = $userInfo['user']['profile']['email'] ?? 'No email';
        
        return "👋 You are *{$name}* ({$email})";
    } catch (Exception $e) {
        return "Sorry, I couldn't fetch your information.";
    }
});
```

### Error Handling and Logging

```php
// Add comprehensive error handling
$bot->middleware(function($context) {
    try {
        $message = $context->getMessage();
        $userId = $context->getSenderId();
        
        // Log all interactions
        error_log("Slack message from {$userId}: {$message}");
        
        return true; // Continue processing
        
    } catch (Exception $e) {
        error_log("Slack middleware error: " . $e->getMessage());
        $context->getDriver()->sendMessage('Sorry, something went wrong. Please try again.');
        return false; // Stop processing
    }
});
```

## Real-World Use Cases

### 1. Support Ticket System

```php
$bot->hears('/support {issue}', function($context) {
    $issue = $context->getParam('issue');
    $userId = $context->getSenderId();
    
    // Create ticket
    $ticketId = createTicket($userId, $issue);
    
    // Send confirmation
    $blocks = [
        [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => "✅ *Support Ticket Created*\n\n*Ticket ID:* #{$ticketId}\n*Issue:* {$issue}\n\nOur team will respond within 2 hours."
            ]
        ],
        [
            'type' => 'actions',
            'elements' => [
                [
                    'type' => 'button',
                    'text' => ['type' => 'plain_text', 'text' => 'View Ticket'],
                    'url' => "https://support.yourcompany.com/ticket/{$ticketId}"
                ]
            ]
        ]
    ];
    
    $context->getDriver()->sendRichMessage(null, $blocks);
    return null;
});
```

### 2. Team Productivity Bot

```php
$bot->hears('/standup', function($context) {
    $conversation = $context->getConversation();
    $conversation->setState('standup_yesterday');
    
    return "📅 *Daily Standup*\n\nWhat did you work on yesterday?";
});

$bot->hears('*', function($context) {
    $conversation = $context->getConversation();
    $state = $conversation->getState();
    
    if ($state === 'standup_yesterday') {
        $conversation->set('yesterday', $context->getMessage());
        $conversation->setState('standup_today');
        return "Great! What are you working on today?";
    }
    
    if ($state === 'standup_today') {
        $conversation->set('today', $context->getMessage());
        $conversation->setState('standup_blockers');
        return "Awesome! Any blockers or issues?";
    }
    
    if ($state === 'standup_blockers') {
        $blockers = $context->getMessage();
        $yesterday = $conversation->get('yesterday');
        $today = $conversation->get('today');
        
        $conversation->clear();
        
        // Post to standup channel
        $standupSummary = "📋 *Standup Summary*\n\n" .
                         "*Yesterday:* {$yesterday}\n" .
                         "*Today:* {$today}\n" .
                         "*Blockers:* {$blockers}";
        
        return "✅ Standup recorded! Summary posted to #standup channel.";
    }
    
    return null;
});
```

## Deployment Checklist

- [ ] Set up proper environment variables
- [ ] Configure webhook URL in Slack app settings
- [ ] Test webhook signature verification
- [ ] Set up error logging and monitoring
- [ ] Configure rate limiting
- [ ] Test all interactive components
- [ ] Set up proper storage permissions
- [ ] Configure SSL for webhook endpoint

## Security Best Practices

1. **Always verify webhook signatures**
2. **Use HTTPS for webhook endpoints**
3. **Validate all user inputs**
4. **Implement rate limiting**
5. **Log security events**
6. **Rotate tokens regularly**
7. **Use environment variables for secrets**

## Troubleshooting

### Common Issues

1. **Webhook not receiving events**: Check URL configuration and SSL certificate
2. **Signature verification fails**: Ensure signing secret is correct
3. **Bot not responding**: Check token permissions and scopes
4. **Rate limiting**: Implement proper delays between API calls

### Debug Mode

```php
// Enable debug logging
$bot->middleware(function($context) {
    $data = $context->getData();
    error_log('Slack event data: ' . json_encode($data, JSON_PRETTY_PRINT));
    return true;
});
```
