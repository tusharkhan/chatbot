<?php

// Example: Configure SSL for your Laravel Slack webhook route

use TusharKhan\Chatbot\Config\SSLConfig;

Route::post('slack-webhook', function() {
    $request = request();
    
    // Step 1: Configure SSL before creating SlackDriver
    try {
        // Check if certificate path is configured
        if (!getenv('SLACK_CACERT_PATH') && !$_ENV['SLACK_CACERT_PATH'] ?? null) {
            
            if (env('APP_ENV') === 'local' || env('APP_ENV') === 'development') {
                // For local development: auto-download certificate
                $certPath = SSLConfig::downloadAndConfigureCertificate();
            } else {
                // For production: require proper configuration
                throw new Exception(
                    'SSL certificate not configured. Please set SLACK_CACERT_PATH environment variable. ' .
                    'See SSL_CONFIGURATION.md for details.'
                );
            }
        }
    } catch (Exception $e) {
        // Last resort for development only
        if (env('APP_ENV') === 'local') {
            error_log("SSL auto-configuration failed, disabling SSL verification: " . $e->getMessage());
            SSLConfig::disableSSLVerification();
        } else {
            throw $e; // Re-throw for production
        }
    }
    
    // Step 2: Create SlackDriver (SSL should be configured now)
    $driver = new \TusharKhan\Chatbot\Drivers\SlackDriver(
        'xoxb-9085976216148-9078830822707-cSeXPxH71DVahCCLW6IQKRhO', 
        '9f7a8c33ec06b2c008be6767ea9d76e4'
    );
    
    $storagePath = storage_path('chatbot');
    $storage = new FileStore($storagePath);
    $bot = new Bot($driver, $storage);

    $bot->hears(['hello', 'hi'], function (\TusharKhan\Chatbot\Core\Context $context) {
        return 'Hello! How can I help you today?';
    });

    $bot->listen();
    return $request->input('challenge');
    
})->withoutMiddleware(VerifyCsrfToken::class);
