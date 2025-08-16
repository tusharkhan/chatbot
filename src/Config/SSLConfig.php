<?php

namespace TusharKhan\Chatbot\Config;

/**
 * SSL Configuration Helper for Chatbot
 * 
 * This class provides utilities for configuring SSL certificates
 * for the SlackDriver and other HTTP-based drivers.
 */
class SSLConfig
{
    /**
     * Configure SSL certificate path
     * 
     * @param string $certPath Path to the cacert.pem file
     * @return bool True if configuration was successful
     */
    public static function setCertificatePath(string $certPath): bool
    {
        if (!file_exists($certPath)) {
            throw new \InvalidArgumentException("Certificate file not found: {$certPath}");
        }
        
        if (filesize($certPath) < 1000) {
            throw new \InvalidArgumentException("Certificate file appears to be invalid: {$certPath}");
        }
        
        // Set environment variable
        putenv("SLACK_CACERT_PATH={$certPath}");
        $_ENV['SLACK_CACERT_PATH'] = $certPath;
        
        // Set PHP ini settings
        ini_set('openssl.cafile', $certPath);
        ini_set('curl.cainfo', $certPath);
        
        return true;
    }
    
    /**
     * Download and configure CA certificate
     * 
     * @param string|null $downloadPath Optional path to download the certificate to
     * @return string Path to the downloaded certificate
     * @throws \Exception If download fails
     */
    public static function downloadAndConfigureCertificate(?string $downloadPath = null): string
    {
        if (!$downloadPath) {
            // Use a reasonable default path
            if (function_exists('storage_path')) {
                $downloadPath = storage_path('certs/cacert.pem');
            } else {
                $downloadPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'chatbot-cacert.pem';
            }
        }
        
        // Create directory if needed
        $certDir = dirname($downloadPath);
        if (!file_exists($certDir)) {
            if (!mkdir($certDir, 0755, true)) {
                throw new \Exception("Could not create certificate directory: {$certDir}");
            }
        }
        
        // Download certificate
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
            'http' => [
                'timeout' => 30,
            ]
        ]);
        
        $cacert = file_get_contents('https://curl.se/ca/cacert.pem', false, $context);
        
        if (!$cacert || strlen($cacert) < 1000) {
            throw new \Exception("Failed to download certificate from curl.se");
        }
        
        if (!file_put_contents($downloadPath, $cacert)) {
            throw new \Exception("Failed to write certificate to: {$downloadPath}");
        }
        
        // Configure the downloaded certificate
        self::setCertificatePath($downloadPath);
        
        return $downloadPath;
    }
    
    /**
     * Disable SSL verification for local development
     * WARNING: Only use this for local development, never in production!
     * 
     * @return void
     */
    public static function disableSSLVerification(): void
    {
        // Set environment variables
        putenv('SSL_VERIFY_PEER=0');
        putenv('SSL_VERIFY_HOST=0');
        putenv('CURL_CA_BUNDLE=');
        putenv('SSL_CERT_FILE=');
        
        $_ENV['SSL_VERIFY_PEER'] = '0';
        $_ENV['SSL_VERIFY_HOST'] = '0';
        
        // Set PHP ini settings
        ini_set('openssl.cafile', '');
        ini_set('openssl.capath', '');
        
        // Set default stream context
        stream_context_set_default([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]
        ]);
    }
    
    /**
     * Get helpful configuration instructions
     * 
     * @return string Configuration instructions
     */
    public static function getConfigurationInstructions(): string
    {
        return "SSL Certificate Configuration for Chatbot\n\n" .
            "Method 1 - Environment Variable (Recommended):\n" .
            "Set SLACK_CACERT_PATH in your .env file:\n" .
            "SLACK_CACERT_PATH=/path/to/your/cacert.pem\n\n" .
            
            "Method 2 - Download Certificate Programmatically:\n" .
            "use TusharKhan\\Chatbot\\Config\\SSLConfig;\n" .
            "SSLConfig::downloadAndConfigureCertificate();\n\n" .
            
            "Method 3 - Manual Certificate Setup:\n" .
            "1. Download: curl -o cacert.pem https://curl.se/ca/cacert.pem\n" .
            "2. Place in your project directory\n" .
            "3. Set SLACK_CACERT_PATH to point to this file\n\n" .
            
            "Method 4 - For Local Development Only:\n" .
            "SSLConfig::disableSSLVerification(); // WARNING: Never use in production!\n\n" .
            
            "For Laravel projects, you can add this to your AppServiceProvider boot() method.";
    }
}
