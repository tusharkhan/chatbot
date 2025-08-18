# Slack Bot Example Guide

This guide walks you through building a production-ready Slack bot using the SlackDriver. It covers Slack App setup, routing events to your app, handling slash commands and interactive buttons, and persisting conversation data.

A complete, real-world example is available at:
- `examples/slack.php` 

## Features Covered

- Events API handling (messages, mentions, reactions)
- Slash commands (e.g., `/ticket create ...`)
- Interactive components (Block Kit buttons)
- Rich messages with sections, fields, and actions
- Ephemeral messages, message updates/deletes, and reactions
- User/channel info helpers
- Conversation persistence with FileStore
- Webhook signature verification (Signing Secret)

## Prerequisites

- PHP 8.0+
- Composer
- A publicly accessible HTTPS URL for Slack to send requests (use ngrok in development)
- Slack Workspace with permission to create Slack Apps

## Slack App Setup

1. Create a Slack App at https://api.slack.com/apps
2. Add a Bot User and install the app to your workspace to get the Bot User OAuth Token (starts with `xoxb-`)
3. Set up features:
   - OAuth & Permissions:
     - Add required Bot Token Scopes (you can start with): `chat:write`, `channels:read`, `users:read`, `reactions:write`, `commands`
     - Install (or Reinstall) the app to workspace; copy the Bot User OAuth Token
   - Event Subscriptions:
     - Enable Events
     - Request URL: `https://<your-domain>/slack/webhook`
     - Subscribe to Bot Events: `message.channels`, `message.groups`, `message.im`, `app_mention`, `reaction_added` (as needed)
   - Interactivity & Shortcuts:
     - Enable Interactivity, set Request URL: `https://<your-domain>/slack/webhook`
   - Slash Commands:
     - Create commands like `/echo`, Request URL: `https://<your-domain>/slack/webhook`
   - Basic Information → App Credentials:
     - Copy Signing Secret

4. Set environment variables in your app:
   - `SLACK_BOT_TOKEN=xoxb-...`
   - `SLACK_SIGNING_SECRET=...`

## Vanilla PHP (No Framework) Webhook

For a minimal setup without any framework, create `public/slack.php` as your single endpoint:

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\SlackDriver;
use TusharKhan\Chatbot\Storage\FileStore;

$botToken      = getenv('SLACK_BOT_TOKEN') ?: 'xoxb-your-bot-token';
$signingSecret = getenv('SLACK_SIGNING_SECRET') ?: 'your-signing-secret';

$driver  = new SlackDriver($botToken, $signingSecret); // auto-parses php://input & verifies challenge
$storage = new FileStore(__DIR__ . '/../storage/chatbot');
$bot     = new Bot($driver, $storage);

$bot->hears('hello', fn($c) => 'Hello from Slack! 👋');
$bot->hears('/echo {text}', fn($c) => 'You said: ' . $c->getParam('text'));

$bot->listen();

http_response_code(200);
echo 'OK';
```

Run locally with:

```bash
php -S 127.0.0.1:8000 -t public
```

Then point your Slack App request URL(s) to your domain (or an ngrok HTTPS URL) ending with `/slack.php`.

## Laravel Route Example

The example uses a single route for all Slack traffic (events, commands, interactivity):

```php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use TusharKhan\Chatbot\Drivers\SlackDriver;
use TusharKhan\Chatbot\Storage\FileStore;
use TusharKhan\Chatbot\Core\Bot;

