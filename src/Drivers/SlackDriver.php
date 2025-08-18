<?php

namespace TusharKhan\Chatbot\Drivers;

use JoliCode\Slack\ClientFactory;
use TusharKhan\Chatbot\Contracts\DriverInterface;
use JoliCode\Slack\Api\Client;
use Illuminate\Support\Facades\Log;
use JoliCode\Slack\Exception\SlackErrorResponse;
use Symfony\Component\HttpClient\HttpClient;

class SlackDriver implements DriverInterface
{
    private Client $client;
    private ?array $event = null;
    private ?string $message = null;
    private ?string $senderId = null;
    private ?string $channelId = null;
    private array $data = [];
    private string $botToken;
    private ?string $signingSecret = null;
    private bool $isValidRequest = false;

    public function __construct(string $botToken, ?string $signingSecret = null, ?array $eventData = null)
    {
        $this->botToken = $botToken;
        $this->signingSecret = $signingSecret;
        
        // Configure SSL for local development environments
        // This is a workaround for common SSL issues in Laragon, XAMPP, and Windows environments
        // $this->configureSSLForLocalDevelopment();
        
        // Try to create client with custom configuration for local development
        // try {
        //     $this->client = $this->createSlackClientWithSSLConfig($this->botToken);
        // } catch (\Exception $e) {
            // Fallback to regular client creation
            $this->client = ClientFactory::create($this->botToken);
        // }

        if ($eventData) {
            $this->parseEventData($eventData);
        } else {
            $this->parseWebhookInput();
        }
    }

    /**
     * Parse webhook input from Slack Events API
     */
    private function parseWebhookInput(): void
    {
        $input = file_get_contents('php://input');
        if ($input === false || empty($input)) {
            return;
        }

        $eventData = json_decode($input, true);
        if (!$eventData) {
            return;
        }

        // Verify webhook signature if signing secret is provided
        if ($this->signingSecret && !$this->verifyWebhookSignature($input)) {
            return;
        }

        $this->parseEventData($eventData);
    }

    /**
     * Configure SSL settings for local development environments
     */
    private function configureSSLForLocalDevelopment(): void
    {
        // Check if we're in a local development environment
        $isLocalDevelopment = (
            PHP_OS_FAMILY === 'Windows' ||
            ($_SERVER['SERVER_NAME'] ?? '') === 'localhost' ||
            ($_SERVER['SERVER_NAME'] ?? '') === '127.0.0.1' ||
            strpos(strtolower($_SERVER['HTTP_HOST'] ?? ''), 'localhost') !== false ||
            strpos(strtolower($_SERVER['HTTP_HOST'] ?? ''), 'ngrok') !== false ||
            strpos(strtolower($_SERVER['HTTP_HOST'] ?? ''), '.local') !== false ||
            // Detect PHP built-in development server
            strpos($_SERVER['SERVER_SOFTWARE'] ?? '', 'PHP') === 0 ||
            // Detect local IP addresses
            strpos($_SERVER['SERVER_NAME'] ?? '', '127.0.0.1') !== false ||
            strpos($_SERVER['SERVER_NAME'] ?? '', '::1') !== false
        );

        if ($isLocalDevelopment) {
            // Download and set cacert.pem for proper SSL if it doesn't exist
            $this->ensureCACertExists();
            
            // Set environment variables to disable SSL verification for HTTP clients
            // This affects Symfony HttpClient used by JoliCode Slack
            $_ENV['HTTPLUG_SSL_VERIFICATION'] = '0';
            $_ENV['SSL_VERIFY_PEER'] = '0';
            $_ENV['SSL_VERIFY_HOST'] = '0';
            $_ENV['CURL_CA_BUNDLE'] = '';
            $_ENV['SSL_CERT_FILE'] = '';
            $_ENV['SSL_CERT_DIR'] = '';
            
            // Set cURL options globally
            if (function_exists('curl_setopt')) {
                $GLOBALS['_curl_ssl_options'] = [
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_CAINFO => '',
                    CURLOPT_CAPATH => '',
                ];
            }
            
            // Set OpenSSL configuration
            ini_set('openssl.cafile', '');
            ini_set('openssl.capath', '');
            
            // Override default stream context for all SSL connections
            $context = stream_context_get_default();
            stream_context_set_option($context, 'ssl', 'verify_peer', false);
            stream_context_set_option($context, 'ssl', 'verify_peer_name', false);
            stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
            stream_context_set_option($context, 'ssl', 'SNI_enabled', false);
            
            // For Symfony HttpClient specifically
            stream_context_set_option($context, 'http', 'method', 'POST');
            stream_context_set_option($context, 'http', 'timeout', 30);
        }
    }

