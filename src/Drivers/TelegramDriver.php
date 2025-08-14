<?php

namespace TusharKhan\Chatbot\Drivers;

use TusharKhan\Chatbot\Contracts\DriverInterface;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;
use Telegram\Bot\Objects\Message;
use Telegram\Bot\Objects\CallbackQuery;
use Telegram\Bot\Exceptions\TelegramSDKException;

class TelegramDriver implements DriverInterface
{
    private Api $telegram;
    private ?Update $update = null;
    private ?string $message = null;
    private ?string $senderId = null;
    private ?string $chatId = null;
    private array $data = [];
    private ?CallbackQuery $callbackQuery = null;
    private ?string $messageType = null;
    private ?Message $messageObject = null;

    public function __construct(string $token, array $webhook = [])
    {
        $this->telegram = new Api($token);

        if ($webhook) {
            $this->parseWebhookData($webhook);
        } else {
            $this->parseWebhookInput();
        }
    }

    /**
     * Get webhook input safely
     */
    private function parseWebhookInput(): void
    {
        $input = file_get_contents('php://input');
        if ($input === false || empty($input)) {
            return;
        }

        $decoded = json_decode($input, true);
        if ($decoded) {
            $this->parseWebhookData($decoded);
        }
    }

    /**
     * Parse webhook data using SDK
     */
    private function parseWebhookData(array $webhookData): void
    {
        try {
            $this->update = new Update($webhookData);
            $this->data = $webhookData;

            // Handle regular messages
            if ($this->update->getMessage()) {
                $this->parseMessage($this->update->getMessage());
            }

            // Handle callback queries (inline keyboard responses)
            if ($this->update->getCallbackQuery()) {
                $this->parseCallbackQuery($this->update->getCallbackQuery());
            }

            // Handle edited messages
            if ($this->update->getEditedMessage()) {
                $this->parseMessage($this->update->getEditedMessage());
                $this->messageType = 'edited_message';
            }
        } catch (\Telegram\Bot\Exceptions\TelegramSDKException $e) {
            error_log("Telegram SDK Error: " . $e->getMessage());
        }
    }

    /**
     * Parse regular message using SDK objects
     */
    private function parseMessage(Message $message): void
    {
        $this->messageObject = $message;
        $this->message = $message->getText();
        $this->senderId = (string) $message->getFrom()->getId();
        $this->chatId = (string) $message->getChat()->getId();
        $this->messageType = 'message';

        // Handle different message types
        if (!$this->message) {
            if ($message->getPhoto()) {
                $this->messageType = 'photo';
                $this->message = $message->getCaption() ?? '';
            } elseif ($message->getDocument()) {
                $this->messageType = 'document';
                $this->message = $message->getCaption() ?? '';
            } elseif ($message->getSticker()) {
                $this->messageType = 'sticker';
            } elseif ($message->getVideo()) {
                $this->messageType = 'video';
                $this->message = $message->getCaption() ?? '';
            } elseif ($message->getAudio()) {
                $this->messageType = 'audio';
                $this->message = $message->getCaption() ?? '';
            } elseif ($message->getVoice()) {
                $this->messageType = 'voice';
            } elseif ($message->getLocation()) {
                $this->messageType = 'location';
            }
        }
    }

