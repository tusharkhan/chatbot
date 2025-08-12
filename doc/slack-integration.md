# Slack Integration Documentation

## Overview

The Slack integration for the TusharKhan Chatbot framework provides comprehensive support for Slack bots using the latest Slack API features. This implementation follows the same driver pattern as the existing Telegram and WhatsApp integrations.

## Features Implemented

### Core Functionality
- ✅ **Events API Integration** - Real-time event processing
- ✅ **Socket Mode Support** - For development and secure connections
- ✅ **Webhook Verification** - Signature validation for security
- ✅ **Message Processing** - Text, attachments, files, and rich media
- ✅ **Interactive Components** - Buttons, dropdowns, and form handling
- ✅ **Slash Commands** - Custom command support
- ✅ **Block Kit Messaging** - Rich, interactive message layouts
- ✅ **File Upload/Download** - Comprehensive file handling
- ✅ **User/Channel Management** - User info and channel operations
- ✅ **Error Handling** - Robust error management and logging

### Advanced Features
- ✅ **Bot User Filtering** - Prevents infinite loops from bot messages
- ✅ **Thread Support** - Message threading capabilities
- ✅ **Emoji Reactions** - React to messages with emojis
- ✅ **Rich Attachments** - Support for complex message attachments
- ✅ **Event Type Filtering** - Process only relevant events
- ✅ **Rate Limiting** - Built-in Slack API rate limit handling

## Installation

### 1. Install Dependencies

```bash
composer require jolicode/slack-php-api symfony/http-client nyholm/psr7
```

### 2. Configure Your Slack App

1. Go to [Slack API Console](https://api.slack.com/apps)
2. Create a new app or use existing one
3. Configure OAuth & Permissions:
   - `chat:write` - Send messages
   - `files:read` - Read file info
   - `files:write` - Upload files
   - `users:read` - Get user information
   - `channels:read` - Get channel information
   - `im:read` - Direct message access
   - `groups:read` - Private channel access

4. Enable Events API:
   - Set Request URL: `https://yourdomain.com/slack_webhook.php`
   - Subscribe to events:
     - `message.channels`
     - `message.groups`
     - `message.im`
     - `message.mpim`
     - `app_mention`

5. Configure Interactive Components:
   - Request URL: `https://yourdomain.com/slack_webhook.php`

6. Configure Slash Commands (optional):
   - Command: `/yourcommand`
   - Request URL: `https://yourdomain.com/slack_webhook.php`

### 3. Environment Setup

Create a `.env` file or set environment variables:

```bash
SLACK_BOT_TOKEN=xoxb-your-bot-token
SLACK_SIGNING_SECRET=your-signing-secret
SLACK_APP_TOKEN=xapp-your-app-token  # For Socket Mode
```

## Usage

### Basic Bot Setup

```php
<?php
require_once 'vendor/autoload.php';

use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\SlackDriver;
use TusharKhan\Chatbot\Storage\FileStore;

// Initialize driver
$driver = new SlackDriver(
    botToken: $_ENV['SLACK_BOT_TOKEN'],
    signingSecret: $_ENV['SLACK_SIGNING_SECRET']
);

// Initialize bot
$bot = new Bot($driver, new FileStore());

// Define conversation flow
$bot->hears('hello', function($bot) {
    $bot->reply('Hi there! How can I help you?');
});

// Start listening
$bot->listen();
```

### Advanced Message Types

```php
// Rich message with blocks
$bot->hears('features', function($bot) {
    $blocks = [
        [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => '*Available Features:*'
            ]
        ],
        [
            'type' => 'actions',
            'elements' => [
                [
                    'type' => 'button',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => 'Learn More'
                    ],
                    'action_id' => 'learn_more'
                ]
            ]
        ]
    ];
    
    $bot->reply('', ['blocks' => $blocks]);
});

// Handle button interactions
$bot->on('interactive_component', function($bot) {
    $payload = $bot->getMessage();
    
    if ($payload['type'] === 'block_actions') {
        $action = $payload['actions'][0];
        
        if ($action['action_id'] === 'learn_more') {
            $bot->reply('Here\'s more information about our features...');
        }
    }
});
```

### File Handling

```php
// Handle file uploads
$bot->on('file_shared', function($bot) {
    $message = $bot->getMessage();
    $fileId = $message['file_id'];
    
    // Download file
    $fileContent = $bot->getDriver()->downloadFile($fileId);
    
    // Process file...
    $bot->reply('File received and processed!');
});

// Upload a file
$bot->hears('send file', function($bot) {
    $bot->getDriver()->uploadFile([
        'channels' => $bot->getMessage()['channel'],
        'file' => '/path/to/file.pdf',
        'title' => 'Important Document',
        'initial_comment' => 'Here\'s the document you requested.'
    ]);
});
```

### Slash Commands

```php
// Handle slash commands
$bot->on('slash_command', function($bot) {
    $command = $bot->getMessage();
    
    switch ($command['command']) {
        case '/help':
            $bot->reply('Available commands: /help, /status');
            break;
            
        case '/status':
            $bot->reply('Bot is running smoothly! 🚀');
            break;
    }
});
```

## Webhook Setup

Use the provided `slack_webhook.php` file as your webhook endpoint. This file includes:

- Complete bot initialization
- Event handling for all message types
- Interactive component processing
- Error handling and logging
- Security features (signature verification)

## Testing

Run the comprehensive test suite:

```bash
# Run only Slack tests
./vendor/bin/phpunit tests/Drivers/SlackDriverTest.php

# Run with coverage
./vendor/bin/phpunit tests/Drivers/SlackDriverTest.php --coverage-html coverage
```

## Security Features

- **Webhook Signature Verification** - Validates requests from Slack
- **Token Validation** - Ensures proper authentication
- **Rate Limiting** - Respects Slack API limits
- **Error Sanitization** - Prevents sensitive data leakage
- **Bot Loop Prevention** - Ignores messages from bots

## Troubleshooting

### Common Issues

1. **Webhook URL Verification Failed**
   - Ensure your webhook URL is publicly accessible
   - Check that SSL certificate is valid
   - Verify signing secret is correct

2. **Messages Not Being Received**
   - Check Event Subscriptions in Slack App settings
   - Verify bot token has required permissions
   - Check webhook endpoint logs

3. **Interactive Components Not Working**
   - Ensure Interactive Components URL matches webhook URL
   - Check payload parsing in webhook handler
   - Verify button/action IDs are unique

### Debug Mode

Enable debug logging in your webhook:

```php
$driver = new SlackDriver(
    botToken: $_ENV['SLACK_BOT_TOKEN'],
    signingSecret: $_ENV['SLACK_SIGNING_SECRET'],
    debug: true // Enable debug logging
);
```

## Performance Considerations

- Use appropriate event filtering to reduce unnecessary processing
- Implement caching for user/channel information
- Consider using Slack's Socket Mode for high-traffic bots
- Monitor API rate limits and implement backoff strategies

## Support

For issues specific to this integration:
1. Check the test suite for examples
2. Review Slack API documentation
3. Enable debug logging for detailed error information
4. Check webhook endpoint logs for request/response details

This integration provides a robust foundation for building sophisticated Slack bots while maintaining compatibility with the existing chatbot framework architecture.
