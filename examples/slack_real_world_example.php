<?php

// NOTE: This is a Laravel-specific example. The package is framework-agnostic; adapt as needed for other frameworks or vanilla PHP.
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use TusharKhan\Chatbot\Drivers\SlackDriver;
use TusharKhan\Chatbot\Storage\FileStore;
use TusharKhan\Chatbot\Core\Bot;

/**
 * Real-World Slack Bot Implementation
 *
 * This example shows how to implement a production-ready Slack bot
 * for a customer support or team productivity use case.
 */

// Main webhook endpoint for your Slack app
Route::post('/slack/webhook', function (\Illuminate\Http\Request $request) {
    try {
        // Get your tokens from environment variables
        $botToken = env('SLACK_BOT_TOKEN'); // xoxb-your-bot-token
        $signingSecret = env('SLACK_SIGNING_SECRET'); // your-signing-secret

        // Get webhook data
        $webhookData = $request->all();

        // Initialize driver
        $driver = new SlackDriver($botToken, $signingSecret, $webhookData);
        $storage = new FileStore(storage_path('app/chatbot'));
        $bot = new Bot($driver, $storage);

        // ========================================
        // CUSTOMER SUPPORT BOT COMMANDS
        // ========================================

        // Ticket management system
        $bot->hears('/ticket create {description}', function($context) {
            $description = $context->getParam('description');
            $userId = $context->getDriver()->getSenderId();

            // Generate ticket ID
            $ticketId = 'TICK-' . strtoupper(substr(md5($userId . time()), 0, 6));

            // Store ticket in conversation
            $tickets = $context->getConversation()->get('tickets', []);
            $tickets[$ticketId] = [
                'id' => $ticketId,
                'description' => $description,
                'status' => 'open',
                'created_at' => date('Y-m-d H:i:s'),
                'assigned_to' => null
            ];
            $context->getConversation()->set('tickets', $tickets);

            // Get user info for personalization
            $userInfo = $context->getDriver()->getUserInfo($userId);
            $userName = $userInfo['real_name'] ?? $userInfo['name'] ?? 'User';

            $blocks = [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "🎫 *Ticket Created Successfully*\n\nHi {$userName}! Your support ticket has been created."
                    ]
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Ticket ID:*\n{$ticketId}"
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Status:*\n🟡 Open"
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Created:*\n" . date('M d, Y H:i')
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Priority:*\nNormal"
                        ]
                    ]
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*Description:*\n{$description}"
                    ]
                ],
                [
                    'type' => 'actions',
                    'elements' => [
                        [
                            'type' => 'button',
                            'text' => [
                                'type' => 'plain_text',
                                'text' => 'View My Tickets'
                            ],
                            'action_id' => 'view_tickets',
                            'value' => 'view_all'
                        ],
                        [
                            'type' => 'button',
                            'text' => [
                                'type' => 'plain_text',
                                'text' => 'Update Ticket'
                            ],
                            'action_id' => 'update_ticket',
                            'value' => $ticketId
                        ]
                    ]
                ]
            ];

            $context->getDriver()->sendRichMessage("Ticket Management", $blocks);

            // Notify support team (in a real app, you'd send to a support channel)
            $supportMessage = "🚨 New support ticket created by {$userName}\n" .
                            "Ticket ID: {$ticketId}\n" .
                            "Description: {$description}";

            Log::info('New support ticket created', [
                'ticket_id' => $ticketId,
                'user' => $userName,
                'description' => $description
            ]);

            return null;
        });

        // List user's tickets
        $bot->hears('/ticket list', function($context) {
            $tickets = $context->getConversation()->get('tickets', []);

            if (empty($tickets)) {
                return "📋 You don't have any support tickets yet.\nUse `/ticket create [description]` to create one.";
            }

            $blocks = [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*📋 Your Support Tickets*"
                    ]
                ]
            ];

            foreach ($tickets as $ticket) {
                $statusEmoji = $ticket['status'] === 'open' ? '🟡' :
                              ($ticket['status'] === 'in_progress' ? '🔵' : '✅');

                $blocks[] = [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => "*{$ticket['id']}*\n{$ticket['description']}"
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Status:*\n{$statusEmoji} " . ucfirst($ticket['status'])
                        ]
                    ],
                    'accessory' => [
                        'type' => 'button',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => 'View Details'
                        ],
                        'action_id' => 'view_ticket',
                        'value' => $ticket['id']
                    ]
                ];

                $blocks[] = ['type' => 'divider'];
            }

            $context->getDriver()->sendRichMessage("Support Tickets", $blocks);
            return null;
        });

        // Team productivity commands
        $bot->hears('/standup {status}', function($context) {
            $status = $context->getParam('status');
            $userId = $context->getDriver()->getSenderId();
            $channelId = $context->getDriver()->getChannelId();

            // Store standup update
            $today = date('Y-m-d');
            $standups = $context->getConversation()->get('standups', []);
            $standups[$today] = [
                'status' => $status,
                'timestamp' => time(),
                'channel' => $channelId
            ];
            $context->getConversation()->set('standups', $standups);

            $userInfo = $context->getDriver()->getUserInfo($userId);
            $userName = $userInfo['real_name'] ?? $userInfo['name'] ?? 'Team Member';

            // Format for team channel
            $standupMessage = "📊 *Daily Standup Update*\n\n" .
                            "*Team Member:* {$userName}\n" .
                            "*Date:* " . date('F d, Y') . "\n" .
                            "*Status:* {$status}\n" .
                            "*Time:* " . date('H:i T');

            return $standupMessage;
        });

        // Meeting scheduler
        $bot->hears('/schedule {title} at {time}', function($context) {
            $title = $context->getParam('title');
            $time = $context->getParam('time');
            $userId = $context->getDriver()->getSenderId();

            // Parse time (in a real app, you'd use a proper date parser)
            $meetingTime = strtotime($time);
            if (!$meetingTime) {
                return "❌ Invalid time format. Please use a format like 'tomorrow 2pm' or '2024-01-15 14:00'";
            }

            $meetingId = 'MEET-' . strtoupper(substr(md5($title . $meetingTime), 0, 6));

            $meetings = $context->getConversation()->get('meetings', []);
            $meetings[$meetingId] = [
                'id' => $meetingId,
                'title' => $title,
                'scheduled_time' => $meetingTime,
                'organizer' => $userId,
                'attendees' => [$userId],
                'status' => 'scheduled'
            ];
            $context->getConversation()->set('meetings', $meetings);

            $blocks = [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "📅 *Meeting Scheduled*\n\n*{$title}*"
                    ]
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Meeting ID:*\n{$meetingId}"
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Date & Time:*\n" . date('M d, Y @ H:i T', $meetingTime)
                        ]
                    ]
                ],
                [
                    'type' => 'actions',
                    'elements' => [
                        [
                            'type' => 'button',
                            'text' => [
                                'type' => 'plain_text',
                                'text' => 'Join Meeting'
                            ],
                            'action_id' => 'join_meeting',
                            'value' => $meetingId,
                            'style' => 'primary'
                        ],
                        [
                            'type' => 'button',
                            'text' => [
                                'type' => 'plain_text',
                                'text' => 'Invite Others'
                            ],
                            'action_id' => 'invite_meeting',
                            'value' => $meetingId
                        ]
                    ]
                ]
            ];

            $context->getDriver()->sendRichMessage("Meeting Scheduler", $blocks);
            return null;
        });

        // Company announcements
        $bot->hears('/announce {message}', function($context) {
            $message = $context->getParam('message');
            $userId = $context->getDriver()->getSenderId();

            // Check if user has announcement permissions (in real app, check roles/permissions)
            $userInfo = $context->getDriver()->getUserInfo($userId);
            $userName = $userInfo['real_name'] ?? $userInfo['name'] ?? 'Team Member';

            $blocks = [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "📢 *Company Announcement*"
                    ]
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => $message
                    ]
                ],
                [
                    'type' => 'context',
                    'elements' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => "Posted by {$userName} • " . date('M d, Y @ H:i T')
                        ]
                    ]
                ]
            ];

            $context->getDriver()->sendRichMessage("📢 Announcement", $blocks);
            return null;
        });

        // ========================================
        // INTERACTIVE BUTTON HANDLERS
        // ========================================

        $bot->hears('action:view_tickets:view_all', function($context) {
            $tickets = $context->getConversation()->get('tickets', []);
            $count = count($tickets);
            return "📋 You have {$count} total tickets. Use `/ticket list` to see details.";
        });

        $bot->hears('action:view_ticket:{ticketId}', function($context) {
            $ticketId = $context->getParam('ticketId');
            $tickets = $context->getConversation()->get('tickets', []);

            if (!isset($tickets[$ticketId])) {
                return "❌ Ticket not found: {$ticketId}";
            }

            $ticket = $tickets[$ticketId];
            $statusEmoji = $ticket['status'] === 'open' ? '🟡' :
                          ($ticket['status'] === 'in_progress' ? '🔵' : '✅');

            return "🎫 *Ticket Details*\n\n" .
                   "ID: {$ticket['id']}\n" .
                   "Status: {$statusEmoji} " . ucfirst($ticket['status']) . "\n" .
                   "Created: {$ticket['created_at']}\n" .
                   "Description: {$ticket['description']}";
        });

        $bot->hears('action:join_meeting:{meetingId}', function($context) {
            $meetingId = $context->getParam('meetingId');
            $meetings = $context->getConversation()->get('meetings', []);

            if (!isset($meetings[$meetingId])) {
                return "❌ Meeting not found: {$meetingId}";
            }

            $meeting = $meetings[$meetingId];
            $meetingTime = date('M d, Y @ H:i T', $meeting['scheduled_time']);

            return "🎯 Joining meeting: *{$meeting['title']}*\n" .
                   "Scheduled for: {$meetingTime}\n" .
                   "Meeting link: https://zoom.us/j/example-{$meetingId}";
        });

        // ========================================
        // GENERAL COMMANDS & FALLBACKS
        // ========================================

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

        // Handle mentions
        $bot->hears('<@.*> hello|hello <@.*>', function($context) {
            $userInfo = $context->getDriver()->getUserInfo($context->getDriver()->getSenderId());
            $firstName = $userInfo['real_name'] ?? $userInfo['name'] ?? 'there';

            return "Hello {$firstName}! 👋 I'm your team productivity bot. Type `/help` to see what I can do!";
        });

        // Fallback for unrecognized commands
        $bot->fallback(function($context) {
            $message = $context->getMessage();

            // Don't respond to reactions or empty messages
            if (strpos($message, 'reaction_') === 0 ||
                strpos($message, 'action:') === 0 ||
                empty(trim($message))) {
                return null;
            }

            return "🤔 I didn't understand that command. Type `/help` to see available commands.";
        });

        // Process the message
        $bot->listen();

        return response()->json(['status' => 'ok']);

    } catch (\Exception $e) {
        Log::error('Slack webhook error:', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json(['error' => 'Internal server error'], 500);
    }
});

// Test endpoint for development
Route::post('/slack/test-command', function (\Illuminate\Http\Request $request) {
    $command = $request->input('command', '/help');

    echo "Testing Slack command: {$command}\n\n";

    // Simulate different command types
    if (strpos($command, '/ticket create') === 0) {
        echo "✅ Would create a support ticket\n";
        echo "Command format: /ticket create [description]\n";
        echo "Example: /ticket create My computer won't start\n";
    } elseif (strpos($command, '/schedule') === 0) {
        echo "📅 Would schedule a meeting\n";
        echo "Command format: /schedule [title] at [time]\n";
        echo "Example: /schedule Team standup at tomorrow 9am\n";
    } else {
        echo "ℹ️  Use /help to see all available commands\n";
    }

    return response()->json(['test' => 'completed']);
});
