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
                            // Handle different message types
                            if (isset($message['text']['body'])) {
                                $this->message = $message['text']['body'];
                            } elseif (isset($message['interactive'])) {
                                // Handle interactive message responses (button clicks, list selections)
                                if (isset($message['interactive']['button_reply'])) {
                                    $this->message = $message['interactive']['button_reply']['title'];
                                } elseif (isset($message['interactive']['list_reply'])) {
                                    $this->message = $message['interactive']['list_reply']['title'];
                                }
                            } elseif (isset($message['image'])) {
                                $this->message = '[image]';
                            } elseif (isset($message['document'])) {
                                $this->message = '[document]';
                            } elseif (isset($message['audio'])) {
                                $this->message = '[audio]';
                            } elseif (isset($message['video'])) {
                                $this->message = '[video]';
                            } else {
                                $this->message = '[media]';
                            }
                            
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
            error_log("WhatsApp: No recipient specified");
            return false;
        }

        $url = "https://graph.facebook.com/v17.0/{$this->phoneNumberId}/messages";
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'text' => ['body' => $message]
        ];

        return $this->makeApiCall($url, $data);
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
            error_log("WhatsApp: No recipient specified for template");
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

        return $this->makeApiCall($url, $data);
    }

    /**
     * Send image
     */
    public function sendImage(string $imageUrl, string $caption = '', ?string $to = null): bool
    {
        $to = $to ?: $this->senderId;
        
        if (!$to) {
            error_log("WhatsApp: No recipient specified for image");
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

        return $this->makeApiCall($url, $data);
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

        return $this->makeApiCall($url, $data);
    }

    /**
     * Make API call to WhatsApp Business API
     */
    private function makeApiCall(string $url, array $data): bool
    {
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
        
        if ($result === false) {
            error_log("WhatsApp API Error: Failed to make request to $url");
            return false;
        }

        $response = json_decode($result, true);
        if (isset($response['error'])) {
            error_log("WhatsApp API Error: " . json_encode($response['error']));
            return false;
        }

        return true;
    }

    /**
     * Send buttons message
     */
    public function sendButtons(string $text, array $buttons, ?string $to = null): bool
    {
        $to = $to ?: $this->senderId;
        
        if (!$to) {
            error_log("WhatsApp: No recipient specified for buttons");
            return false;
        }

        if (count($buttons) > 3) {
            error_log("WhatsApp: Maximum 3 buttons allowed");
            return false;
        }

        $url = "https://graph.facebook.com/v17.0/{$this->phoneNumberId}/messages";
        
        $buttonData = [];
        foreach ($buttons as $index => $button) {
            $buttonData[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => $button['id'] ?? "btn_$index",
                    'title' => $button['title']
                ]
            ];
        }

        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $text],
                'action' => [
                    'buttons' => $buttonData
                ]
            ]
        ];

        return $this->makeApiCall($url, $data);
    }

    /**
     * Send list message
     */
    public function sendList(string $text, string $buttonText, array $sections, ?string $to = null): bool
    {
        $to = $to ?: $this->senderId;
        
        if (!$to) {
            error_log("WhatsApp: No recipient specified for list");
            return false;
        }

        $url = "https://graph.facebook.com/v17.0/{$this->phoneNumberId}/messages";
        
        $listSections = [];
        foreach ($sections as $section) {
            $rows = [];
            foreach ($section['rows'] as $row) {
                $rows[] = [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'description' => $row['description'] ?? ''
                ];
            }
            
            $listSections[] = [
                'title' => $section['title'],
                'rows' => $rows
            ];
        }

        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => ['text' => $text],
                'action' => [
                    'button' => $buttonText,
                    'sections' => $listSections
                ]
            ]
        ];

        return $this->makeApiCall($url, $data);
    }

    /**
     * Get user profile information
     */
    public function getUserInfo(): ?array
    {
        if (!$this->senderId) {
            return null;
        }

        // WhatsApp Business API doesn't provide user profile info directly
        // Return basic info from webhook data
        return [
            'id' => $this->senderId,
            'phone' => $this->senderId,
            'platform' => 'whatsapp'
        ];
    }

    /**
     * Get webhook verification challenge
     */
    public function verifyWebhook(string $verifyToken): ?string
    {
        $mode = $_GET['hub_mode'] ?? null;
        $token = $_GET['hub_verify_token'] ?? null;
        $challenge = $_GET['hub_challenge'] ?? null;

        if ($mode === 'subscribe' && $token === $verifyToken) {
            return $challenge;
        }

        return null;
    }
}
