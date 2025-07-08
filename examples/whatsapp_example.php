<?php

require_once __DIR__ . '/../vendor/autoload.php';

use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\WhatsAppDriver;
use TusharKhan\Chatbot\Storage\FileStore;

// Replace with your actual WhatsApp Business API credentials
$accessToken = 'YOUR_ACCESS_TOKEN_HERE';
$phoneNumberId = 'YOUR_PHONE_NUMBER_ID_HERE';

// Initialize the bot
$driver = new WhatsAppDriver($accessToken, $phoneNumberId);
$storage = new FileStore(__DIR__ . '/storage');
$bot = new Bot($driver, $storage);

// Welcome message
$bot->hears(['hello', 'hi', 'start'], function($context) {
    return [
        "👋 Welcome to our WhatsApp Business Bot!",
        "",
        "Here's what I can help you with:",
        "• Type 'menu' - See our services",
        "• Type 'contact' - Get our contact info",
        "• Type 'hours' - Business hours",
        "• Type 'help' - Show this message again"
    ];
});

// Business menu
$bot->hears(['menu', 'services'], function($context) {
    return [
        "🏪 Our Services:",
        "",
        "1️⃣ Product Catalog - Type 'products'",
        "2️⃣ Order Status - Type 'order status'",
        "3️⃣ Customer Support - Type 'support'",
        "4️⃣ Store Locations - Type 'locations'",
        "5️⃣ Special Offers - Type 'offers'"
    ];
});

// Products
$bot->hears('products', function($context) {
    return [
        "📦 Our Product Categories:",
        "",
        "🍕 Food & Beverages",
        "👕 Clothing & Accessories", 
        "📱 Electronics",
        "🏠 Home & Garden",
        "",
        "For detailed catalog, visit our website or contact our sales team."
    ];
});

// Order tracking
$bot->hears('order status', function($context) {
    $context->getConversation()->setState('tracking_order');
    return "📋 Please provide your order number (e.g., ORD12345):";
});

$bot->hears('/^ORD\d+$/i', function($context) {
    $conversation = $context->getConversation();
    
    if ($conversation->isInState('tracking_order')) {
        $orderNumber = strtoupper($context->getMessage());
        $conversation->setState(null);
        
        // In a real application, you would query your database here
        return [
            "📦 Order Status for $orderNumber:",
            "",
            "Status: ✅ Confirmed",
            "Estimated Delivery: Tomorrow, 2-4 PM",
            "Tracking: TR123456789",
            "",
            "You'll receive updates as your order progresses!"
        ];
    }
    
    return null;
});

// Customer support
$bot->hears(['support', 'help me', 'problem'], function($context) {
    $context->getConversation()->setState('support_mode');
    return [
        "🎧 Customer Support",
        "",
        "I'm here to help! Please describe your issue:",
        "• Billing questions",
        "• Technical problems", 
        "• Product inquiries",
        "• Returns & refunds",
        "",
        "Type your question or 'agent' to speak with a human agent."
    ];
});

// Handle support mode
$bot->hears('*', function($context) {
    $conversation = $context->getConversation();
    
    if ($conversation->isInState('support_mode')) {
        $message = strtolower($context->getMessage());
        
        if ($message === 'agent') {
            $conversation->setState(null);
            return [
                "👨‍💼 Connecting you with a human agent...",
                "",
                "A customer service representative will contact you within 30 minutes during business hours.",
                "",
                "Business Hours: Mon-Fri 9AM-6PM, Sat 10AM-4PM"
            ];
        }
        
        $conversation->setState(null);
        return [
            "✅ Thank you for your message. We've recorded your inquiry:",
            "",
            "\"" . $context->getMessage() . "\"",
            "",
            "Our support team will review and respond within 24 hours.",
            "For urgent matters, please call +1-800-SUPPORT"
        ];
    }
    
    return null;
});

// Business hours
$bot->hears(['hours', 'open', 'closed', 'schedule'], function($context) {
    return [
        "🕐 Business Hours:",
        "",
        "Monday - Friday: 9:00 AM - 6:00 PM",
        "Saturday: 10:00 AM - 4:00 PM", 
        "Sunday: Closed",
        "",
        "📍 Time Zone: EST (UTC-5)",
        "📞 Emergency Support: Available 24/7"
    ];
});

// Store locations
$bot->hears(['locations', 'stores', 'address'], function($context) {
    return [
        "📍 Our Store Locations:",
        "",
        "🏪 Main Store",
        "123 Business Ave, City Center",
        "Phone: (555) 123-4567",
        "",
        "🏪 North Branch", 
        "456 North Street, Uptown",
        "Phone: (555) 234-5678",
        "",
        "🏪 South Branch",
        "789 South Road, Downtown", 
        "Phone: (555) 345-6789"
    ];
});

// Special offers
$bot->hears(['offers', 'deals', 'promotions', 'discount'], function($context) {
    return [
        "🎉 Current Special Offers:",
        "",
        "💫 NEW CUSTOMER: 20% off first order",
        "🎂 BIRTHDAY MONTH: 15% off (show ID)",
        "📱 APP DOWNLOAD: Free shipping on orders $50+",
        "👥 REFER FRIEND: $10 credit for each referral",
        "",
        "Use code WHATSAPP10 for 10% off your next online order!",
        "",
        "Valid until end of month. Terms apply."
    ];
});

// Contact information
$bot->hears(['contact', 'phone', 'email'], function($context) {
    return [
        "📞 Contact Information:",
        "",
        "📱 Phone: +1-800-BUSINESS",
        "📧 Email: info@business.com",
        "🌐 Website: www.business.com",
        "💬 WhatsApp: This number!",
        "",
        "🕐 Customer Service Hours:",
        "Mon-Fri: 9AM-6PM | Sat: 10AM-4PM"
    ];
});

// Handle greetings at different times
$bot->hears(['good morning', 'good afternoon', 'good evening'], function($context) {
    $hour = date('H');
    $greeting = '';
    
    if ($hour < 12) {
        $greeting = "Good morning! ☀️";
    } elseif ($hour < 17) {
        $greeting = "Good afternoon! 🌤️";
    } else {
        $greeting = "Good evening! 🌙";
    }
    
    return [
        $greeting,
        "How can I assist you today?",
        "Type 'menu' to see our services."
    ];
});

// Thank you responses
$bot->hears(['thank you', 'thanks', 'thank u'], function($context) {
    return [
        "You're very welcome! 😊",
        "",
        "Is there anything else I can help you with today?",
        "Type 'menu' for our services or 'contact' for our details."
    ];
});

// Goodbye responses
$bot->hears(['bye', 'goodbye', 'see you'], function($context) {
    return [
        "Goodbye! 👋",
        "",
        "Thank you for contacting us today.",
        "Feel free to message us anytime - we're here to help!",
        "",
        "Have a wonderful day! 😊"
    ];
});

// Fallback for unmatched messages
$bot->fallback(function($context) {
    return [
        "🤔 I didn't quite understand that.",
        "",
        "Here are some things you can try:",
        "• Type 'menu' - See our services",
        "• Type 'help' - Get assistance", 
        "• Type 'contact' - Get our contact info",
        "",
        "Or describe what you're looking for and I'll do my best to help!"
    ];
});

// Listen for incoming messages
$bot->listen();

// Webhook verification (WhatsApp requires this)
if (isset($_GET['hub_mode']) && $_GET['hub_mode'] === 'subscribe') {
    if (isset($_GET['hub_verify_token']) && $_GET['hub_verify_token'] === 'YOUR_VERIFY_TOKEN') {
        echo $_GET['hub_challenge'];
        exit;
    }
}