    /**
     * Parse callback query using SDK objects
     */
    private function parseCallbackQuery(CallbackQuery $callbackQuery): void
    {
        $this->callbackQuery = $callbackQuery;
        $this->message = $callbackQuery->getData();
        $this->senderId = (string) $callbackQuery->getFrom()->getId();
        $this->chatId = (string) $callbackQuery->getMessage()->getChat()->getId();
        $this->messageType = 'callback_query';
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

        try {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML'
            ]);
            return true;
        } catch (TelegramSDKException $e) {
            error_log("Failed to send message: " . $e->getMessage());
            return false;
        }
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function hasMessage(): bool
    {
        return !empty($this->message);
    }

    /**
     * Send photo using SDK
     */
    public function sendPhoto(string $photo, string $caption = '', ?string $chatId = null): bool
    {
        $chatId = $chatId ?: $this->chatId;
        
        if (!$chatId) {
            return false;
        }

        try {
            $this->telegram->sendPhoto([
                'chat_id' => $chatId,
                'photo' => $photo,
                'caption' => $caption
            ]);
            return true;
        } catch (TelegramSDKException $e) {
            error_log("Failed to send photo: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send document using SDK
     */
    public function sendDocument(string $document, string $caption = '', ?string $chatId = null): bool
    {
        $chatId = $chatId ?: $this->chatId;

        if (!$chatId) {
            return false;
        }

        try {
            $this->telegram->sendDocument([
                'chat_id' => $chatId,
                'document' => $document,
                'caption' => $caption
            ]);
            return true;
        } catch (TelegramSDKException $e) {
            error_log("Failed to send document: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send keyboard using SDK
     */
    public function sendKeyboard(string $message, array $keyboard, ?string $chatId = null): bool
    {
        $chatId = $chatId ?: $this->chatId;
        
        if (!$chatId) {
            return false;
        }

        try {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $message,
                'reply_markup' => json_encode([
                    'keyboard' => $keyboard,
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true
                ])
            ]);
            return true;
        } catch (TelegramSDKException $e) {
            error_log("Failed to send keyboard: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send inline keyboard using SDK
     */
    public function sendInlineKeyboard(string $message, array $inlineKeyboard, ?string $chatId = null): bool
    {
        $chatId = $chatId ?: $this->chatId;

        if (!$chatId) {
            return false;
        }

        try {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $message,
                'reply_markup' => json_encode([
                    'inline_keyboard' => $inlineKeyboard
                ])
            ]);
            return true;
        } catch (TelegramSDKException $e) {
            error_log("Failed to send inline keyboard: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Set webhook using SDK
     */
    public function setWebhook(string $url)
    {
        try {
            $response = $this->telegram->setWebhook(['url' => $url]);
            // The response should be a boolean for setWebhook
            return $response;
        } catch (TelegramSDKException $e) {
            error_log("Failed to set webhook: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get webhook info using SDK
     */
    public function getWebhookInfo(): ?array
    {
        try {
            $response = $this->telegram->getWebhookInfo();
            // Try different methods to get the data
            if (method_exists($response, 'toArray')) {
                return $response->toArray();
            } elseif (method_exists($response, 'getDecodedBody')) {
                return $response->getDecodedBody();
            } elseif (method_exists($response, 'getResult')) {
                return $response->getResult();
            } else {
                // If it's already an array or can be cast to array
                return (array) $response;
            }
        } catch (TelegramSDKException $e) {
            error_log("Failed to get webhook info: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete webhook using SDK
     */
    public function deleteWebhook(): bool
    {
        try {
            return $this->telegram->deleteWebhook();
        } catch (TelegramSDKException $e) {
            error_log("Failed to delete webhook: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get chat ID
     */
    public function getChatId(): ?string
    {
        return $this->chatId;
    }

    /**
     * Get user info using SDK objects
     */
    public function getUserInfo(): ?array
    {
        if ($this->messageType === 'callback_query' && $this->callbackQuery) {
            return $this->callbackQuery->getFrom()->toArray();
        }

        if ($this->messageObject) {
            return $this->messageObject->getFrom()->toArray();
        }

        return null;
    }

    /**
     * Get message type
     */
    public function getMessageType(): ?string
    {
        return $this->messageType;
    }

    /**
     * Get callback query data
     */
    public function getCallbackQuery(): ?array
    {
        return $this->callbackQuery ? $this->callbackQuery->toArray() : null;
    }

    /**
     * Answer callback query using SDK
     */
    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): bool
    {
        try {
            $this->telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQueryId,
                'text' => $text,
                'show_alert' => $showAlert
            ]);
            return true;
        } catch (TelegramSDKException $e) {
            error_log("Failed to answer callback query: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get photo file info using SDK
     */
    public function getPhotoInfo(): ?array
    {
        if ($this->messageType !== 'photo' || !$this->messageObject) {
            return null;
        }

        $photos = $this->messageObject->getPhoto();
        return $photos ? array_map(fn($photo) => $photo->toArray(), $photos) : null;
    }

    /**
     * Get document info using SDK
     */
    public function getDocumentInfo(): ?array
    {
        if ($this->messageType !== 'document' || !$this->messageObject) {
            return null;
        }

        $document = $this->messageObject->getDocument();
        return $document ? $document->toArray() : null;
    }

    /**
     * Get video info using SDK
     */
    public function getVideoInfo(): ?array
    {
        if ($this->messageType !== 'video' || !$this->messageObject) {
            return null;
        }

        $video = $this->messageObject->getVideo();
        return $video ? $video->toArray() : null;
    }

    /**
     * Get audio info using SDK
     */
    public function getAudioInfo(): ?array
    {
        if ($this->messageType !== 'audio' || !$this->messageObject) {
            return null;
        }

        $audio = $this->messageObject->getAudio();
        return $audio ? $audio->toArray() : null;
    }

    /**
     * Get voice info using SDK
     */
    public function getVoiceInfo(): ?array
    {
        if ($this->messageType !== 'voice' || !$this->messageObject) {
            return null;
        }

        $voice = $this->messageObject->getVoice();
        return $voice ? $voice->toArray() : null;
    }

    /**
     * Get location info using SDK
     */
    public function getLocationInfo(): ?array
    {
        if ($this->messageType !== 'location' || !$this->messageObject) {
            return null;
        }

        $location = $this->messageObject->getLocation();
        return $location ? $location->toArray() : null;
    }

    /**
     * Send location using SDK
     */
    public function sendLocation(float $latitude, float $longitude, ?string $chatId = null): bool
    {
        $chatId = $chatId ?: $this->chatId;

        if (!$chatId) {
            return false;
        }

        try {
            $this->telegram->sendLocation([
                'chat_id' => $chatId,
                'latitude' => $latitude,
                'longitude' => $longitude
            ]);
            return true;
        } catch (TelegramSDKException $e) {
            error_log("Failed to send location: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get bot info using SDK
     */
    public function getBotInfo(): ?array
    {
        try {
            $me = $this->telegram->getMe();
            // Try different methods to get the data
            if (method_exists($me, 'toArray')) {
                return $me->toArray();
            } elseif (method_exists($me, 'getDecodedBody')) {
                return $me->getDecodedBody();
            } else {
                // If it's already an array or can be cast to array
                return (array) $me;
            }
        } catch (TelegramSDKException $e) {
            error_log("Failed to get bot info: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get SDK instance for advanced usage
     */
    public function getTelegramApi(): Api
    {
        return $this->telegram;
    }

    /**
     * Get Update object for advanced usage
     */
    public function getUpdate(): ?Update
    {
        return $this->update;
    }

    /**
     * Get Message object for advanced usage
     */
    public function getMessageObject(): ?Message
    {
        return $this->messageObject;
    }
}
