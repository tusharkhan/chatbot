<?php

namespace TusharKhan\Chatbot\Drivers;

use TusharKhan\Chatbot\Contracts\DriverInterface;

class TelegramDriver implements DriverInterface
{
    private $token;
    private $webhook;
    private $message;
    private $senderId;
    private $chatId;
    private $data;

    public function __construct(string $token, array $webhook = null)
    {
        $this->token = $token;
        $this->webhook = $webhook ?: json_decode(file_get_contents('php://input'), true);
        $this->parseWebhook();
    }

    /**
     * Parse Telegram webhook data
     */
    private function parseWebhook(): void
    {
        if (!$this->webhook || !isset($this->webhook['message'])) {
            return;
        }

        $message = $this->webhook['message'];
        
        $this->message = $message['text'] ?? null;
        $this->senderId = (string) $message['from']['id'];
        $this->chatId = (string) $message['chat']['id'];
        $this->data = $this->webhook;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getSenderId(): ?string
    {
        return $this->senderId;
    }

    public function sendMessage(string $message, ?string $senderId = null): bool
    {
        $chatId = $senderId ?: $this->chatId;
        
        if (!$chatId) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$this->token}/sendMessage";
        
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];

        $options = [
            'http' => [
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        return $result !== false;
    }

    public function getData(): array
    {
        return $this->data ?? [];
    }

    public function hasMessage(): bool
    {
        return !empty($this->message);
    }

    /**
     * Send photo
     */
    public function sendPhoto(string $photo, string $caption = '', ?string $chatId = null): bool
    {
        $chatId = $chatId ?: $this->chatId;
        
        if (!$chatId) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$this->token}/sendPhoto";
        
        $data = [
            'chat_id' => $chatId,
            'photo' => $photo,
            'caption' => $caption
        ];

        $options = [
            'http' => [
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        return $result !== false;
    }

    /**
     * Send keyboard
     */
    public function sendKeyboard(string $message, array $keyboard, ?string $chatId = null): bool
    {
        $chatId = $chatId ?: $this->chatId;
        
        if (!$chatId) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$this->token}/sendMessage";
        
        $replyMarkup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ];

        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($replyMarkup)
        ];

        $options = [
            'http' => [
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        return $result !== false;
    }

    /**
     * Set webhook
     */
    public function setWebhook(string $url): bool
    {
        $webhookUrl = "https://api.telegram.org/bot{$this->token}/setWebhook";
        
        $data = ['url' => $url];

        $options = [
            'http' => [
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($webhookUrl, false, $context);
        
        return $result !== false;
    }

    /**
     * Get chat ID
     */
    public function getChatId(): ?string
    {
        return $this->chatId;
    }

    /**
     * Get user info
     */
    public function getUserInfo(): ?array
    {
        if (!$this->webhook || !isset($this->webhook['message']['from'])) {
            return null;
        }

        return $this->webhook['message']['from'];
    }
}
