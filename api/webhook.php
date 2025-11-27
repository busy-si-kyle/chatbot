<?php
// Prevent Vercel from timing out
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Load Secrets
$verify_token = getenv('VERIFY_TOKEN');
$page_access_token = getenv('PAGE_ACCESS_TOKEN');

// ==========================================
// 1. VERIFICATION (GET Request)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $hub_verify_token = $_GET['hub_verify_token'] ?? '';
    $hub_challenge = $_GET['hub_challenge'] ?? '';

    if ($hub_verify_token === $verify_token) {
        header('Content-Type: text/plain');
        echo $hub_challenge;
        exit;
    } else {
        http_response_code(403);
        echo "Invalid Verify Token";
        exit;
    }
}

// ==========================================
// 2. MESSAGING (POST Request)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (isset($data['entry'][0]['messaging'][0])) {
        $messageData = $data['entry'][0]['messaging'][0];
        $sender_id = $messageData['sender']['id'];
        $message_text = $messageData['message']['text'] ?? '';

        if (!empty($message_text)) {
            // --- STEP 1: GET THE USER'S NAME ---
            $firstName = getUserName($sender_id, $page_access_token);
            
            // --- STEP 2: SIMPLE REPLY (NO LOGIC) ---
            // No matter what they typed, send this exact reply:
            $reply = "Hi $firstName! Thanks for your message.";

            // Send the reply
            sendReply($sender_id, $reply, $page_access_token);
        }
    }
    
    http_response_code(200);
    echo json_encode(["status" => "EVENT_RECEIVED"]);
    exit;
}

// ==========================================
// HELPER FUNCTIONS
// ==========================================

function getUserName($senderId, $token) {
    // Try to get the user's First Name from Facebook
    $url = "https://graph.facebook.com/$senderId?fields=first_name&access_token=$token";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($result, true);
    
    // Return the name if found, otherwise default to "Friend"
    return $data['first_name'] ?? "Friend";
}

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
    curl_exec($ch);
    curl_close($ch);
}
?>