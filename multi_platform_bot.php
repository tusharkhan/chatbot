<?php

/**
 * Multi-Platform Chatbot Example
 * 
 * This file demonstrates how to use the same bot logic for both 
 * Telegram and WhatsApp platforms using the unified Bot framework.
 */

require_once 'vendor/autoload.php';

use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\TelegramDriver;
use TusharKhan\Chatbot\Drivers\WhatsAppDriver;

// Configuration
$platform = $_GET['platform'] ?? 'auto'; // auto, telegram, whatsapp

// Telegram Configuration
$telegramToken = '7745849469:AAGXVrpEGCEcRgdLaiP8KoXMOLD0kyLVd1g';

// WhatsApp Configuration
$whatsappAccessToken = 'YOUR_WHATSAPP_ACCESS_TOKEN';
$whatsappPhoneNumberId = 'YOUR_PHONE_NUMBER_ID';
$whatsappVerifyToken = 'YOUR_VERIFY_TOKEN';

// Auto-detect platform or use specified platform
if ($platform === 'auto') {
    // Check if it's a WhatsApp webhook verification
    if (isset($_GET['hub_mode']) && $_GET['hub_mode'] === 'subscribe') {
        $platform = 'whatsapp';
    }
    // Check for WhatsApp webhook data
    elseif (isset($_POST['object']) && $_POST['object'] === 'whatsapp_business_account') {
        $platform = 'whatsapp';
    }
    // Check for Telegram webhook data
    else {
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['update_id'])) {
            $platform = 'telegram';
        } else {
            $platform = 'whatsapp'; // Default to WhatsApp
        }
    }
}

// Initialize appropriate driver
$driver = null;
$botName = '';

switch ($platform) {
    case 'telegram':
        $driver = new TelegramDriver($telegramToken);
        $botName = 'Telegram Bot';
        break;
        
    case 'whatsapp':
        // Handle WhatsApp webhook verification
        if (isset($_GET['hub_mode']) && $_GET['hub_mode'] === 'subscribe') {
            $tempDriver = new WhatsAppDriver($whatsappAccessToken, $whatsappPhoneNumberId);
            $challenge = $tempDriver->verifyWebhook($whatsappVerifyToken);
            
            if ($challenge) {
                echo $challenge;
                exit;
            } else {
                http_response_code(403);
                echo 'Verification failed';
                exit;
            }
        }
        
        $driver = new WhatsAppDriver($whatsappAccessToken, $whatsappPhoneNumberId);
        $botName = 'WhatsApp Bot';
        break;
        
    default:
        http_response_code(400);
        echo 'Invalid platform specified';
        exit;
}

// Create bot instance
$bot = new Bot($driver);

// Log the platform for debugging
error_log("Multi-platform bot: Using $platform driver");

// Common welcome message for both platforms
$bot->hears('hello|hi|hey|start|/start', function ($bot, $message) use ($botName) {
    $platformEmoji = strpos($botName, 'Telegram') !== false ? '🤖' : '💬';
    
    $welcomeMessage = "$platformEmoji *Welcome to $botName!*\n\n";
    $welcomeMessage .= "Hello! I'm your chatbot assistant available on multiple platforms.\n\n";
    $welcomeMessage .= "📋 *Available Commands:*\n";
    $welcomeMessage .= "• Type *menu* - Show main menu\n";
    $welcomeMessage .= "• Type *help* - Get help\n";
    $welcomeMessage .= "• Type *features* - See platform features\n";
    $welcomeMessage .= "• Type *buttons* - Interactive buttons\n";
    $welcomeMessage .= "• Type *image* - Get sample image\n";
    $welcomeMessage .= "• Type *contact* - Contact information\n";
    $welcomeMessage .= "• Type *ping* - Test response\n\n";
    $welcomeMessage .= "I work on both Telegram and WhatsApp! 🚀";

    $bot->reply($welcomeMessage);
});

// Help command
$bot->hears('help|/help', function ($bot, $message) use ($botName) {
    $helpMessage = "📋 *Help - $botName*\n\n";
    $helpMessage .= "*Available Commands:*\n";
    $helpMessage .= "• hello/hi/start - Welcome message\n";
    $helpMessage .= "• menu - Main menu with options\n";
    $helpMessage .= "• features - Platform-specific features\n";
    $helpMessage .= "• buttons - Interactive button demo\n";
    $helpMessage .= "• image - Send sample image\n";
    $helpMessage .= "• contact - Get contact info\n";
    $helpMessage .= "• ping - Test bot response\n";
    $helpMessage .= "• platform - Show current platform\n\n";
    $helpMessage .= "*Multi-Platform Features:*\n";
    $helpMessage .= "✅ Works on Telegram & WhatsApp\n";
    $helpMessage .= "✅ Unified message handling\n";
    $helpMessage .= "✅ Platform-specific optimizations\n";
    $helpMessage .= "✅ Rich interactive elements\n\n";
    $helpMessage .= "Need assistance? Type *contact* to reach us!";

    $bot->reply($helpMessage);
});

