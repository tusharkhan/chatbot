<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use TusharKhan\Chatbot\Drivers\SlackDriver;
use TusharKhan\Chatbot\Storage\FileStore;
use TusharKhan\Chatbot\Core\Bot;

/**
 * Comprehensive Slack Bot Commands Example
 *
 * This example demonstrates how to handle various types of Slack commands:
 * - Slash commands (/weather, /help, /task)
 * - Regular message patterns
 * - App mentions (@botname)
 * - Interactive buttons and menus
 * - Rich message formatting with blocks
 */

// Main Slack webhook endpoint
Route::post('/slack/webhook', function (\Illuminate\Http\Request $request) {
    try {
        // Get webhook data
        $webhookData = $request->all();

        // Log incoming webhook for debugging (remove in production)
        Log::info('Slack webhook received:', $webhookData);

        // Your Slack Bot Token (get from Slack App settings)
        $botToken = 'xoxb-your-bot-token-here';
        $signingSecret = 'your-signing-secret-here'; // Optional but recommended for security

        // Initialize driver with webhook data and signing secret
        $driver = new SlackDriver($botToken, $signingSecret, $webhookData);

        // Initialize storage
        $storage = new FileStore(storage_path('app/chatbot'));

        // Initialize bot
        $bot = new Bot($driver, $storage);

        // ==============================================
        // SLASH COMMANDS
        // ==============================================

        // Handle /help command
        $bot->hears('/help', function($context) {
            $blocks = [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*Available Commands:*\n\n" .
                                 "• `/help` - Show this help message\n" .
                                 "• `/weather [city]` - Get weather for a city\n" .
                                 "• `/task add [description]` - Add a new task\n" .
                                 "• `/task list` - List all tasks\n" .
                                 "• `/status` - Check bot status\n" .
                                 "• `@botname hello` - Say hello to the bot"
                    ]
                ],
                [
                    'type' => 'actions',
                    'elements' => [
                        [
                            'type' => 'button',
                            'text' => [
                                'type' => 'plain_text',
                                'text' => 'Check Status'
                            ],
                            'action_id' => 'check_status',
                            'value' => 'status'
                        ],
                        [
                            'type' => 'button',
                            'text' => [
                                'type' => 'plain_text',
                                'text' => 'List Tasks'
                            ],
                            'action_id' => 'list_tasks',
                            'value' => 'tasks'
                        ]
                    ]
                ]
            ];

            $context->getDriver()->sendRichMessage("Here's what I can help you with:", $blocks);
            return null; // Don't send additional message
        });

        // Handle /weather command with parameter
        $bot->hears('/weather {city?}', function($context) {
            $city = $context->getParam('city') ?: 'London';

            // Simulate weather API call (replace with real API)
            $weatherData = [
                'temperature' => rand(15, 30) . '°C',
                'condition' => ['Sunny', 'Cloudy', 'Rainy', 'Partly Cloudy'][rand(0, 3)],
                'humidity' => rand(40, 80) . '%'
            ];

            $blocks = [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*Weather in {$city}*"
                    ]
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Temperature:*\n{$weatherData['temperature']}"
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Condition:*\n{$weatherData['condition']}"
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Humidity:*\n{$weatherData['humidity']}"
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Updated:*\n" . date('H:i')
                        ]
                    ]
                ]
            ];

            $context->getDriver()->sendRichMessage("Weather information:", $blocks);
            return null;
        });

        // Handle /task commands with subcommands
        $bot->hears('/task add {description}', function($context) {
            $description = $context->getParam('description');
            $userId = $context->getDriver()->getSenderId();

            // Store task in conversation storage
            $tasks = $context->getConversation()->get('tasks', []);
            $tasks[] = [
                'id' => count($tasks) + 1,
                'description' => $description,
                'created_at' => date('Y-m-d H:i:s'),
                'completed' => false
            ];
            $context->getConversation()->set('tasks', $tasks);

            return "✅ Task added: *{$description}*\nUse `/task list` to see all your tasks.";
        });

        $bot->hears('/task list', function($context) {
            $tasks = $context->getConversation()->get('tasks', []);

            if (empty($tasks)) {
                return "📝 You don't have any tasks yet. Use `/task add [description]` to create one.";
            }

            $taskList = "*Your Tasks:*\n\n";
            foreach ($tasks as $task) {
                $status = $task['completed'] ? '✅' : '⏳';
                $taskList .= "{$status} {$task['id']}. {$task['description']}\n";
            }

            $blocks = [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => $taskList
                    ]
                ],
                [
                    'type' => 'actions',
                    'elements' => [
                        [
                            'type' => 'button',
                            'text' => [
                                'type' => 'plain_text',
                                'text' => 'Add New Task'
                            ],
                            'action_id' => 'add_task',
                            'value' => 'add_task'
                        ],
                        [
                            'type' => 'button',
                            'text' => [
                                'type' => 'plain_text',
                                'text' => 'Clear All'
                            ],
                            'action_id' => 'clear_tasks',
                            'value' => 'clear_tasks',
                            'style' => 'danger'
                        ]
                    ]
                ]
            ];

            $context->getDriver()->sendRichMessage("Task Management", $blocks);
            return null;
        });

        // Handle /status command
        $bot->hears('/status', function($context) {
            $uptime = gmdate('H:i:s', time() - $_SERVER['REQUEST_TIME_FLOAT']);
            $userInfo = $context->getDriver()->getUserInfo($context->getDriver()->getSenderId());
            $userName = $userInfo['real_name'] ?? $userInfo['name'] ?? 'User';

            $blocks = [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "🤖 *Bot Status*\n\nHello {$userName}! I'm running smoothly."
                    ]
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Status:*\n🟢 Online"
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Uptime:*\n{$uptime}"
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Version:*\nv1.0.0"
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Last Updated:*\n" . date('Y-m-d H:i')
                        ]
                    ]
                ]
            ];

            $context->getDriver()->sendRichMessage("System Status", $blocks);
            return null;
        });

        // ==============================================
        // APP MENTIONS (@botname)
        // ==============================================

        // Handle when bot is mentioned
        $bot->hears('<@.*> hello|hello <@.*>', function($context) {
            $userInfo = $context->getDriver()->getUserInfo($context->getDriver()->getSenderId());
            $firstName = $userInfo['real_name'] ?? $userInfo['name'] ?? 'there';

            $greetings = [
                "Hello {$firstName}! 👋 How can I help you today?",
                "Hi {$firstName}! 😊 What can I do for you?",
                "Hey {$firstName}! 🌟 Ready to assist you!",
                "Greetings {$firstName}! 🤖 How may I be of service?"
            ];

            $greeting = $greetings[array_rand($greetings)];

            $blocks = [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => $greeting
                    ]
                ],
                [
                    'type' => 'actions',
                    'elements' => [
                        [
                            'type' => 'button',
                            'text' => [
                                'type' => 'plain_text',
                                'text' => 'Show Commands'
                            ],
                            'action_id' => 'show_help',
                            'value' => 'help'
                        ],
                        [
                            'type' => 'button',
                            'text' => [
                                'type' => 'plain_text',
                                'text' => 'Check Weather'
                            ],
                            'action_id' => 'check_weather',
                            'value' => 'weather'
                        ]
                    ]
                ]
            ];

            $context->getDriver()->sendRichMessage("Welcome!", $blocks);
            return null;
        });

        // ==============================================
        // INTERACTIVE COMPONENTS (Buttons, Menus)
        // ==============================================

        // Handle button clicks
        $bot->hears('action:check_status:status', function($context) {
            return "🤖 Bot is running perfectly! All systems operational.";
        });

        $bot->hears('action:list_tasks:tasks', function($context) {
            $tasks = $context->getConversation()->get('tasks', []);
            if (empty($tasks)) {
                return "📝 No tasks found. Use `/task add [description]` to create your first task.";
            }

            $taskList = "Your current tasks:\n";
            foreach ($tasks as $task) {
                $status = $task['completed'] ? '✅' : '⏳';
                $taskList .= "{$status} {$task['description']}\n";
            }
            return $taskList;
        });

        $bot->hears('action:show_help:help', function($context) {
            return "Type `/help` to see all available commands, or try:\n" .
                   "• `/weather London` - Get weather\n" .
                   "• `/task add Buy groceries` - Add a task\n" .
                   "• `/status` - Check bot status";
        });

        $bot->hears('action:check_weather:weather', function($context) {
            return "Use `/weather [city]` to get weather information. For example: `/weather New York`";
        });

        $bot->hears('action:add_task:add_task', function($context) {
            return "To add a new task, use: `/task add [your task description]`\n" .
                   "Example: `/task add Finish the presentation`";
        });

        $bot->hears('action:clear_tasks:clear_tasks', function($context) {
            $context->getConversation()->set('tasks', []);
            return "🗑️ All tasks have been cleared!";
        });

        // ==============================================
        // REGULAR MESSAGE PATTERNS
        // ==============================================

        // Handle common greetings
        $bot->hears('hi|hello|hey|good morning|good afternoon', function($context) {
            return "Hello! 👋 I'm your helpful bot. Type `/help` to see what I can do for you!";
        });

        // Handle thank you messages
        $bot->hears('thank you|thanks|thx', function($context) {
            return "You're welcome! 😊 Happy to help anytime!";
        });

        // Handle questions about the bot
        $bot->hears('what can you do|what are your features|help me', function($context) {
            return "I can help you with several things! Type `/help` to see all my commands, or try:\n" .
                   "• Ask about weather: `/weather [city]`\n" .
                   "• Manage tasks: `/task add [description]`\n" .
                   "• Check my status: `/status`";
        });

        // Handle reactions (emoji responses)
        $bot->hears('reaction_added:thumbsup', function($context) {
            $context->getDriver()->addReaction('heart', $context->getDriver()->getData()['event']['item']['ts']);
            return null;
        });

        // ==============================================
        // FALLBACK HANDLER
        // ==============================================

        // Handle unrecognized messages
        $bot->fallback(function($context) {
            $message = $context->getMessage();

            // Don't respond to certain types of messages
            if (strpos($message, 'reaction_') === 0 ||
                strpos($message, 'action:') === 0 ||
                empty(trim($message))) {
                return null;
            }

            $blocks = [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "I didn't quite understand that. 🤔\n\nHere are some things you can try:"
                    ]
                ],
                [
                    'type' => 'actions',
                    'elements' => [
                        [
                            'type' => 'button',
                            'text' => [
                                'type' => 'plain_text',
                                'text' => 'Show Help'
                            ],
                            'action_id' => 'show_help',
                            'value' => 'help'
                        ],
                        [
                            'type' => 'button',
                            'text' => [
                                'type' => 'plain_text',
                                'text' => 'Check Weather'
                            ],
                            'action_id' => 'check_weather',
                            'value' => 'weather'
                        ],
                        [
                            'type' => 'button',
                            'text' => [
                                'type' => 'plain_text',
                                'text' => 'Manage Tasks'
                            ],
                            'action_id' => 'list_tasks',
                            'value' => 'tasks'
                        ]
                    ]
                ]
            ];

            $context->getDriver()->sendRichMessage("Need help?", $blocks);
            return null;
        });

        // Process the message
        $bot->listen();

        // Return success response to Slack
        return response()->json(['status' => 'ok']);

    } catch (\Exception $e) {
        Log::error('Slack webhook error:', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json(['error' => 'Internal server error'], 500);
    }
});