    /**
     * Ensure CA certificate file exists or disable SSL verification
     */
    private function ensureCACertExists(): void
    {
        // Try to find common certificate paths for different environments
        $possibleCertPaths = [
            // User-defined environment variable (highest priority)
            getenv('SLACK_CACERT_PATH'),
            $_ENV['SLACK_CACERT_PATH'] ?? null,
            
            // Laravel-specific paths (if functions exist)
            function_exists('\\base_path') ? \base_path('cacert.pem') : null,
            function_exists('\\storage_path') ? \storage_path('certs/cacert.pem') : null,
            
            // Cross-platform development server paths
            $this->detectLocalDevelopmentCertPath(),
            
            // Current working directory
            getcwd() . DIRECTORY_SEPARATOR . 'cacert.pem',
            
            // PHP's default certificate file setting
            ini_get('openssl.cafile'),
            ini_get('curl.cainfo'),
        ];
        
        // Remove empty/null paths
        $possibleCertPaths = array_filter($possibleCertPaths);
        
        $certFound = false;
        $certPath = null;
        
        // Check if any certificate file exists
        foreach ($possibleCertPaths as $path) {
            if ($path && file_exists($path) && filesize($path) > 1000) {
                $certFound = true;
                $certPath = $path;
                break;
            }
        }
        
        if (!$certFound) {
            // Try to download certificate to a reasonable location
            $certPath = $this->downloadCACertificate();
            
            if (!$certPath) {
                // If we can't find or download a certificate, provide helpful error message
                $this->logCertificateError();
            }
        }
        
        // Set the certificate path if found
        if ($certPath && file_exists($certPath)) {
            ini_set('openssl.cafile', $certPath);
            ini_set('curl.cainfo', $certPath);
            putenv('SSL_CERT_FILE=' . $certPath);
        }
    }
    
