<?php

require_once 'vendor/autoload.php';

use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\WhatsAppDriver;

// WhatsApp Business API Configuration
$accessToken = 'EAAUbRYiGSgUBPOW8N79g8kpz2ZBQWZAIwPZBO5aTNImu0v3q2GsIZALyekuePy7jqPGRDVRmOH8UztGwu3IwXOK4hZCb0aHcsPlUEl0M4M6l6VFRlHSD9gZBHWJn0Az2urJo8sCZBZCBozanYR3CvBA5M5l5Tl5ly6DJ0GfN7OwSYdoIMBwZCTsQlRiZBXXWueuVito3Rq7nZBiBbGjWhjj'; // Replace with your access token
$phoneNumberId = '+8801967209977'; // Replace with your phone number ID
$verifyToken = 'daecaadfc0b1eff1537924fa44eff5ee'; // Replace with your webhook verify token

// Handle webhook verification
if (isset($_GET['hub_mode']) && $_GET['hub_mode'] === 'subscribe') {
    $whatsappDriver = new WhatsAppDriver($accessToken, $phoneNumberId);
    $challenge = $whatsappDriver->verifyWebhook($verifyToken);
    
    if ($challenge) {
        echo $challenge;
        exit;
    } else {
        http_response_code(403);
        echo 'Verification failed';
        exit;
    }
}

// Initialize the WhatsApp driver
$whatsappDriver = new WhatsAppDriver($accessToken, $phoneNumberId);

// Create the bot instance
$bot = new Bot($whatsappDriver);

// Welcome message handler
$bot->hears('hello|hi|hey|start', function ($bot, $message) {
    $userInfo = $bot->getDriver()->getUserInfo();
    $phone = $userInfo['phone'] ?? 'friend';

    $welcomeMessage = "🤖 *Welcome to WhatsApp Chatbot!*\n\n";
    $welcomeMessage .= "Hello! I'm your WhatsApp assistant. Here's what I can help you with:\n\n";
    $welcomeMessage .= "📋 *Available Commands:*\n";
    $welcomeMessage .= "• Type *menu* - Show main menu\n";
    $welcomeMessage .= "• Type *help* - Get help\n";
    $welcomeMessage .= "• Type *buttons* - See button example\n";
    $welcomeMessage .= "• Type *list* - See list example\n";
    $welcomeMessage .= "• Type *image* - Get sample image\n";
    $welcomeMessage .= "• Type *contact* - Contact information\n";
    $welcomeMessage .= "• Type *info* - Your account info\n\n";
    $welcomeMessage .= "Just type any message and I'll respond! 💬";

    $bot->reply($welcomeMessage);
});

// Help command
$bot->hears('help', function ($bot, $message) {
    $helpMessage = "📋 *Help Center*\n\n";
    $helpMessage .= "*Available Commands:*\n";
    $helpMessage .= "• hello/hi/hey - Welcome message\n";
    $helpMessage .= "• menu - Main menu options\n";
    $helpMessage .= "• buttons - Interactive buttons demo\n";
    $helpMessage .= "• list - Interactive list demo\n";
    $helpMessage .= "• image - Send sample image\n";
    $helpMessage .= "• contact - Get contact info\n";
    $helpMessage .= "• info - Your account information\n";
    $helpMessage .= "• ping - Test bot response\n\n";
    $helpMessage .= "*Features:*\n";
    $helpMessage .= "✅ Text messages\n";
    $helpMessage .= "✅ Interactive buttons\n";
    $helpMessage .= "✅ Interactive lists\n";
    $helpMessage .= "✅ Images with captions\n";
    $helpMessage .= "✅ Template messages\n";
    $helpMessage .= "✅ Media handling\n\n";
    $helpMessage .= "Need more help? Type *contact* to reach us!";

    $bot->reply($helpMessage);
});

// Menu with buttons
$bot->hears('menu', function ($bot, $message) {
    $menuText = "🍽️ *Main Menu*\n\nChoose an option below:";
    
    $buttons = [
        ['id' => 'services', 'title' => '🛍️ Services'],
        ['id' => 'about', 'title' => 'ℹ️ About Us'],
        ['id' => 'contact', 'title' => '📞 Contact']
    ];

    $bot->getDriver()->sendButtons($menuText, $buttons);
});

