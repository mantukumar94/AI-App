<?php
session_start();

$apiKey = "AIzaSyA8iSRmKIoTlIW3Lv952h_yTWQdYFIoruY";
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

if (!isset($_SESSION['chat'])) {
    $_SESSION['chat'] = [];
}

// Reset chat
if (isset($_GET['action']) && $_GET['action'] === 'reset') {
    $_SESSION['chat'] = [];
    exit;
}

// Get chat history
if (isset($_GET['action']) && $_GET['action'] === 'history') {
    header("Content-Type: application/json");
    echo json_encode($_SESSION['chat']);
    exit;
}

// Handle user message
$data = json_decode(file_get_contents("php://input"), true);
if (!empty($data['message'])) {
    $userMessage = trim($data['message']);
    $_SESSION['chat'][] = ["role" => "user", "text" => $userMessage];

    // ✅ Convert history for Gemini (role must be user/model)
    $contents = [];
    foreach ($_SESSION['chat'] as $msg) {
        $contents[] = [
            "role" => ($msg['role'] === "ai" ? "model" : "user"),
            "parts" => [["text" => $msg['text']]]
        ];
    }

    $postData = ["contents" => $contents];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

    // ⚠️ For local dev only (disable SSL check)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    if ($httpCode !== 200) {
        $reply = "❌ API Error: " . ($result['error']['message'] ?? "Unknown error");
    } elseif (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $reply = $result['candidates'][0]['content']['parts'][0]['text'];
    } else {
        $reply = "⚠️ Unexpected API response.";
    }

    $_SESSION['chat'][] = ["role" => "ai", "text" => $reply];

    header("Content-Type: application/json");
    echo json_encode(["reply" => $reply]);
    exit;
}
