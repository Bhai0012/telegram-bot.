<?php
// CONFIG
$botToken = "7635685639:AAGztPHjQHJsiHxfGIJU3ApNu9bE_ttEZSw"; // Replace with your actual Bot Token
$apiUrl = "https://api.telegram.org/bot$botToken/";
$bgImageUrl = "https://i.ibb.co/NWVXb6n/black-verification.png"; // ✅ Working image for verification

// DATABASE CONNECTION
$db = new mysqli("localhost", "root", "", "telegram_bot");

// Check connection
if ($db->connect_error) {
    error_log("Database connection failed: " . $db->connect_error);
    exit("Database connection failed.");
}

// FUNCTION TO SEND TELEGRAM MESSAGES
function sendTelegram($method, $data) {
    global $apiUrl;
    $ch = curl_init($apiUrl . $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

// Log the incoming request for debugging
file_put_contents("update_log.txt", file_get_contents("php://input") . "\n", FILE_APPEND);

// HANDLE UPDATES
$content = file_get_contents("php://input");
$update = json_decode($content, true);

// HANDLE /start command
if (isset($update["message"])) {
    $chatId = $update["message"]["chat"]["id"];
    $text = $update["message"]["text"];

    if ($text === "/start") {
        sendTelegram("sendMessage", [
            "chat_id" => $chatId,
            "text" => "🔐 Verify yourself to use the bot",
            "reply_markup" => json_encode([
                "inline_keyboard" => [
                    [["text" => "✅ Click to Verify", "callback_data" => "verify_device"]]
                ]
            ])
        ]);
    }
}

// HANDLE BUTTON CLICK (Callback Query)
if (isset($update["callback_query"])) {
    $chatId = $update["callback_query"]["message"]["chat"]["id"];
    $callbackId = $update["callback_query"]["id"];
    $userId = $update["callback_query"]["from"]["id"];
    $ip = $_SERVER['REMOTE_ADDR']; // Get user's IP address

    // Show "Verifying..." image
    sendTelegram("sendPhoto", [
        "chat_id" => $chatId,
        "photo" => $bgImageUrl,
        "caption" => "🛡️ Device Verifying..."
    ]);

    sleep(2); // Fake delay for effect

    // Check if IP already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE ip_address = ?");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $message = "⚠️ Same Device Detected\nYou Can Still Refer & Earn ✅";
    } else {
        // Save new IP to database
        $stmt = $db->prepare("INSERT INTO users (telegram_id, ip_address) VALUES (?, ?)");
        $stmt->bind_param("ss", $userId, $ip);
        $stmt->execute();
        $message = "✅ Device Verified Successfully";
    }

    // Send result back to user
    sendTelegram("sendMessage", [
        "chat_id" => $chatId,
        "text" => $message
    ]);
}
?>