// Button responses
$bot->hears('🛍️ Services|services', function ($bot, $message) {
    $servicesText = "🛍️ *Our Services*\n\nWe offer the following services:";
    
    $sections = [
        [
            'title' => 'Digital Services',
            'rows' => [
                ['id' => 'web_dev', 'title' => 'Web Development', 'description' => 'Custom websites and web apps'],
                ['id' => 'mobile_dev', 'title' => 'Mobile Development', 'description' => 'iOS and Android apps'],
                ['id' => 'chatbot', 'title' => 'Chatbot Development', 'description' => 'WhatsApp and Telegram bots']
            ]
        ],
        [
            'title' => 'Consulting',
            'rows' => [
                ['id' => 'tech_consult', 'title' => 'Tech Consulting', 'description' => 'Technology strategy and advice'],
                ['id' => 'digital_transform', 'title' => 'Digital Transformation', 'description' => 'Modernize your business']
            ]
        ]
    ];

    $bot->getDriver()->sendList($servicesText, 'Choose Service', $sections);
});

$bot->hears('ℹ️ About Us|about', function ($bot, $message) {
    $aboutMessage = "ℹ️ *About Our Company*\n\n";
    $aboutMessage .= "We are a leading technology company specializing in:\n\n";
    $aboutMessage .= "🚀 *Innovation* - Cutting-edge solutions\n";
    $aboutMessage .= "💡 *Creativity* - Unique approaches to problems\n";
    $aboutMessage .= "🤝 *Partnership* - Working closely with clients\n";
    $aboutMessage .= "⚡ *Efficiency* - Fast and reliable delivery\n\n";
    $aboutMessage .= "*Founded:* 2020\n";
    $aboutMessage .= "*Team Size:* 50+ professionals\n";
    $aboutMessage .= "*Clients:* 200+ satisfied customers\n";
    $aboutMessage .= "*Countries:* Operating in 15+ countries\n\n";
    $aboutMessage .= "Ready to work with us? Type *contact* to get in touch!";

    $bot->reply($aboutMessage);
});

// Interactive buttons demo
$bot->hears('buttons', function ($bot, $message) {
    $buttonText = "🔘 *Interactive Buttons Demo*\n\nClick any button below to see how it works:";
    
    $buttons = [
        ['id' => 'like', 'title' => '👍 Like'],
        ['id' => 'share', 'title' => '📤 Share'],
        ['id' => 'more_info', 'title' => 'ℹ️ More Info']
    ];

    $bot->getDriver()->sendButtons($buttonText, $buttons);
});

// Button click responses
$bot->hears('👍 Like|like', function ($bot, $message) {
    $bot->reply("👍 *Thanks for the like!*\n\nWe appreciate your positive feedback! 😊");
});

$bot->hears('📤 Share|share', function ($bot, $message) {
    $bot->reply("📤 *Thank you for sharing!*\n\nSpread the word about our services! 🌟");
});

$bot->hears('ℹ️ More Info|more_info', function ($bot, $message) {
    $infoMessage = "ℹ️ *More Information*\n\n";
    $infoMessage .= "Here are some additional details:\n\n";
    $infoMessage .= "📧 *Email:* info@company.com\n";
    $infoMessage .= "🌐 *Website:* www.company.com\n";
    $infoMessage .= "📱 *Phone:* +1 (555) 123-4567\n";
    $infoMessage .= "📍 *Address:* 123 Tech Street, Digital City\n\n";
    $infoMessage .= "Business Hours: Monday-Friday, 9 AM - 6 PM";

    $bot->reply($infoMessage);
});

// Interactive list demo
$bot->hears('list', function ($bot, $message) {
    $listText = "📋 *Interactive List Demo*\n\nChoose from the options below:";
    
    $sections = [
        [
            'title' => 'Products',
            'rows' => [
                ['id' => 'product_a', 'title' => 'Product A', 'description' => 'Our premium offering'],
                ['id' => 'product_b', 'title' => 'Product B', 'description' => 'Popular choice'],
                ['id' => 'product_c', 'title' => 'Product C', 'description' => 'Budget-friendly option']
            ]
        ],
        [
            'title' => 'Support',
            'rows' => [
                ['id' => 'tech_support', 'title' => 'Technical Support', 'description' => '24/7 technical assistance'],
                ['id' => 'billing', 'title' => 'Billing Support', 'description' => 'Payment and billing queries']
            ]
        ]
    ];

    $bot->getDriver()->sendList($listText, 'Select Option', $sections);
});

