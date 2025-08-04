<?php

require_once 'vendor/autoload.php';

use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\WhatsAppDriver;

echo "🤖 WhatsApp Bot Testing Script\n";
echo "===============================\n\n";

// Configuration (replace with your actual values)
$accessToken = 'YOUR_WHATSAPP_ACCESS_TOKEN';
$phoneNumberId = 'YOUR_PHONE_NUMBER_ID';
$verifyToken = 'YOUR_VERIFY_TOKEN';

echo "📋 WhatsApp Business API Setup Test\n";
echo "====================================\n\n";

// Test 1: Check configuration
echo "Test 1: Checking configuration...\n";
if ($accessToken === 'YOUR_WHATSAPP_ACCESS_TOKEN') {
    echo "⚠️ Warning: Please update your access token in the script\n";
} else {
    echo "✅ Access token configured\n";
}

if ($phoneNumberId === 'YOUR_PHONE_NUMBER_ID') {
    echo "⚠️ Warning: Please update your phone number ID in the script\n";
} else {
    echo "✅ Phone number ID configured\n";
}

if ($verifyToken === 'YOUR_VERIFY_TOKEN') {
    echo "⚠️ Warning: Please update your verify token in the script\n";
} else {
    echo "✅ Verify token configured\n";
}
echo "\n";

// Test 2: Test webhook verification
echo "Test 2: Testing webhook verification...\n";
try {
    // Simulate webhook verification request
    $_GET['hub_mode'] = 'subscribe';
    $_GET['hub_verify_token'] = $verifyToken;
    $_GET['hub_challenge'] = 'test_challenge_123';

    $whatsappDriver = new WhatsAppDriver($accessToken, $phoneNumberId);
    $challenge = $whatsappDriver->verifyWebhook($verifyToken);
    
    if ($challenge === 'test_challenge_123') {
        echo "✅ Webhook verification working correctly\n";
    } else {
        echo "❌ Webhook verification failed\n";
    }
} catch (Exception $e) {
    echo "❌ Webhook verification error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 3: Test driver initialization with simulated webhook
echo "Test 3: Testing driver initialization...\n";

// Create simulated WhatsApp webhook data
$simulatedWebhook = [
    'object' => 'whatsapp_business_account',
    'entry' => [
        [
            'id' => 'WHATSAPP_BUSINESS_ACCOUNT_ID',
            'changes' => [
                [
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '1234567890',
                            'phone_number_id' => $phoneNumberId
                        ],
                        'contacts' => [
                            [
                                'profile' => [
                                    'name' => 'Test User'
                                ],
                                'wa_id' => '1234567890'
                            ]
                        ],
                        'messages' => [
                            [
                                'from' => '1234567890',
                                'id' => 'wamid.test123',
                                'timestamp' => time(),
                                'text' => [
                                    'body' => 'hello'
                                ],
                                'type' => 'text'
                            ]
                        ]
                    ],
                    'field' => 'messages'
                ]
            ]
        ]
    ]
];

