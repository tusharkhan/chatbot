# Cross-Platform SSL Configuration

The SlackDriver now supports automatic SSL certificate detection and configuration across Windows, macOS, and Linux operating systems. This document explains how the cross-platform SSL configuration works and how to use it.

## Overview

The SSL configuration system automatically:
1. Detects the operating system
2. Searches for SSL certificates in common locations for that OS
3. Provides fallback options for downloading certificates
4. Configures SSL settings appropriately for local development

## Supported Operating Systems

### Windows
- **Laragon**: Automatically detects certificates at `D:\laragon\etc\ssl\cacert.pem` or `C:\laragon\etc\ssl\cacert.pem`
- **XAMPP**: Looks for certificates at `C:\xampp\php\extras\ssl\cacert.pem`
- **WAMP**: Searches in WAMP PHP SSL directories
- **User Directory**: `%USERPROFILE%\AppData\Local\chatbot-certs\`

### macOS
- **Homebrew**: Checks `/usr/local/etc/openssl/cert.pem` and `/opt/homebrew/etc/openssl/cert.pem`
- **System**: Uses `/etc/ssl/cert.pem`
- **MAMP**: Looks in `/Applications/MAMP/conf/apache/ssl.crt/`
- **Valet**: Checks Valet certificate paths
- **User Directory**: `~/Library/Application Support/chatbot-certs/`

### Linux
- **Ubuntu/Debian**: Uses `/etc/ssl/certs/ca-certificates.crt`
- **CentOS/RHEL**: Uses `/etc/pki/tls/certs/ca-bundle.crt`
- **Docker**: Checks container-specific paths
- **User Directory**: `~/.local/share/chatbot-certs/`

## Configuration Methods

### Method 1: Environment Variable (Recommended)
Set the `SLACK_CACERT_PATH` environment variable to point to your certificate file:

#### Windows
```powershell
# PowerShell
$env:SLACK_CACERT_PATH = "C:\path\to\your\cacert.pem"

# Command Prompt
set SLACK_CACERT_PATH=C:\path\to\your\cacert.pem

# Permanently (requires restart)
setx SLACK_CACERT_PATH "C:\path\to\your\cacert.pem"
```

#### macOS/Linux
```bash
# Temporary
export SLACK_CACERT_PATH="/path/to/your/cacert.pem"

# Permanent (add to ~/.bashrc, ~/.zshrc, or ~/.profile)
echo 'export SLACK_CACERT_PATH="/path/to/your/cacert.pem"' >> ~/.bashrc
```

### Method 2: Laravel .env File
If you're using Laravel, add this to your `.env` file:
```env
SLACK_CACERT_PATH=storage/certs/cacert.pem
```

### Method 3: Automatic Download
The system can automatically download the certificate:

#### Windows (PowerShell)
```powershell
# Create directory
New-Item -ItemType Directory -Force -Path "$env:USERPROFILE\AppData\Local\chatbot-certs"
# Download certificate
Invoke-WebRequest -Uri "https://curl.se/ca/cacert.pem" -OutFile "$env:USERPROFILE\AppData\Local\chatbot-certs\cacert.pem"
# Set environment variable
$env:SLACK_CACERT_PATH = "$env:USERPROFILE\AppData\Local\chatbot-certs\cacert.pem"
```

#### macOS
```bash
# Create directory
mkdir -p "$HOME/Library/Application Support/chatbot-certs"
# Download certificate
curl -o "$HOME/Library/Application Support/chatbot-certs/cacert.pem" https://curl.se/ca/cacert.pem
# Set environment variable
export SLACK_CACERT_PATH="$HOME/Library/Application Support/chatbot-certs/cacert.pem"
```

#### Linux
```bash
# Create directory
mkdir -p "$HOME/.local/share/chatbot-certs"
# Download certificate
curl -o "$HOME/.local/share/chatbot-certs/cacert.pem" https://curl.se/ca/cacert.pem
# Set environment variable
export SLACK_CACERT_PATH="$HOME/.local/share/chatbot-certs/cacert.pem"
```

## Development Environment Detection

The system automatically detects common development environments:

### Windows
- **Laragon**: Looks for `LARAGON_ROOT` environment variable
- **XAMPP**: Checks for `XAMPP_ROOT` environment variable
- **WAMP**: Searches common WAMP installation directories

### macOS
- **Homebrew**: Detects Homebrew installations
- **MAMP**: Looks for MAMP.app installations
- **Valet**: Checks for Laravel Valet configurations

### Linux
- **Docker**: Detects container environments
- **Vagrant**: Looks for Vagrant-specific paths
- **System packages**: Uses system certificate stores

## Troubleshooting

### Certificate Not Found
If you see SSL certificate errors:

1. **Check environment variable**:
   ```bash
   # Windows (PowerShell)
   echo $env:SLACK_CACERT_PATH
   
   # macOS/Linux
   echo $SLACK_CACERT_PATH
   ```

2. **Verify certificate file exists**:
   ```bash
   # Check if file exists and is readable
   ls -la /path/to/your/cacert.pem
   ```

3. **Download manually**:
   ```bash
   curl -o cacert.pem https://curl.se/ca/cacert.pem
   ```

### SSL Verification Issues
For development environments, the system automatically:
- Disables SSL peer verification
- Sets appropriate cURL options
- Configures stream contexts

### Permission Issues
Ensure the certificate file and directory are readable:

#### Windows
```powershell
# Check permissions
Get-Acl "C:\path\to\cacert.pem"
```

#### macOS/Linux
```bash
# Check permissions
ls -la /path/to/cacert.pem

# Fix permissions if needed
chmod 644 /path/to/cacert.pem
```

## Testing Your Configuration

Use the provided test script to verify your configuration:

```bash
php test_cross_platform_ssl.php
```

This will show:
- Detected operating system
- Found certificate paths
- Environment variables
- Platform-specific recommendations

## Production Considerations

1. **Always use proper SSL certificates in production**
2. **Don't disable SSL verification in production**
3. **Use system-provided certificate stores when possible**
4. **Set appropriate file permissions for certificate files**

## Environment Variables Reference

- `SLACK_CACERT_PATH`: Path to your SSL certificate file
- `LARAGON_ROOT`: Laragon installation directory (Windows)
- `XAMPP_ROOT`: XAMPP installation directory (Windows)
- `SSL_CERT_FILE`: System SSL certificate file
- `SSL_CERT_DIR`: System SSL certificate directory
- `CURL_CA_BUNDLE`: cURL certificate bundle path

## Automatic Fallbacks

The system provides these fallbacks in order:

1. User-defined `SLACK_CACERT_PATH` environment variable
2. Laravel storage directory (if available)
3. OS-specific development server paths
4. Current working directory
5. PHP's default certificate settings
6. Automatic download to user directory
7. SSL verification bypass (development only)

This ensures maximum compatibility across different environments while maintaining security best practices.
