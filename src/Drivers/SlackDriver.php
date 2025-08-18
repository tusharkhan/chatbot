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
        $this->client = ClientFactory::create($this->botToken);

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