// List item responses
$bot->hears('Product A|product_a', function ($bot, $message) {
    $bot->reply("🌟 *Product A - Premium*\n\nOur top-tier solution with all features included!\n\nPrice: $99/month\nFeatures: All premium features\nSupport: 24/7 priority support");
});

$bot->hears('Product B|product_b', function ($bot, $message) {
    $bot->reply("⭐ *Product B - Popular*\n\nOur most popular choice for growing businesses!\n\nPrice: $49/month\nFeatures: Core features + extras\nSupport: Business hours support");
});

$bot->hears('Product C|product_c', function ($bot, $message) {
    $bot->reply("💎 *Product C - Budget*\n\nPerfect for startups and small teams!\n\nPrice: $19/month\nFeatures: Essential features\nSupport: Email support");
});

// Contact information
$bot->hears('📞 Contact|contact', function ($bot, $message) {
    $contactMessage = "📞 *Contact Information*\n\n";
    $contactMessage .= "Get in touch with us:\n\n";
    $contactMessage .= "📧 *Email:* support@company.com\n";
    $contactMessage .= "📱 *Phone:* +1 (555) 123-4567\n";
    $contactMessage .= "🌐 *Website:* www.company.com\n";
    $contactMessage .= "💬 *WhatsApp:* +1 (555) 987-6543\n";
    $contactMessage .= "📍 *Address:* 123 Business Ave, Suite 100\n";
    $contactMessage .= "🕒 *Hours:* Mon-Fri 9AM-6PM EST\n\n";
    $contactMessage .= "🚀 *Quick Actions:*\n";
    $contactMessage .= "• Type *quote* for a free quote\n";
    $contactMessage .= "• Type *demo* to schedule a demo\n";
    $contactMessage .= "• Type *support* for technical help";

    $bot->reply($contactMessage);
});

// Image sending
$bot->hears('image|photo|picture', function ($bot, $message) {
    $imageUrl = 'https://picsum.photos/800/600?random=' . time();
    $caption = "📸 *Sample Image*\n\nThis is a random sample image to demonstrate image sharing capabilities!\n\nOur bot can send:\n• Images with captions\n• Documents\n• Audio files\n• Video files\n\nType *help* to see more features!";

    $success = $bot->getDriver()->sendImage($imageUrl, $caption);
    
    if (!$success) {
        $bot->reply("❌ Sorry, couldn't send the image right now. Please try again later.");
    }
});

// User info
$bot->hears('info|profile', function ($bot, $message) {
    $userInfo = $bot->getDriver()->getUserInfo();
    
    $infoMessage = "👤 *Your Information*\n\n";
    $infoMessage .= "📱 *Phone:* " . $userInfo['phone'] . "\n";
    $infoMessage .= "💬 *Platform:* " . ucfirst($userInfo['platform']) . "\n";
    $infoMessage .= "🆔 *ID:* " . substr($userInfo['id'], 0, 10) . "...\n";
    $infoMessage .= "🕒 *Session:* Active\n\n";
    $infoMessage .= "Need to update your info? Contact our support team!";

    $bot->reply($infoMessage);
});

// Ping test
$bot->hears('ping|test', function ($bot, $message) {
    $bot->reply("🏓 *Pong!*\n\n✅ Bot is working perfectly!\n\n🕒 Response time: < 1 second\n📡 Connection: Stable\n🤖 Status: Online");
});

// Quote request
$bot->hears('quote|pricing|price', function ($bot, $message) {
    $quoteText = "💰 *Get a Free Quote*\n\nWe'd love to help with your project!";
    
    $buttons = [
        ['id' => 'web_quote', 'title' => '🌐 Web Dev Quote'],
        ['id' => 'mobile_quote', 'title' => '📱 Mobile Quote'],
        ['id' => 'custom_quote', 'title' => '🎯 Custom Quote']
    ];

    $bot->getDriver()->sendButtons($quoteText, $buttons);
});

// Quote responses
$bot->hears('🌐 Web Dev Quote|web_quote', function ($bot, $message) {
    $bot->reply("🌐 *Web Development Quote*\n\nGreat choice! Please provide:\n\n1. Type of website needed\n2. Number of pages\n3. Special features required\n4. Timeline\n\nSend this info and we'll get back to you within 24 hours!");
});