// ==============================================
// UTILITY ROUTES FOR SETUP AND TESTING
// ==============================================

// Route to set up the webhook (run this once)
Route::get('/slack/setup-webhook', function () {
    $botToken = 'xoxb-your-bot-token-here';
    $signingSecret = 'your-signing-secret-here';

    $driver = new SlackDriver($botToken, $signingSecret, []);

    $webhookUrl = url('/slack/webhook');

    // Note: You need to manually set up the webhook in your Slack app settings
    // This is just for reference
    return response()->json([
        'message' => 'Set this URL as your Slack app webhook endpoint',
        'webhook_url' => $webhookUrl,
        'instructions' => [
            '1. Go to your Slack app settings at https://api.slack.com/apps',
            '2. Navigate to "Event Subscriptions" and enable events',
            '3. Set the Request URL to: ' . $webhookUrl,
            '4. Subscribe to these bot events: message.channels, message.groups, message.im, app_mention',
            '5. Install the app to your workspace'
        ]
    ]);
});

// Route to test the bot locally (for development)
Route::post('/slack/test', function (\Illuminate\Http\Request $request) {
    $testMessage = $request->input('message', '/help');

    // Simulate Slack webhook data
    $webhookData = [
        'type' => 'event_callback',
        'event' => [
            'type' => 'message',
            'text' => $testMessage,
            'user' => 'U1234567890',
            'channel' => 'C1234567890',
            'ts' => time()
        ]
    ];

    $botToken = 'xoxb-your-bot-token-here';
    $driver = new SlackDriver($botToken, null, $webhookData);
    $storage = new FileStore(storage_path('app/chatbot'));
    $bot = new Bot($driver, $storage);

    // Simple test responses
    $bot->hears('/help', function($context) {
        return "Help: Available commands are /help, /weather, /task, /status";
    });

    $bot->hears('/weather {city?}', function($context) {
        $city = $context->getParam('city') ?: 'London';
        return "Weather in {$city}: 22°C, Sunny ☀️";
    });

    $bot->fallback(function($context) {
        return "Test response for: " . $context->getMessage();
    });

    $bot->listen();

    return response()->json([
        'message' => 'Test completed',
        'input' => $testMessage,
        'note' => 'Check your logs for the bot response'
    ]);
});