    /**
     * Detect certificate paths for common local development environments
     * Works across Windows, macOS, and Linux
     */
    private function detectLocalDevelopmentCertPath(): ?string
    {
        $possiblePaths = [];
        
        // Windows-specific paths
        if (PHP_OS_FAMILY === 'Windows') {
            $possiblePaths = array_merge($possiblePaths, [
                // Laragon paths
                'D:\laragon\etc\ssl\cacert.pem',
                'C:\laragon\etc\ssl\cacert.pem',
                
                // XAMPP paths
                'C:\xampp\apache\conf\ssl.crt\server.crt',
                'C:\xampp\php\extras\ssl\cacert.pem',
                
                // WAMP paths
                'C:\wamp64\bin\php\php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.*\extras\ssl\cacert.pem',
                
                // System paths
                'C:\Windows\System32\cacert.pem',
            ]);
            
            // Also try to detect from environment variables
            $laragonRoot = getenv('LARAGON_ROOT') ?: $_ENV['LARAGON_ROOT'] ?? null;
            if ($laragonRoot) {
                $possiblePaths[] = $laragonRoot . '\etc\ssl\cacert.pem';
            }
            
            $xamppRoot = getenv('XAMPP_ROOT') ?: $_ENV['XAMPP_ROOT'] ?? null;
            if ($xamppRoot) {
                $possiblePaths[] = $xamppRoot . '\apache\conf\ssl.crt\server.crt';
            }
        }
        
        // macOS-specific paths
        elseif (PHP_OS_FAMILY === 'Darwin') {
            $possiblePaths = array_merge($possiblePaths, [
                // Homebrew paths
                '/usr/local/etc/ca-certificates/cert.pem',
                '/usr/local/etc/openssl/cert.pem',
                '/opt/homebrew/etc/ca-certificates/cert.pem',
                '/opt/homebrew/etc/openssl/cert.pem',
                
                // MAMP paths
                '/Applications/MAMP/conf/apache/ssl.crt/server.crt',
                '/Applications/MAMP/Library/OpenSSL/certs/cacert.pem',
                
                // Valet paths
                $_SERVER['HOME'] . '/.config/valet/CA/LaravelValetCASelfSigned.pem',
                
                // System paths
                '/etc/ssl/cert.pem',
                '/usr/local/share/ca-certificates/',
                '/System/Library/OpenSSL/certs/cert.pem',
            ]);
        }
        
        // Linux-specific paths
        elseif (PHP_OS_FAMILY === 'Linux') {
            $possiblePaths = array_merge($possiblePaths, [
                // Common Linux paths
                '/etc/ssl/certs/ca-certificates.crt',
                '/etc/pki/tls/certs/ca-bundle.crt',
                '/usr/share/ssl/certs/ca-bundle.crt',
                '/usr/local/share/certs/ca-root-nss.crt',
                '/etc/ssl/cert.pem',
                
                // Docker/container paths
                '/usr/local/share/ca-certificates/',
                '/etc/ssl/certs/',
                
                // Development server paths (like Homestead, Docker containers)
                '/vagrant/ssl/cacert.pem',
                '/var/www/ssl/cacert.pem',
            ]);
        }
        
        // Common cross-platform paths
        $homeDir = $this->getHomeDirectory();
        if ($homeDir) {
            $possiblePaths = array_merge($possiblePaths, [
                // User home directory
                $homeDir . DIRECTORY_SEPARATOR . '.ssl' . DIRECTORY_SEPARATOR . 'cacert.pem',
                $homeDir . DIRECTORY_SEPARATOR . 'cacert.pem',
            ]);
        }
        
        $possiblePaths = array_merge($possiblePaths, [
            // Project-specific paths
            dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'cacert.pem',
            dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'cacert.pem',
        ]);
        
        foreach ($possiblePaths as $path) {
            if ($path && file_exists($path) && is_readable($path) && filesize($path) > 1000) {
                return $path;
            }
        }
        
        return null;
    }
    
    /**
     * Get home directory in a cross-platform way
     * Handles both CLI and web environments
     */
    private function getHomeDirectory(): ?string
    {
        // Try different methods to get home directory
        $homeDir = null;
        
        // Method 1: Environment variables (works in CLI)
        $homeDir = $_SERVER['HOME'] ?? $_ENV['HOME'] ?? getenv('HOME') ?? null;
        
        // Method 2: Windows USERPROFILE (works in CLI and sometimes web)
        if (!$homeDir) {
            $homeDir = $_SERVER['USERPROFILE'] ?? $_ENV['USERPROFILE'] ?? getenv('USERPROFILE') ?? null;
        }
        
        // Method 3: Use system-specific methods
        if (!$homeDir) {
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows: Try HOMEDRIVE + HOMEPATH
                $homeDrive = $_SERVER['HOMEDRIVE'] ?? $_ENV['HOMEDRIVE'] ?? getenv('HOMEDRIVE') ?? 'C:';
                $homePath = $_SERVER['HOMEPATH'] ?? $_ENV['HOMEPATH'] ?? getenv('HOMEPATH');
                if ($homePath) {
                    $homeDir = $homeDrive . $homePath;
                }
            } else {
                // Unix-like systems: Try to get from /etc/passwd or common paths
                $user = $_SERVER['USER'] ?? $_ENV['USER'] ?? getenv('USER') ?? null;
                
                // Try to get user from posix functions if available
                if (!$user && function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
                    $pwuid = posix_getpwuid(posix_geteuid());
                    $user = $pwuid['name'] ?? null;
                }
                
                if ($user) {
                    $homeDir = '/home/' . $user;
                    // macOS users are typically in /Users/
                    if (PHP_OS_FAMILY === 'Darwin') {
                        $homeDir = '/Users/' . $user;
                    }
                }
            }
        }
        