Route::post('/slack/webhook', function (\Illuminate\Http\Request $request) {
    $botToken = env('SLACK_BOT_TOKEN');
    $signingSecret = env('SLACK_SIGNING_SECRET');

    $webhookData = $request->all();

    $driver = new SlackDriver($botToken, $signingSecret, $webhookData);
    $storage = new FileStore(storage_path('app/chatbot'));
    $bot = new Bot($driver, $storage);

    // Register handlers (see sections below)
    $bot->hears('/help', function($context) {
            $blocks = [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*🤖 Bot Commands Help*\n\nHere are all available commands:"
                    ]
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Support Tickets:*\n• `/ticket create [description]`\n• `/ticket list`"
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Team Productivity:*\n• `/standup [status]`\n• `/schedule [title] at [time]`"
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Communication:*\n• `/announce [message]`\n• `@botname hello`"
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*General:*\n• `/help` - Show this help\n• `/status` - Bot status"
                        ]
                    ]
                ]
            ];

            $context->getDriver()->sendRichMessage("Help Center", $blocks);
            return null;
        });

    $bot->listen();

    // Respond 200 OK to Slack quickly
    return response('OK', 200);
});
```

The SlackDriver automatically:
- Verifies URL challenge for Event Subscriptions
- Parses Events, Slash Commands, and Interactivity payloads
- Optionally verifies signatures using the signing secret

## Core Patterns and Helpers

- Pattern matching with parameters: `$bot->hears('/ticket create {description}', $handler)`
- Multiple routes and wildcards supported (see README Matching section)
- Context methods:
  - `$context->getMessage()`, `$context->getSenderId()`, `$context->getDriver()`
  - `$context->getConversation()->get/set/clear()`
  - `$context->getParam('description')` for `{description}`
- SlackDriver helpers:
  - `sendMessage($text, $senderId?)`
  - `sendRichMessage($text, array $blocks, $channelId = null)`
  - `sendEphemeralMessage($text, $userId, $channelId = null)`
  - `updateMessage($ts, $text, $channelId = null)`
  - `deleteMessage($ts, $channelId = null)`
  - `addReaction($emoji, $ts, $channelId = null)`
  - `getUserInfo($userId): ?array`
  - `getChannelInfo($channelId): ?array`
  - `isDirectMessage()`, `isMention()`, `isSlashCommand()`, `hasMessage()`

## Real-World Ticket Bot (Key Excerpts)

The example implements a support workflow. Below are condensed pieces; see `examples/slack.php` for the full version.

### Create Ticket

```php
$bot->hears('/ticket create {description}', function($context) {
    $description = $context->getParam('description');
    $userId = $context->getDriver()->getSenderId();

    $ticketId = 'TICK-' . strtoupper(substr(md5($userId . time()), 0, 6));

    $tickets = $context->getConversation()->get('tickets', []);
    $tickets[$ticketId] = [
        'id' => $ticketId,
        'description' => $description,
        'status' => 'open',
        'created_at' => date('Y-m-d H:i:s'),
        'assigned_to' => null
    ];
    $context->getConversation()->set('tickets', $tickets);

    $userInfo = $context->getDriver()->getUserInfo($userId);
    $userName = $userInfo['real_name'] ?? $userInfo['name'] ?? 'User';

    $blocks = [
        [
            'type' => 'section',
            'text' => ['type' => 'mrkdwn', 'text' => "🎫 *Ticket Created Successfully*\n\nHi {$userName}! Your support ticket has been created."]
        ],
        [
            'type' => 'section',
            'fields' => [
                ['type' => 'mrkdwn', 'text' => "*Ticket ID:*\n{$ticketId}"],
                ['type' => 'mrkdwn', 'text' => "*Status:*\n🟡 Open"],
                ['type' => 'mrkdwn', 'text' => "*Created:*\n" . date('M d, Y H:i')],
                ['type' => 'mrkdwn', 'text' => "*Priority:*\nNormal"],
            ]
        ],
        [
            'type' => 'section',
            'text' => ['type' => 'mrkdwn', 'text' => "*Description:*\n{$description}"]
        ],
        [
            'type' => 'actions',
            'elements' => [
                ['type' => 'button', 'text' => ['type' => 'plain_text', 'text' => 'View My Tickets'], 'action_id' => 'view_tickets', 'value' => 'view_all'],
                ['type' => 'button', 'text' => ['type' => 'plain_text', 'text' => 'Update Ticket'], 'action_id' => 'update_ticket', 'value' => $ticketId],
            ]
        ],
    ];

    $context->getDriver()->sendRichMessage('Ticket Management', $blocks);
    return null; // we already sent a rich message
});
```

### List Tickets

```php
$bot->hears('/ticket list', function($context) {
    $tickets = $context->getConversation()->get('tickets', []);
    if (empty($tickets)) {
        return "📋 You don't have any support tickets yet.\nUse `/ticket create [description]` to create one.";
    }
    // build and send blocks to display tickets...
});
```

### Update via Interactive Button

Interactive payloads are parsed and converted into a message like `action:<action_id>:<value>`. The example listens for these and updates tickets accordingly.

```php
$bot->hears('action:update_ticket:*', function($context) {
    $ticketId = $context->getParam(0) ?? null; // or parse from message
    // ask user for new status, or update directly...
});
```

### Reactions and Mentions

SlackDriver normalizes reaction events and mentions so you can match them:

```php
$bot->hears('reaction_added:*', function($context) {
    // context->getMessage() like 'reaction_added:thumbsup'
});

$bot->hears('*', function($context) {
    if ($context->getDriver()->isMention()) {
        return 'You mentioned me? How can I help?';
    }
});
```

## Signature Verification

SlackDriver can verify requests using `SLACK_SIGNING_SECRET`:
- Extracts `X-Slack-Request-Timestamp` and `X-Slack-Signature`
- Rejects stale requests (>5 minutes)
- Validates HMAC SHA256 signature over the raw body

Ensure you pass the raw `$request->all()` to the driver and do not alter the body before the driver reads it.

## Ephemeral Messages & Reactions

```php
$driver = $context->getDriver();
$driver->sendEphemeralMessage('Only you can see this', $context->getSenderId());
$driver->addReaction('thumbsup', $messageTs, $channelId);
$driver->updateMessage($messageTs, 'Updated text', $channelId);
$driver->deleteMessage($messageTs, $channelId);
```

## Testing and Local Development

- Use PHPUnit to run tests: `composer test`
- See `tests/Drivers/SlackDriverTest.php` for how different events and payloads are parsed
- In development, use ngrok to expose your local server to Slack:
  - `ngrok http http://localhost:8000`
  - Set Slack request URLs to the ngrok HTTPS URL

## Troubleshooting

- 403/Verification failed: Check `SLACK_SIGNING_SECRET` and system time sync; ensure raw body is intact
- Event challenge not acknowledged: Your route must allow the driver to echo the `challenge` value once on verification
- No response to slash command: Respond quickly (HTTP 200), and optionally send updates via `sendMessage`/`sendRichMessage`
- Missing scopes: Add required scopes in `OAuth & Permissions` and reinstall the app

## Full Example

Open `examples/slack.php` for a ready-to-run, full-featured implementation with ticket management, interactive buttons, logging, and persistent storage.
