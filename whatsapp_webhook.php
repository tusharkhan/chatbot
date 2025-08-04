<?php

/**
 * WhatsApp Business API Webhook Endpoint
 * 
 * This file handles incoming WhatsApp webhooks from Meta Business API
 * 
 * Setup Instructions:
 * 1. Upload this file to your HTTPS server
 * 2. Set webhook URL in Meta Business Manager
 * 3. Configure your access token and phone number ID
 */

// Set error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors to WhatsApp
ini_set('log_errors', 1);

// Log incoming webhooks for debugging (remove in production)
$input = file_get_contents('php://input');
if ($input) {
    file_put_contents('whatsapp_webhook_debug.log', date('Y-m-d H:i:s') . " - " . $input . "\n", FILE_APPEND);
}

// Include the WhatsApp bot logic
try {
    include_once 'whatsapp_bot_example.php';
} catch (Exception $e) {
    // Log error but don't expose to WhatsApp
    error_log("WhatsApp Bot Error: " . $e->getMessage());
    http_response_code(500);
    echo "Internal Server Error";
}

?>