$bot->hears('📱 Mobile Quote|mobile_quote', function ($bot, $message) {
    $bot->reply("📱 *Mobile App Quote*\n\nExcellent! Please tell us:\n\n1. Platform (iOS/Android/Both)\n2. App category/type\n3. Key features needed\n4. Target audience\n\nShare these details for a detailed quote!");
});

$bot->hears('🎯 Custom Quote|custom_quote', function ($bot, $message) {
    $bot->reply("🎯 *Custom Solution Quote*\n\nPerfect! For custom projects, please describe:\n\n1. Your business challenge\n2. Desired solution\n3. Budget range\n4. Timeline\n\nOur team will create a tailored proposal for you!");
});

// Demo scheduling
$bot->hears('demo|demonstration|show', function ($bot, $message) {
    $demoMessage = "🎬 *Schedule a Demo*\n\n";
    $demoMessage .= "See our solutions in action!\n\n";
    $demoMessage .= "📅 *Available Slots:*\n";
    $demoMessage .= "• Weekdays: 10 AM - 4 PM EST\n";
    $demoMessage .= "• Duration: 30 minutes\n";
    $demoMessage .= "• Format: Video call or in-person\n\n";
    $demoMessage .= "To schedule:\n";
    $demoMessage .= "📧 Email: demos@company.com\n";
    $demoMessage .= "📱 Call: +1 (555) 123-4567\n";
    $demoMessage .= "💬 Or reply with your preferred date/time!";

    $bot->reply($demoMessage);
});

// Support request
$bot->hears('support|help me|issue|problem', function ($bot, $message) {
    $supportText = "🆘 *Technical Support*\n\nHow can we help you today?";
    
    $sections = [
        [
            'title' => 'Common Issues',
            'rows' => [
                ['id' => 'login_issue', 'title' => 'Login Problems', 'description' => 'Cannot access account'],
                ['id' => 'payment_issue', 'title' => 'Payment Issues', 'description' => 'Billing and payment help'],
                ['id' => 'feature_help', 'title' => 'Feature Help', 'description' => 'How to use features']
            ]
        ],
        [
            'title' => 'Contact Support',
            'rows' => [
                ['id' => 'live_chat', 'title' => 'Live Chat', 'description' => 'Chat with support agent'],
                ['id' => 'ticket', 'title' => 'Create Ticket', 'description' => 'Submit support ticket']
            ]
        ]
    ];

    $bot->getDriver()->sendList($supportText, 'Get Help', $sections);
});

// Handle greetings
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

// Handle thank you
$bot->hears('thank you|thanks|appreciate', function ($bot, $message) {
    $bot->reply("😊 *You're very welcome!*\n\nI'm happy to help! If you need anything else, just let me know.\n\nType *menu* to see all available options! 🚀");
});

// Handle goodbye
$bot->hears('bye|goodbye|see you|farewell', function ($bot, $message) {
    $goodbyes = [
        "👋 Goodbye! Have a wonderful day!",
        "🌟 See you later! Feel free to message anytime!",
        "✨ Take care! I'm here whenever you need help!",
        "🚀 Until next time! Thanks for chatting!"
    ];

    $randomGoodbye = $goodbyes[array_rand($goodbyes)];
    $bot->reply($randomGoodbye);
});

// Fallback for unmatched messages
$bot->fallback(function ($bot, $message) {
    $userMessage = $bot->getDriver()->getMessage();
    
    $fallbackMessage = "🤔 *Interesting message!*\n\n";
    $fallbackMessage .= "I received: \"_" . substr($userMessage, 0, 50) . "_\"\n\n";
    $fallbackMessage .= "I'm still learning, but I can help you with:\n\n";
    $fallbackMessage .= "📋 Type *menu* - Main menu\n";
    $fallbackMessage .= "❓ Type *help* - All commands\n";
    $fallbackMessage .= "🔘 Type *buttons* - Interactive buttons\n";
    $fallbackMessage .= "📋 Type *list* - Interactive lists\n";
    $fallbackMessage .= "📞 Type *contact* - Get in touch\n\n";
    $fallbackMessage .= "Or just say *hello* to start over! 😊";

    $bot->reply($fallbackMessage);
});

// Process the incoming message
$bot->listen();

// Always return 200 OK to WhatsApp
http_response_code(200);
echo "OK";

?>
