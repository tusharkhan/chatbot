# SSL Configuration for Slack Driver

The SlackDriver requires SSL certificates for secure communication with Slack's API. Here are several ways to configure SSL certificates:

## Method 1: Environment Variable (Recommended)

Set the `SLACK_CACERT_PATH` environment variable to point to your certificate file:

### For Laravel (.env file):
```env
SLACK_CACERT_PATH=/path/to/your/cacert.pem
```

### For other frameworks:
```php
putenv('SLACK_CACERT_PATH=/path/to/your/cacert.pem');
// or
$_ENV['SLACK_CACERT_PATH'] = '/path/to/your/cacert.pem';
```

## Method 2: Automatic Download and Configuration

Use the SSLConfig helper class to automatically download and configure certificates:

```php
use TusharKhan\Chatbot\Config\SSLConfig;

try {
    $certPath = SSLConfig::downloadAndConfigureCertificate();
    echo "Certificate downloaded and configured: " . $certPath;
} catch (Exception $e) {
    echo "Failed to configure SSL: " . $e->getMessage();
}
```

## Method 3: Manual Certificate Setup

1. Download the certificate manually:
```bash
curl -o cacert.pem https://curl.se/ca/cacert.pem
```

2. Place it in your project directory (e.g., `storage/certs/cacert.pem` for Laravel)

3. Configure the path:
```php
SSLConfig::setCertificatePath('/path/to/your/cacert.pem');
```

## Method 4: For Local Development Only

⚠️ **WARNING: Never use this in production!**

```php
use TusharKhan\Chatbot\Config\SSLConfig;

// Only for local development
if (env('APP_ENV') === 'local') {
    SSLConfig::disableSSLVerification();
}
```

## Laravel Integration Example

Add this to your `AppServiceProvider` boot method:

```php
use TusharKhan\Chatbot\Config\SSLConfig;

public function boot()
{
    // For production/staging
    if (env('SLACK_CACERT_PATH')) {
        SSLConfig::setCertificatePath(env('SLACK_CACERT_PATH'));
    } 
    // Auto-download for development (optional)
    elseif (env('APP_ENV') === 'local') {
        try {
            SSLConfig::downloadAndConfigureCertificate();
        } catch (Exception $e) {
            // Fallback to disabled SSL for development only
            SSLConfig::disableSSLVerification();
            logger()->warning('SSL certificate auto-configuration failed, disabled SSL verification for development');
        }
    }
}
```

## Route Example with SSL Configuration

```php
Route::post('slack-webhook', function() {
    // Configure SSL before creating SlackDriver
    if (!getenv('SLACK_CACERT_PATH')) {
        if (env('APP_ENV') === 'local') {
            // For development - try auto-download, fallback to disable SSL
            try {
                SSLConfig::downloadAndConfigureCertificate();
            } catch (Exception $e) {
                SSLConfig::disableSSLVerification();
            }
        } else {
            throw new Exception('SSL certificate not configured. Please set SLACK_CACERT_PATH environment variable.');
        }
    }
    
    $driver = new SlackDriver($botToken, $signingSecret);
    // ... rest of your code
});
```

## Common Certificate Locations

The SlackDriver automatically checks these locations:

1. `SLACK_CACERT_PATH` environment variable (highest priority)
2. Laravel storage path: `storage/certs/cacert.pem`
3. Laragon paths (auto-detected)
4. System certificate paths
5. PHP's configured certificate paths

## Troubleshooting

### Error: "SSL Certificate Configuration Required"
This means no certificate was found. Use one of the methods above to configure SSL.

### Error: "Certificate file not found"
The path in `SLACK_CACERT_PATH` doesn't exist. Check the path or download the certificate.

### Error: "Certificate file appears to be invalid"
The certificate file is empty or corrupted. Re-download it:
```bash
curl -o cacert.pem https://curl.se/ca/cacert.pem
```

## Production Considerations

1. **Never disable SSL verification in production**
2. **Store certificates in a secure location**
3. **Keep certificates updated** (they expire periodically)
4. **Use environment variables** for certificate paths
5. **Monitor certificate expiration** and set up auto-renewal if possible

## Support

If you're still having SSL issues, please check:
1. Your certificate file exists and is readable
2. Your PHP installation supports SSL/TLS
3. Your server allows outbound HTTPS connections
4. Your firewall doesn't block SSL traffic
