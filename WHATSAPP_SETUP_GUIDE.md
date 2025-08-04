# WhatsApp Chatbot Setup Guide

## 🚀 Complete WhatsApp Business API Integration

This guide will help you set up your WhatsApp chatbot using the Meta WhatsApp Business API.

## 📋 Prerequisites

- ✅ Meta Business Manager account
- ✅ Verified WhatsApp Business phone number
- ✅ HTTPS web server
- ✅ PHP 7.4+ with cURL support
- ✅ Valid SSL certificate

## 🔧 Step 1: Meta Business Manager Setup

### 1.1 Create Business Account
1. Go to [Meta Business Manager](https://business.facebook.com/)
2. Create or select your business account
3. Complete business verification if required

### 1.2 Add WhatsApp Product
1. In Business Manager, go to **Business Settings**
2. Click **Accounts** → **WhatsApp Business Accounts**
3. Click **Add** → **Create a WhatsApp Business Account**
4. Follow the setup wizard

### 1.3 Add Phone Number
1. In WhatsApp Business Account settings
2. Go to **Phone Numbers**
3. Click **Add Phone Number**
4. Verify your business phone number
5. Note down the **Phone Number ID**

## 🔐 Step 2: Access Token Generation

### 2.1 Temporary Token (for testing)
1. Go to **App Dashboard** → **WhatsApp** → **API Setup**
2. Copy the temporary access token
3. This expires in 24 hours

### 2.2 Permanent Token (for production)
1. Go to **Business Settings** → **System Users**
2. Create a new system user
3. Assign WhatsApp Business permissions
4. Generate permanent access token
5. Store securely

## 📱 Step 3: Configuration

### 3.1 Update Bot Configuration
Edit `whatsapp_bot_example.php`:

```php
// Replace these with your actual values
$accessToken = 'EAAxxxxxxx'; // Your permanent access token
$phoneNumberId = '123456789'; // Your phone number ID  
$verifyToken = 'my_secure_verify_token'; // Choose a secure token
```

### 3.2 Environment Variables (Recommended)
Create `.env` file:
```env
WHATSAPP_ACCESS_TOKEN=EAAxxxxxxx
WHATSAPP_PHONE_NUMBER_ID=123456789
WHATSAPP_VERIFY_TOKEN=my_secure_verify_token
```

Update your bot to use environment variables:
```php
$accessToken = $_ENV['WHATSAPP_ACCESS_TOKEN'] ?? 'YOUR_WHATSAPP_ACCESS_TOKEN';
$phoneNumberId = $_ENV['WHATSAPP_PHONE_NUMBER_ID'] ?? 'YOUR_PHONE_NUMBER_ID';
$verifyToken = $_ENV['WHATSAPP_VERIFY_TOKEN'] ?? 'YOUR_VERIFY_TOKEN';
```

## 🌐 Step 4: Webhook Setup

### 4.1 Upload Files
Upload these files to your HTTPS server:
- `src/` (all bot classes)
- `vendor/` (Composer dependencies)
- `whatsapp_bot_example.php` (bot logic)
- `whatsapp_webhook.php` (webhook endpoint)

### 4.2 Configure Webhook in Meta
1. Go to **App Dashboard** → **WhatsApp** → **Configuration**
2. In **Webhook** section:
   - **Callback URL**: `https://yourdomain.com/whatsapp_webhook.php`
   - **Verify Token**: Your verify token from Step 3.1
   - Click **Verify and Save**

### 4.3 Subscribe to Webhook Events
1. In webhook configuration
2. Click **Manage** next to your phone number
3. Subscribe to **messages** events
4. Save configuration

## 🧪 Step 5: Testing

### 5.1 Run Local Tests
```bash
cd /path/to/your/chatbot
php test_whatsapp_bot.php
```

### 5.2 Test Webhook Verification
Visit: `https://yourdomain.com/whatsapp_webhook.php?hub.mode=subscribe&hub.verify_token=YOUR_VERIFY_TOKEN&hub.challenge=test123`

Should return: `test123`

### 5.3 Manual Testing
1. Send a WhatsApp message to your business number
2. Try these commands:
   - `hello` - Welcome message
   - `menu` - Interactive buttons
   - `list` - Interactive list
   - `help` - All commands
   - `buttons` - Button demo
   - `image` - Send image
   - `contact` - Contact info

## 📊 Step 6: API Testing

### 6.1 Direct API Test
```bash
curl -X POST \
  'https://graph.facebook.com/v17.0/YOUR_PHONE_NUMBER_ID/messages' \
  -H 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "messaging_product": "whatsapp",
    "to": "RECIPIENT_PHONE_NUMBER",
    "text": { "body": "Hello from WhatsApp Bot!" }
  }'
```

### 6.2 Expected Response
```json
{
  "messaging_product": "whatsapp",
  "contacts": [
    {
      "input": "RECIPIENT_PHONE_NUMBER",
      "wa_id": "RECIPIENT_WHATSAPP_ID"
    }
  ],
  "messages": [
    {
      "id": "wamid.MESSAGE_ID"
    }
  ]
}
```

## ✨ Features Available

### 📝 Text Messages
```php
$bot->reply("Hello! This is a text message.");
```

### 🔘 Interactive Buttons (Max 3)
```php
$buttons = [
    ['id' => 'btn1', 'title' => 'Option 1'],
    ['id' => 'btn2', 'title' => 'Option 2'],
    ['id' => 'btn3', 'title' => 'Option 3']
];
$bot->getDriver()->sendButtons("Choose an option:", $buttons);
```

### 📋 Interactive Lists
```php
$sections = [
    [
        'title' => 'Section 1',
        'rows' => [
            ['id' => 'item1', 'title' => 'Item 1', 'description' => 'Description'],
            ['id' => 'item2', 'title' => 'Item 2', 'description' => 'Description']
        ]
    ]
];
$bot->getDriver()->sendList("Choose from list:", "Select", $sections);
```

### 🖼️ Images
```php
$bot->getDriver()->sendImage("https://example.com/image.jpg", "Caption text");
```

### 📋 Template Messages
```php
$bot->getDriver()->sendTemplate("hello_world", ["Parameter 1", "Parameter 2"]);
```

## 🔍 Debugging

### Debug Logs
Check these files for debugging:
- `whatsapp_webhook_debug.log` - Incoming webhooks
- PHP error logs - Server errors

### Common Issues

#### 1. Webhook Not Receiving Messages
- ✅ Check HTTPS certificate is valid
- ✅ Verify webhook URL returns 200 OK
- ✅ Check webhook subscription to 'messages' events
- ✅ Verify phone number is properly configured

#### 2. API Calls Failing
- ✅ Check access token is valid and permanent
- ✅ Verify phone number ID is correct
- ✅ Check API permissions
- ✅ Review error logs for specific error messages

#### 3. Button/List Interactions Not Working
- ✅ Ensure interactive message parsing is working
- ✅ Check button/list click handlers are defined
- ✅ Verify webhook receives interaction events

### Webhook Testing
Test your webhook manually:
```bash
curl -X POST https://yourdomain.com/whatsapp_webhook.php \
  -H "Content-Type: application/json" \
  -d '{
    "object": "whatsapp_business_account",
    "entry": [{
      "changes": [{
        "value": {
          "messages": [{
            "from": "1234567890",
            "text": {"body": "test message"}
          }]
        }
      }]
    }]
  }'
```

## 🚀 Production Deployment

### Security Checklist
- ✅ Use environment variables for tokens
- ✅ Implement request signature validation
- ✅ Set up proper error logging
- ✅ Use HTTPS with valid SSL certificate
- ✅ Implement rate limiting
- ✅ Remove debug logging

### Performance Optimization
- ✅ Use connection pooling for API calls
- ✅ Implement message queuing for high volume
- ✅ Cache template messages
- ✅ Optimize webhook response time

### Monitoring
- ✅ Set up webhook delivery monitoring
- ✅ Monitor API rate limits
- ✅ Track message delivery status
- ✅ Monitor error rates

## 📚 Additional Resources

- [WhatsApp Business API Documentation](https://developers.facebook.com/docs/whatsapp)
- [Webhook Setup Guide](https://developers.facebook.com/docs/whatsapp/webhooks)
- [Message Types Reference](https://developers.facebook.com/docs/whatsapp/api/messages)
- [Interactive Messages Guide](https://developers.facebook.com/docs/whatsapp/api/messages/interactive)

## 🆘 Support

If you encounter issues:

1. Check the debug logs
2. Review Meta's API documentation
3. Test with simple API calls first
4. Verify all configuration values
5. Check webhook event subscriptions

---

**Your WhatsApp chatbot is now ready! 🎉**

Start testing by sending messages to your WhatsApp Business number.
