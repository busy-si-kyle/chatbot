<?php
// Prevent Vercel from timing out or caching the wrong things
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Load secrets from Environment Variables
$verify_token = getenv('VERIFY_TOKEN');
$page_access_token = getenv('PAGE_ACCESS_TOKEN');

// ==========================================
// 1. VERIFICATION (GET Request)
// Facebook calls this once to verify your URL
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $hub_mode = $_GET['hub_mode'] ?? '';
    $hub_verify_token = $_GET['hub_verify_token'] ?? '';
    $hub_challenge = $_GET['hub_challenge'] ?? '';

    if ($hub_mode === 'subscribe' && $hub_verify_token === $verify_token) {
        // Facebook requires a plain text response of the challenge string
        header('Content-Type: text/plain');
        echo $hub_challenge;
        exit;
    } else {
        http_response_code(403);
        echo "Forbidden: Invalid Verify Token";
        exit;
    }
}

// ==========================================
// 2. MESSAGING (POST Request)
// Facebook calls this every time a user sends a message
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read the incoming JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Check if this is a message from a page subscription
    if (isset($data['entry'][0]['messaging'][0])) {
        $messageData = $data['entry'][0]['messaging'][0];
        
        // Extract sender ID and message text
        $sender_id = $messageData['sender']['id'];
        $message_text = $messageData['message']['text'] ?? '';

        // If there is text, reply with the same text
        if (!empty($message_text)) {
            sendReply($sender_id, "You said: " . $message_text, $page_access_token);
        }
    }

    // Always return 200 OK, otherwise Facebook attempts to retry sending
    http_response_code(200);
    echo json_encode(["status" => "EVENT_RECEIVED"]);
    exit;
}

// Helper Function: Send Message to Graph API
function sendReply($recipientId, $messageText, $token) {
    $url = "https://graph.facebook.com/v18.0/me/messages?access_token=" . $token;
    
    $jsonData = json_encode([
        "recipient" => ["id" => $recipientId],
        "message" => ["text" => $messageText]
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
}
?>