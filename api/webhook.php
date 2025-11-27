<?php
// Prevent Vercel from timing out / Handle CORS
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

        // Check if message text exists (ignore stickers/likes for now)
        if (!empty($message_text)) {
            
            // --- STEP 1: GET THE FULL NAME (Safely) ---
            $fullName = getUserFullName($sender_id, $page_access_token);
            
            // --- STEP 2: PREPARE REPLY ---
            $reply = "Hi $fullName, I received your message: '$message_text'";

            // --- STEP 3: SEND REPLY ---
            sendReply($sender_id, $reply, $page_access_token);
        }
    }
    
    // Always return 200 OK to Facebook, otherwise they will retry and cause "stuck sending"
    http_response_code(200);
    echo json_encode(["status" => "EVENT_RECEIVED"]);
    exit;
}

// ==========================================
// HELPER FUNCTIONS
// ==========================================

function getUserFullName($senderId, $token) {
    // Added v18.0 to ensure stability
    $url = "https://graph.facebook.com/v18.0/$senderId?fields=name&access_token=$token";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Don't wait more than 5 seconds
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // If API fails or permission denied (common with non-public apps), default to "Friend"
    if ($httpCode !== 200 || !$result) {
        return "Friend";
    }

    $data = json_decode($result, true);
    
    return $data['name'] ?? "Friend";
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Don't wait more than 5 seconds
    curl_exec($ch);
    curl_close($ch);
}
?>