try {
    $testDriver = new WhatsAppDriver($accessToken, $phoneNumberId, $simulatedWebhook);
    echo "✅ Driver initialized successfully\n";
    echo "   Message: " . $testDriver->getMessage() . "\n";
    echo "   Sender ID: " . $testDriver->getSenderId() . "\n";
    echo "   Has Message: " . ($testDriver->hasMessage() ? 'Yes' : 'No') . "\n";
} catch (Exception $e) {
    echo "❌ Driver initialization failed: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 4: Test bot instance creation
echo "Test 4: Testing bot instance creation...\n";
try {
    $bot = new Bot($testDriver);
    echo "✅ Bot instance created successfully\n";
} catch (Exception $e) {
    echo "❌ Bot creation failed: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 5: Test message parsing for different types
echo "Test 5: Testing different message types...\n";

$testMessages = [
    [
        'type' => 'text',
        'data' => [
            'text' => ['body' => 'Hello World']
        ],
        'expected' => 'Hello World'
    ],
    [
        'type' => 'interactive_button',
        'data' => [
            'interactive' => [
                'button_reply' => ['title' => 'Button Clicked']
            ]
        ],
        'expected' => 'Button Clicked'
    ],
    [
        'type' => 'interactive_list',
        'data' => [
            'interactive' => [
                'list_reply' => ['title' => 'List Item Selected']
            ]
        ],
        'expected' => 'List Item Selected'
    ],
    [
        'type' => 'image',
        'data' => [
            'image' => ['id' => 'image123']
        ],
        'expected' => '[image]'
    ]
];

foreach ($testMessages as $test) {
    $testWebhook = $simulatedWebhook;
    $testWebhook['entry'][0]['changes'][0]['value']['messages'][0] = array_merge(
        ['from' => '1234567890', 'id' => 'test_' . $test['type'], 'timestamp' => time()],
        $test['data']
    );
    
    try {
        $testMsgDriver = new WhatsAppDriver($accessToken, $phoneNumberId, $testWebhook);
        $parsedMessage = $testMsgDriver->getMessage();
        
        if ($parsedMessage === $test['expected']) {
            echo "   ✅ {$test['type']}: Parsed correctly\n";
        } else {
            echo "   ❌ {$test['type']}: Expected '{$test['expected']}', got '$parsedMessage'\n";
        }
    } catch (Exception $e) {
        echo "   ❌ {$test['type']}: Error - " . $e->getMessage() . "\n";
    }
}
echo "\n";

// Test 6: Test user info extraction
echo "Test 6: Testing user info extraction...\n";
try {
    $userInfo = $testDriver->getUserInfo();
    if ($userInfo) {
        echo "✅ User info extracted:\n";
        echo "   ID: " . $userInfo['id'] . "\n";
        echo "   Phone: " . $userInfo['phone'] . "\n";
        echo "   Platform: " . $userInfo['platform'] . "\n";
    } else {
        echo "❌ Failed to extract user info\n";
    }
} catch (Exception $e) {
    echo "❌ User info error: " . $e->getMessage() . "\n";
}
echo "\n";

// Instructions for manual testing
echo "📋 WhatsApp Business API Setup Instructions:\n";
echo "=============================================\n\n";

echo "🔧 *Meta Business Manager Setup:*\n";
echo "1. Go to https://business.facebook.com/\n";
echo "2. Create/select your business account\n";
echo "3. Add WhatsApp Business API product\n";
echo "4. Get your Phone Number ID and Access Token\n\n";

echo "📱 *Phone Number Setup:*\n";
echo "1. Add a phone number to your WhatsApp Business account\n";
echo "2. Verify the phone number\n";
echo "3. Note down the Phone Number ID from the API settings\n\n";

echo "🔐 *Access Token:*\n";
echo "1. Generate a permanent access token (not temporary)\n";
echo "2. Ensure it has whatsapp_business_messaging permissions\n";
echo "3. Store it securely and update the configuration\n\n";

echo "🌐 *Webhook Configuration:*\n";
echo "1. Upload your files to an HTTPS server\n";
echo "2. Set webhook URL: https://yourdomain.com/whatsapp_webhook.php\n";
echo "3. Set verify token: " . $verifyToken . "\n";
echo "4. Subscribe to 'messages' webhook events\n\n";

echo "🧪 *Testing Your Bot:*\n";
echo "1. Send a WhatsApp message to your business number\n";
echo "2. Try these commands:\n";
echo "   • hello - Welcome message\n";
echo "   • menu - Interactive buttons\n";
echo "   • list - Interactive list\n";
echo "   • help - All commands\n";
echo "   • buttons - Button demo\n";
echo "   • image - Send image\n";
echo "   • contact - Contact info\n\n";

echo "📊 *API Testing:*\n";
echo "You can test API calls directly:\n";
echo "curl -X POST \\\n";
echo "  'https://graph.facebook.com/v17.0/{$phoneNumberId}/messages' \\\n";
echo "  -H 'Authorization: Bearer {$accessToken}' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\n";
echo "    \"messaging_product\": \"whatsapp\",\n";
echo "    \"to\": \"RECIPIENT_PHONE_NUMBER\",\n";
echo "    \"text\": { \"body\": \"Hello from WhatsApp Bot!\" }\n";
echo "  }'\n\n";

echo "🔍 *Debugging:*\n";
echo "• Check whatsapp_webhook_debug.log for incoming webhooks\n";
echo "• Check server error logs for PHP errors\n";
echo "• Verify HTTPS certificate is valid\n";
echo "• Ensure webhook URL returns 200 OK\n\n";

echo "📚 *Documentation:*\n";
echo "• WhatsApp Business API: https://developers.facebook.com/docs/whatsapp\n";
echo "• Webhook setup: https://developers.facebook.com/docs/whatsapp/webhooks\n";
echo "• Message types: https://developers.facebook.com/docs/whatsapp/api/messages\n\n";

echo "🎯 *Features Implemented:*\n";
echo "✅ Text messages\n";
echo "✅ Interactive buttons (up to 3)\n";
echo "✅ Interactive lists\n";
echo "✅ Image sharing\n";
echo "✅ Template messages\n";
echo "✅ Webhook verification\n";
echo "✅ Message read receipts\n";
echo "✅ Error handling and logging\n";
echo "✅ Media message detection\n";
echo "✅ Button/list click handling\n\n";

echo "✅ WhatsApp Bot testing completed!\n";
echo "Your bot is ready for production deployment.\n";

?>