// Platform-specific features
$bot->hears('features|/features', function ($bot, $message) use ($botName, $platform) {
    $featuresMessage = "⭐ *Platform Features - $botName*\n\n";
    
    if ($platform === 'telegram') {
        $featuresMessage .= "🤖 *Telegram Features:*\n";
        $featuresMessage .= "✅ Custom keyboards\n";
        $featuresMessage .= "✅ Inline keyboards with callbacks\n";
        $featuresMessage .= "✅ Rich text formatting\n";
        $featuresMessage .= "✅ Photo sharing with captions\n";
        $featuresMessage .= "✅ Document sharing\n";
        $featuresMessage .= "✅ Typing indicators\n";
        $featuresMessage .= "✅ User profile information\n";
        $featuresMessage .= "✅ Chat information\n";
    } else {
        $featuresMessage .= "💬 *WhatsApp Features:*\n";
        $featuresMessage .= "✅ Interactive buttons (up to 3)\n";
        $featuresMessage .= "✅ Interactive lists\n";
        $featuresMessage .= "✅ Rich media sharing\n";
        $featuresMessage .= "✅ Template messages\n";
        $featuresMessage .= "✅ Message read receipts\n";
        $featuresMessage .= "✅ Business API integration\n";
        $featuresMessage .= "✅ Media message detection\n";
        $featuresMessage .= "✅ Button/list interactions\n";
    }
    
    $featuresMessage .= "\n🌟 *Cross-Platform:*\n";
    $featuresMessage .= "✅ Unified bot logic\n";
    $featuresMessage .= "✅ Pattern matching\n";
    $featuresMessage .= "✅ Fallback handling\n";
    $featuresMessage .= "✅ Error logging\n";

    $bot->reply($featuresMessage);
});

// Show current platform
$bot->hears('platform|/platform', function ($bot, $message) use ($platform, $botName) {
    $platformMessage = "🔍 *Current Platform Information*\n\n";
    $platformMessage .= "📱 *Platform:* " . ucfirst($platform) . "\n";
    $platformMessage .= "🤖 *Bot Name:* $botName\n";
    $platformMessage .= "⚡ *Status:* Active and running\n";
    $platformMessage .= "🔄 *Multi-Platform:* Yes\n\n";
    
    if ($platform === 'telegram') {
        $platformMessage .= "🤖 *Telegram Specific:*\n";
        $platformMessage .= "• Username: @chat_app_test_bot\n";
        $platformMessage .= "• Rich keyboards available\n";
        $platformMessage .= "• Callback queries supported\n";
    } else {
        $platformMessage .= "💬 *WhatsApp Specific:*\n";
        $platformMessage .= "• Business API integration\n";
        $platformMessage .= "• Interactive messages\n";
        $platformMessage .= "• Template messaging\n";
    }

    $bot->reply($platformMessage);
});

// Interactive buttons (platform-specific implementation)
$bot->hears('buttons|/buttons', function ($bot, $message) use ($platform) {
    $buttonText = "🔘 *Interactive Buttons Demo*\n\nChoose an option:";
    
    if ($platform === 'telegram') {
        // Telegram inline keyboard
        $inlineKeyboard = [
            [
                ['text' => '👍 Like', 'callback_data' => 'like'],
                ['text' => '👎 Dislike', 'callback_data' => 'dislike']
            ],
            [
                ['text' => '📋 More Options', 'callback_data' => 'more']
            ]
        ];
        
        if ($bot->driver() instanceof TelegramDriver) {
            /** @var TelegramDriver $telegramDriver */
            $telegramDriver = $bot->driver();
            $telegramDriver->sendInlineKeyboard($buttonText, $inlineKeyboard);
        }
    } else {
        // WhatsApp buttons
        $buttons = [
            ['id' => 'like', 'title' => '👍 Like'],
            ['id' => 'dislike', 'title' => '👎 Dislike'],
            ['id' => 'more', 'title' => '📋 More Options']
        ];
        
        if ($bot->driver() instanceof WhatsAppDriver) {
            /** @var WhatsAppDriver $whatsappDriver */
            $whatsappDriver = $bot->driver();
            $whatsappDriver->sendButtons($buttonText, $buttons);
        }
    }
});