        // Method 4: Try to execute system command as last resort (only in CLI or if safe)
        if (!$homeDir && php_sapi_name() === 'cli') {
            if (PHP_OS_FAMILY === 'Windows') {
                $homeDir = trim(shell_exec('echo %USERPROFILE%') ?: '');
            } else {
                $homeDir = trim(shell_exec('echo $HOME') ?: '');
            }
        }
        
        // Validate the home directory exists
        if ($homeDir && is_dir($homeDir)) {
            return $homeDir;
        }
        
        return null;
    }
    
    /**
     * Download CA certificate to a safe location
     */
    private function downloadCACertificate(): ?string
    {
        // Determine a good location to store the certificate
        $certDir = $this->getCertificateDirectory();
        
        if (!$certDir) {
            return null;
        }
        
        $certPath = $certDir . DIRECTORY_SEPARATOR . 'cacert.pem';
        
        // Create directory if it doesn't exist
        if (!file_exists($certDir)) {
            if (!@mkdir($certDir, 0755, true)) {
                return null;
            }
        }
        
        // Download certificate
        try {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]);
            
            $cacert = @file_get_contents('https://curl.se/ca/cacert.pem', false, $context);
            
            if ($cacert && strlen($cacert) > 1000) {
                if (@file_put_contents($certPath, $cacert)) {
                    return $certPath;
                }
            }
        } catch (\Exception $e) {
            // Download failed, we'll rely on SSL bypass
        }
        
        return null;
    }
    
    /**
     * Get appropriate directory for storing certificates across platforms
     */
    private function getCertificateDirectory(): ?string
    {
        // Priority order for certificate storage
        $possibleDirs = [
            // Laravel storage (if available)
            $this->getLaravelStoragePath(),
            
            // Cross-platform user directories
            $this->getUserCertificateDirectory(),
            
            // System temp directory
            sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'chatbot-certs',
            
            // Current working directory
            getcwd() . DIRECTORY_SEPARATOR . 'certs',
        ];
        
        foreach ($possibleDirs as $dir) {
            if ($dir && (file_exists($dir) || is_writable(dirname($dir)))) {
                return $dir;
            }
        }
        
        return null;
    }
    
    /**
     * Get Laravel storage path if available
     */
    private function getLaravelStoragePath(): ?string
    {
        // Try to detect Laravel installation by looking for typical Laravel structure
        $possibleLaravelPaths = [
            getcwd() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'certs',
            dirname(getcwd()) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'certs',
        ];
        
        foreach ($possibleLaravelPaths as $path) {
            $storageDir = dirname($path);
            if (file_exists($storageDir) && is_dir($storageDir)) {
                return $path;
            }
        }
        
        return null;
    }
    
    /**
     * Get user-specific certificate directory based on OS
     */
    private function getUserCertificateDirectory(): ?string
    {
        $homeDir = $this->getHomeDirectory();
        
        if (!$homeDir) {
            return null;
        }
        
        // OS-specific user certificate directories
        if (PHP_OS_FAMILY === 'Windows') {
            return $homeDir . DIRECTORY_SEPARATOR . 'AppData' . DIRECTORY_SEPARATOR . 'Local' . DIRECTORY_SEPARATOR . 'chatbot-certs';
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            return $homeDir . DIRECTORY_SEPARATOR . 'Library' . DIRECTORY_SEPARATOR . 'Application Support' . DIRECTORY_SEPARATOR . 'chatbot-certs';
        } else {
            // Linux and other Unix-like systems
            return $homeDir . DIRECTORY_SEPARATOR . '.local' . DIRECTORY_SEPARATOR . 'share' . DIRECTORY_SEPARATOR . 'chatbot-certs';
        }
    }
    
    /**
     * Log helpful error message about certificate issues
     */
    private function logCertificateError(): void
    {
        $os = PHP_OS_FAMILY;
        $homeDir = $this->getHomeDirectory() ?? 'your-home-directory';
        
        $errorMessage = "SSL Certificate Configuration Required for Slack API\n\n" .
            "The SlackDriver couldn't find or download SSL certificates. To fix this:\n\n" .
            "Option 1 - Set certificate path via environment variable:\n" .
            "   SLACK_CACERT_PATH=/path/to/your/cacert.pem\n\n" .
            "Option 2 - Download certificate manually:\n";
            
        // OS-specific download instructions
        if ($os === 'Windows') {
            $errorMessage .= "   curl -o cacert.pem https://curl.se/ca/cacert.pem\n" .
                "   Or use PowerShell: Invoke-WebRequest -Uri https://curl.se/ca/cacert.pem -OutFile cacert.pem\n";
        } elseif ($os === 'Darwin') {
            $errorMessage .= "   curl -o cacert.pem https://curl.se/ca/cacert.pem\n" .
                "   Or use: wget -O cacert.pem https://curl.se/ca/cacert.pem\n";
        } else {
            $errorMessage .= "   curl -o cacert.pem https://curl.se/ca/cacert.pem\n" .
                "   Or use: wget -O cacert.pem https://curl.se/ca/cacert.pem\n";
        }
        
        $errorMessage .= "   Then set SLACK_CACERT_PATH to point to this file\n\n" .
            "Option 3 - For Laravel, add to your .env file:\n" .
            "   SLACK_CACERT_PATH=storage/certs/cacert.pem\n\n";
            
        // OS-specific certificate locations
        if ($os === 'Windows') {
            $errorMessage .= "Option 4 - Common Windows certificate locations:\n" .
                "   - Laragon: LARAGON_ROOT\\etc\\ssl\\cacert.pem\n" .
                "   - XAMPP: C:\\xampp\\php\\extras\\ssl\\cacert.pem\n" .
                "   - User directory: {$homeDir}\\AppData\\Local\\chatbot-certs\\cacert.pem\n\n";
        } elseif ($os === 'Darwin') {
            $errorMessage .= "Option 4 - Common macOS certificate locations:\n" .
                "   - Homebrew: /usr/local/etc/openssl/cert.pem\n" .
                "   - System: /etc/ssl/cert.pem\n" .
                "   - User directory: {$homeDir}/Library/Application Support/chatbot-certs/cacert.pem\n\n";
        } else {
            $errorMessage .= "Option 4 - Common Linux certificate locations:\n" .
                "   - System: /etc/ssl/certs/ca-certificates.crt\n" .
                "   - System: /etc/pki/tls/certs/ca-bundle.crt\n" .
                "   - User directory: {$homeDir}/.local/share/chatbot-certs/cacert.pem\n\n";
        }
        
        $errorMessage .= "Option 5 - For production, configure your server's SSL certificates properly\n\n" .
            "Note: SSL verification will be disabled for local development, but this is not recommended for production.";
        
        // Use appropriate logging method
        if (class_exists('\\Illuminate\\Support\\Facades\\Log')) {
            Log::warning('SlackDriver SSL Configuration', ['message' => $errorMessage]);
        } elseif (function_exists('error_log')) {
            error_log("SlackDriver SSL Warning: " . $errorMessage);
        }
    }

    /**
     * Create Slack client with SSL configuration for local development
     */
    private function createSlackClientWithSSLConfig(string $botToken): Client
    {
        // Check if we're in local development
        $isLocalDevelopment = (
            PHP_OS_FAMILY === 'Windows' ||
            strpos(strtolower($_SERVER['HTTP_HOST'] ?? ''), 'localhost') !== false ||
            strpos(strtolower($_SERVER['HTTP_HOST'] ?? ''), 'ngrok') !== false ||
            strpos($_SERVER['SERVER_SOFTWARE'] ?? '', 'PHP') === 0 ||
            ($_SERVER['SERVER_NAME'] ?? '') === '127.0.0.1' ||
            ($_SERVER['SERVER_NAME'] ?? '') === 'localhost'
        );
        
        if ($isLocalDevelopment) {
            // Set PHP's default SSL context to disable verification
            // This should affect all HTTP libraries including Symfony HttpClient
            $sslContext = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                    'SNI_enabled' => false,
                ],
                'http' => [
                    'timeout' => 30,
                ]
            ];
            
            // Set default stream context globally
            $context = stream_context_create($sslContext);
            stream_context_set_default($sslContext);
            
            // Set cURL default options for all cURL requests
            if (extension_loaded('curl')) {
                // These will be used by any library that uses cURL
                $GLOBALS['http_context_options'] = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ]
                ];
            }
        }
        
        // Create regular client - SSL settings should be applied globally
        return ClientFactory::create($botToken);
    }

    /**
     * Verify Slack webhook signature
     */
    private function verifyWebhookSignature(string $body): bool
    {
        $timestamp = $_SERVER['HTTP_X_SLACK_REQUEST_TIMESTAMP'] ?? '';
        $signature = $_SERVER['HTTP_X_SLACK_SIGNATURE'] ?? '';

        if (empty($timestamp) || empty($signature)) {
            return false;
        }

        // Check if request is older than 5 minutes
        if (abs(time() - intval($timestamp)) > 300) {
            return false;
        }

        $baseString = 'v0:' . $timestamp . ':' . $body;
        $expectedSignature = 'v0=' . hash_hmac('sha256', $baseString, $this->signingSecret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Parse event data from Slack
     */
    private function parseEventData(array $eventData): void
    {
        $this->data = $eventData;

        // Handle URL verification challenge
        if (isset($eventData['type']) && $eventData['type'] === 'url_verification') {
            if (isset($eventData['challenge'])) {
                header('Content-Type: text/plain');
                echo $eventData['challenge'];
                exit;
            }
            return;
        }

        // Handle event callback
        if (isset($eventData['type']) && $eventData['type'] === 'event_callback') {
            $this->isValidRequest = true;
            $this->event = $eventData['event'] ?? null;

            if ($this->event) {
                $this->parseEvent($this->event);
            }
        }

        // Handle slash commands
        if (isset($eventData['command'])) {
            $this->isValidRequest = true;
            $this->parseSlashCommand($eventData);
        }

        // Handle interactive components (buttons, selects, etc.)
        if (isset($eventData['payload'])) {
            $this->isValidRequest = true;
            $payload = json_decode($eventData['payload'], true);
            $this->parseInteractivePayload($payload);
        }
    }

    /**
     * Parse Slack event
     */
    private function parseEvent(array $event): void
    {
        $eventType = $event['type'] ?? '';

        switch ($eventType) {
            case 'message':
                // Handle regular messages
                if (!isset($event['bot_id'])) { // Ignore bot messages
                    $this->message = $event['text'] ?? '';
                    $this->senderId = $event['user'] ?? '';
                    $this->channelId = $event['channel'] ?? '';
                }
                break;

            case 'app_mention':
                // Handle mentions
                $this->message = $event['text'] ?? '';
                $this->senderId = $event['user'] ?? '';
                $this->channelId = $event['channel'] ?? '';
                break;

            case 'reaction_added':
            case 'reaction_removed':
                // Handle reactions
                $this->senderId = $event['user'] ?? '';
                $this->channelId = $event['item']['channel'] ?? '';
                $this->message = $eventType . ':' . ($event['reaction'] ?? '');
                break;

            default:
                // Handle other event types
                $this->senderId = $event['user'] ?? '';
                $this->channelId = $event['channel'] ?? '';
                $this->message = $eventType;
                break;
        }
    }

    /**
     * Parse slash command
     */
    private function parseSlashCommand(array $commandData): void
    {
        $this->message = $commandData['text'] ?? '';
        $this->senderId = $commandData['user_id'] ?? '';
        $this->channelId = $commandData['channel_id'] ?? '';

        // Prepend command name to message
        if (isset($commandData['command'])) {
            $this->message = $commandData['command'] . ' ' . $this->message;
        }
    }

    /**
     * Parse interactive payload (buttons, selects, etc.)
     */
    private function parseInteractivePayload(array $payload): void
    {
        $this->senderId = $payload['user']['id'] ?? '';
        $this->channelId = $payload['channel']['id'] ?? '';

        // Handle different types of interactions
        $type = $payload['type'] ?? '';
        switch ($type) {
            case 'block_actions':
                $actions = $payload['actions'] ?? [];
                if (!empty($actions)) {
                    $action = $actions[0];
                    $this->message = 'action:' . ($action['action_id'] ?? '') . ':' . ($action['value'] ?? '');
                }
                break;

            case 'view_submission':
                $this->message = 'form_submission:' . ($payload['view']['callback_id'] ?? '');
                break;

            default:
                $this->message = 'interaction:' . $type;
                break;
        }
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getSenderId(): ?string
    {
        return $this->senderId;
    }

    public function getChannelId(): ?string
    {
        return $this->channelId;
    }

    public function sendMessage(string $message, ?string $senderId = null): bool
    {
        try {
            // Determine the channel to send to
            $channel = $senderId ?: $this->channelId;

            if (!$channel) {
                return false;
            }

            $params = [
                'channel' => $channel,
                'text' => $message,
            ];
            
            $response = $this->client->chatPostMessage($params);
            
            return $response->getOk();
            
        } catch (SlackErrorResponse $e) {
            Log::error('SlackDriver: Slack API Error', [
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('SlackDriver: General Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Send a rich message with blocks
     */
    public function sendRichMessage(string $text, array $blocks = [], ?string $channel = null): bool
    {
        try {
            $channel = $channel ?: $this->channelId;

            if (!$channel) {
                return false;
            }

            $params = [
                'channel' => $channel,
                'text' => $text,
            ];

            if (!empty($blocks)) {
                $params['blocks'] = json_encode($blocks);
            }

            $response = $this->client->chatPostMessage($params);
            return $response->getOk();
        } catch (SlackErrorResponse $e) {
            error_log('Slack API Error: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            error_log('Slack Driver Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send an ephemeral message (only visible to specific user)
     */
    public function sendEphemeralMessage(string $message, string $user, ?string $channel = null): bool
    {
        try {
            $channel = $channel ?: $this->channelId;

            if (!$channel) {
                return false;
            }

            $response = $this->client->chatPostEphemeral([
                'channel' => $channel,
                'text' => $message,
                'user' => $user,
            ]);

            return $response->getOk();
        } catch (SlackErrorResponse $e) {
            error_log('Slack API Error: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            error_log('Slack Driver Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update a message
     */
    public function updateMessage(string $timestamp, string $message, ?string $channel = null): bool
    {
        try {
            $channel = $channel ?: $this->channelId;

            if (!$channel) {
                return false;
            }

            $response = $this->client->chatUpdate([
                'channel' => $channel,
                'ts' => $timestamp,
                'text' => $message,
            ]);

            return $response->getOk();
        } catch (SlackErrorResponse $e) {
            error_log('Slack API Error: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            error_log('Slack Driver Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a message
     */
    public function deleteMessage(string $timestamp, ?string $channel = null): bool
    {
        try {
            $channel = $channel ?: $this->channelId;

            if (!$channel) {
                return false;
            }

            $response = $this->client->chatDelete([
                'channel' => $channel,
                'ts' => $timestamp,
            ]);

            return $response->getOk();
        } catch (SlackErrorResponse $e) {
            error_log('Slack API Error: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            error_log('Slack Driver Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Add reaction to a message
     */
    public function addReaction(string $emoji, string $timestamp, ?string $channel = null): bool
    {
        try {
            $channel = $channel ?: $this->channelId;

            if (!$channel) {
                return false;
            }

            $response = $this->client->reactionsAdd([
                'channel' => $channel,
                'timestamp' => $timestamp,
                'name' => $emoji,
            ]);

            return $response->getOk();
        } catch (SlackErrorResponse $e) {
            error_log('Slack API Error: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            error_log('Slack Driver Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user info
     */
    public function getUserInfo(string $userId): ?array
    {
        try {
            $response = $this->client->usersInfo(['user' => $userId]);

            if ($response->getOk()) {
                $user = $response->getUser();
                return [
                    'id' => $user->getId(),
                    'name' => $user->getName(),
                    'real_name' => $user->getRealName(),
                    'display_name' => $user->getProfile() ? $user->getProfile()->getDisplayName() : '',
                    'email' => $user->getProfile() ? $user->getProfile()->getEmail() : '',
                    'is_bot' => $user->getIsBot(),
                    'is_admin' => $user->getIsAdmin(),
                    'timezone' => $user->getTz(),
                ];
            }

            return null;
        } catch (SlackErrorResponse $e) {
            error_log('Slack API Error: ' . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            error_log('Slack Driver Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get channel info
     */
    public function getChannelInfo(string $channelId): ?array
    {
        try {
            $response = $this->client->conversationsInfo(['channel' => $channelId]);

            if ($response->getOk()) {
                $channel = $response->getChannel();
                return [
                    'id' => $channel->getId(),
                    'name' => $channel->getName(),
                    'is_channel' => $channel->getIsChannel(),
                    'is_group' => $channel->getIsGroup(),
                    'is_im' => $channel->getIsIm(),
                    'is_private' => $channel->getIsPrivate(),
                    'is_archived' => $channel->getIsArchived(),
                    'topic' => $channel->getTopic() ? $channel->getTopic()->getValue() : '',
                    'purpose' => $channel->getPurpose() ? $channel->getPurpose()->getValue() : '',
                    'num_members' => $channel->getNumMembers(),
                ];
            }

            return null;
        } catch (SlackErrorResponse $e) {
            error_log('Slack API Error: ' . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            error_log('Slack Driver Error: ' . $e->getMessage());
            return null;
        }
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function hasMessage(): bool
    {
        return !empty($this->message) && $this->isValidRequest;
    }

    /**
     * Get the event type
     */
    public function getEventType(): ?string
    {
        return $this->event['type'] ?? null;
    }

    /**
     * Get the Slack client for advanced operations
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Get the current event data
     */
    public function getEvent(): ?array
    {
        return $this->event;
    }

    /**
     * Check if the current message is a mention
     */
    public function isMention(): bool
    {
        return $this->getEventType() === 'app_mention';
    }

    /**
     * Check if the current message is a direct message
     */
    public function isDirectMessage(): bool
    {
        if (!$this->channelId) {
            return false;
        }

        // Direct message channels start with 'D'
        return strpos($this->channelId, 'D') === 0;
    }

    /**
     * Check if the current event is a slash command
     */
    public function isSlashCommand(): bool
    {
        return isset($this->data['command']);
    }

    /**
     * Check if the current event is an interactive component
     */
    public function isInteractive(): bool
    {
        return isset($this->data['payload']);
    }
}
