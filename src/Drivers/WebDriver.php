<?php

namespace TusharKhan\Chatbot\Drivers;

use TusharKhan\Chatbot\Contracts\DriverInterface;

class WebDriver implements DriverInterface
{
    private $message;
    private $senderId;
    private $data;
    private $responses = [];

    public function __construct()
    {
        $this->loadFromRequest();
    }

    /**
     * Load data from HTTP request
     */
    private function loadFromRequest(): void
    {
        // Handle JSON POST data
        if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true);
            if ($input) {
                $this->message = $input['message'] ?? null;
                $this->senderId = $input['sender_id'] ?? $input['user_id'] ?? null;
                $this->data = $input;
                return;
            }
        }

        // Handle form data
        $this->message = $_POST['message'] ?? $_GET['message'] ?? null;
        $this->senderId = $_POST['sender_id'] ?? $_POST['user_id'] ?? $_GET['sender_id'] ?? $_GET['user_id'] ?? null;
        $this->data = array_merge($_GET, $_POST);

        // Generate sender ID from session or IP if not provided
        if (!$this->senderId) {
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            if (!isset($_SESSION['chatbot_user_id'])) {
                $_SESSION['chatbot_user_id'] = 'web_' . uniqid();
            }
            $this->senderId = $_SESSION['chatbot_user_id'];
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
        $this->responses[] = [
            'message' => $message,
            'sender_id' => $senderId ?: $this->senderId,
            'timestamp' => time()
        ];
        return true;
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
     * Get all responses to send back
     */
    public function getResponses(): array
    {
        return $this->responses;
    }

    /**
     * Output responses as JSON
     */
    public function outputJson(): void
    {
        header('Content-Type: application/json');
        echo json_encode([
            'responses' => $this->responses,
            'status' => 'success'
        ]);
    }

    /**
     * Output responses as HTML
     */
    public function outputHtml(): void
    {
        foreach ($this->responses as $response) {
            echo '<div class="bot-message">' . htmlspecialchars($response['message']) . '</div>';
        }
    }

    /**
     * Clear responses
     */
    public function clearResponses(): void
    {
        $this->responses = [];
    }
}