// Handle button responses
$bot->hears('👍 Like|like', function ($bot, $message) {
    $bot->reply("👍 *Thanks for the like!*\n\nWe appreciate your positive feedback! 😊");
});

$bot->hears('👎 Dislike|dislike', function ($bot, $message) {
    $bot->reply("👎 *Thanks for the feedback!*\n\nWe'll work to improve our service! 🙏");
});

$bot->hears('📋 More Options|more', function ($bot, $message) use ($platform) {
    if ($platform === 'telegram') {
        $moreMessage = "📋 *More Telegram Options*\n\n";
        $moreMessage .= "Try these Telegram-specific features:\n";
        $moreMessage .= "• /keyboard - Custom keyboard\n";
        $moreMessage .= "• /photo - Send photo\n";
        $moreMessage .= "• /info - Your info\n";
    } else {
        $moreMessage = "📋 *More WhatsApp Options*\n\n";
        $moreMessage .= "Try these WhatsApp-specific features:\n";
        $moreMessage .= "• list - Interactive list\n";
        $moreMessage .= "• template - Template message\n";
        $moreMessage .= "• media - Media handling\n";
    }
    
    $bot->reply($moreMessage);
});

// Menu command
$bot->hears('menu|/menu', function ($bot, $message) use ($platform) {
    $menuText = "🍽️ *Main Menu*\n\nChoose a category:";
    
    if ($platform === 'telegram') {
        // Telegram keyboard
        $keyboard = [
            [['text' => '🛍️ Services'], ['text' => '📞 Contact']],
            [['text' => '⚙️ Settings'], ['text' => '❓ Help']],
            [['text' => '📊 Stats'], ['text' => '🚪 Exit']]
        ];
        
        if ($bot->driver() instanceof TelegramDriver) {
            /** @var TelegramDriver $telegramDriver */
            $telegramDriver = $bot->driver();
            $telegramDriver->sendKeyboard($menuText, $keyboard);
        }
    } else {
        // WhatsApp list
        $sections = [
            [
                'title' => 'Main Options',
                'rows' => [
                    ['id' => 'services', 'title' => '🛍️ Services', 'description' => 'Our services'],
                    ['id' => 'contact', 'title' => '📞 Contact', 'description' => 'Get in touch'],
                    ['id' => 'help', 'title' => '❓ Help', 'description' => 'Get assistance']
                ]
            ]
        ];
        
        if ($bot->driver() instanceof WhatsAppDriver) {
            /** @var WhatsAppDriver $whatsappDriver */
            $whatsappDriver = $bot->driver();
            $whatsappDriver->sendList($menuText, 'Choose Option', $sections);
        }
    }
});

// Image sharing
$bot->hears('image|photo|picture|/photo', function ($bot, $message) use ($platform) {
    $imageUrl = 'https://picsum.photos/800/600?random=' . time();
    $caption = "📸 *Sample Image*\n\nThis image works on both platforms!\n\nPlatform: " . ucfirst($platform);
    $success = false; // Initialize success variable

    if ($platform === 'telegram') {
        if ($bot->driver() instanceof TelegramDriver) {
            /** @var TelegramDriver $telegramDriver */
            $telegramDriver = $bot->driver();
            $success = $telegramDriver->sendPhoto($imageUrl, $caption);
        }
    } else {
        if ($bot->driver() instanceof WhatsAppDriver) {
            /** @var WhatsAppDriver $whatsappDriver */
            $whatsappDriver = $bot->driver();
            $success = $whatsappDriver->sendImage($imageUrl, $caption);
        }
    }
    
    if (!$success) {
        $bot->reply("❌ Sorry, couldn't send the image right now. Please try again later.");
    }
});

// Contact information
$bot->hears('contact|/contact', function ($bot, $message) use ($platform) {
    $contactMessage = "📞 *Contact Information*\n\n";
    $contactMessage .= "Get in touch with us:\n\n";
    $contactMessage .= "📧 *Email:* support@company.com\n";
    $contactMessage .= "📱 *Phone:* +1 (555) 123-4567\n";
    $contactMessage .= "🌐 *Website:* www.company.com\n\n";
    
    if ($platform === 'telegram') {
        $contactMessage .= "🤖 *Telegram:* @chat_app_test_bot\n";
    } else {
        $contactMessage .= "💬 *WhatsApp:* This number\n";
    }
    
    $contactMessage .= "🕒 *Hours:* Mon-Fri 9AM-6PM EST\n\n";
    $contactMessage .= "Available on both Telegram and WhatsApp! 🚀";

    $bot->reply($contactMessage);
});

