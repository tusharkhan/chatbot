<?php

namespace TusharKhan\Chatbot\Drivers;

use TusharKhan\Chatbot\Contracts\DriverInterface;

class WhatsAppDriver implements DriverInterface
{
    private $token;
    private $phoneNumberId;
    private $webhook;
    private $message;
    private $senderId;
    private $data;

    public function __construct(string $token, string $phoneNumberId, array $webhook = null)
    {
        $this->token = $token;
        $this->phoneNumberId = $phoneNumberId;
        $this->webhook = $webhook ?: json_decode(file_get_contents('php://input'), true);
        $this->parseWebhook();
    }

    /**
     * Parse WhatsApp webhook data
     */
    private function parseWebhook(): void
    {
        if (!$this->webhook || !isset($this->webhook['entry'])) {
            return;
        }

        foreach ($this->webhook['entry'] as $entry) {
            if (isset($entry['changes'])) {
                foreach ($entry['changes'] as $change) {
                    if (isset($change['value']['messages'])) {
                        foreach ($change['value']['messages'] as $message) {
                            $this->message = $message['text']['body'] ?? null;
                            $this->senderId = $message['from'];
                            $this->data = $this->webhook;
                            return; // Process only the first message
                        }
                    }
                }
            }
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

    public function sendMessage(string $message, ?string $senderId = null): bool
    {
        $to = $senderId ?: $this->senderId;
        
        if (!$to) {
            return false;
        }

        $url = "https://graph.facebook.com/v17.0/{$this->phoneNumberId}/messages";
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'text' => ['body' => $message]
        ];

        $options = [
            'http' => [
                'header' => [
                    "Content-Type: application/json",
                    "Authorization: Bearer {$this->token}"
                ],
                'method' => 'POST',
                'content' => json_encode($data)
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
     * Send template message
     */
    public function sendTemplate(string $templateName, array $parameters = [], ?string $to = null): bool
    {
        $to = $to ?: $this->senderId;
        
        if (!$to) {
            return false;
        }

        $url = "https://graph.facebook.com/v17.0/{$this->phoneNumberId}/messages";
        
        $template = [
            'name' => $templateName,
            'language' => ['code' => 'en_US']
        ];

        if (!empty($parameters)) {
            $template['components'] = [
                [
                    'type' => 'body',
                    'parameters' => array_map(function($param) {
                        return ['type' => 'text', 'text' => $param];
                    }, $parameters)
                ]
            ];
        }

        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => $template
        ];

        $options = [
            'http' => [
                'header' => [
                    "Content-Type: application/json",
                    "Authorization: Bearer {$this->token}"
                ],
                'method' => 'POST',
                'content' => json_encode($data)
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        return $result !== false;
    }

    /**
     * Send image
     */
    public function sendImage(string $imageUrl, string $caption = '', ?string $to = null): bool
    {
        $to = $to ?: $this->senderId;
        
        if (!$to) {
            return false;
        }

        $url = "https://graph.facebook.com/v17.0/{$this->phoneNumberId}/messages";
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'image',
            'image' => [
                'link' => $imageUrl,
                'caption' => $caption
            ]
        ];

        $options = [
            'http' => [
                'header' => [
                    "Content-Type: application/json",
                    "Authorization: Bearer {$this->token}"
                ],
                'method' => 'POST',
                'content' => json_encode($data)
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        return $result !== false;
    }

    /**
     * Mark message as read
     */
    public function markAsRead(string $messageId): bool
    {
        $url = "https://graph.facebook.com/v17.0/{$this->phoneNumberId}/messages";
        
        $data = [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId
        ];

        $options = [
            'http' => [
                'header' => [
                    "Content-Type: application/json",
                    "Authorization: Bearer {$this->token}"
                ],
                'method' => 'POST',
                'content' => json_encode($data)
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        return $result !== false;
    }
}