// Ping test
$bot->hears('ping|test|/ping', function ($bot, $message) use ($platform, $botName) {
    $success = false; // Initialize success variable
    
    $bot->reply("🏓 *Pong from $botName!*\n\n✅ Bot is working perfectly!\n📱 Platform: " . ucfirst($platform) . "\n🕒 Response time: < 1 second");
});

// Handle Telegram-specific features
if ($platform === 'telegram' && $bot->driver() instanceof TelegramDriver) {
    /** @var TelegramDriver $telegramDriver */
    $telegramDriver = $bot->driver();
    
    // Handle Telegram callback queries - check if we have a callback query
    $callbackQuery = $telegramDriver->getCallbackQuery();
    if ($callbackQuery) {
        $callbackData = $callbackQuery['data'];
        $callbackQueryId = $callbackQuery['id'];

        switch ($callbackData) {
            case 'like':
                $telegramDriver->answerCallbackQuery($callbackQueryId, "Thanks for the like! 👍");
                $telegramDriver->sendMessage("👍 *Liked!* Thanks for your feedback!");
                break;

            case 'dislike':
                $telegramDriver->answerCallbackQuery($callbackQueryId, "We'll improve! 👎");
                $telegramDriver->sendMessage("👎 *Noted!* We appreciate honest feedback!");
                break;

            case 'more':
                $telegramDriver->answerCallbackQuery($callbackQueryId, "More options coming up!");
                $telegramDriver->sendMessage("📋 *More Options*\nTry /help for all commands!");
                break;

            default:
                $telegramDriver->answerCallbackQuery($callbackQueryId, "Unknown action");
                break;
        }
        
        // Don't process regular message handlers for callback queries
        exit;
    }
}

// Common message patterns
$bot->hears('good morning|good afternoon|good evening', function ($bot, $message) {
    $greetings = [
        "🌅 Good morning! How can I help you today?",
        "☀️ Hello! Ready to start something amazing?",
        "🌙 Good evening! What can I do for you?",
        "✨ Great to hear from you! How may I assist?"
    ];

    $randomGreeting = $greetings[array_rand($greetings)];
    $bot->reply($randomGreeting);
});

$bot->hears('thank you|thanks|appreciate', function ($bot, $message) {
    $bot->reply("😊 *You're very welcome!*\n\nI'm happy to help! Available on both Telegram and WhatsApp! 🚀");
});

$bot->hears('bye|goodbye|see you', function ($bot, $message) {
    $goodbyes = [
        "👋 Goodbye! Message me anytime on either platform!",
        "🌟 See you later! Available 24/7 on Telegram & WhatsApp!",
        "✨ Take care! I'm here whenever you need help!",
        "🚀 Until next time! Thanks for using our multi-platform bot!"
    ];

    $randomGoodbye = $goodbyes[array_rand($goodbyes)];
    $bot->reply($randomGoodbye);
});

// Fallback for unmatched messages
$bot->fallback(function ($bot, $message) use ($platform, $botName) {
    $userMessage = $bot->getDriver()->getMessage();
    
    $fallbackMessage = "🤔 *I didn't quite understand that...*\n\n";
    $fallbackMessage .= "You said: \"_" . substr($userMessage, 0, 50) . "_\"\n\n";
    $fallbackMessage .= "💡 *Try these commands:*\n";
    $fallbackMessage .= "• Type *hello* - Start conversation\n";
    $fallbackMessage .= "• Type *menu* - Main menu\n";
    $fallbackMessage .= "• Type *help* - All commands\n";
    $fallbackMessage .= "• Type *features* - Platform features\n";
    $fallbackMessage .= "• Type *platform* - Current platform info\n\n";
    $fallbackMessage .= "🤖 *$botName* is ready to help! 🚀";

    $bot->reply($fallbackMessage);
});

// Process the incoming message
$bot->listen();

// Return appropriate response for each platform
if ($platform === 'telegram') {
    // Telegram doesn't require special response
} else {
    // WhatsApp requires 200 OK response
    http_response_code(200);
    echo "OK";
}

?>
