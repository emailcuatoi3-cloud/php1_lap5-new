<?php
session_start();
require "./db_utils.php";
require_once "./pusher_helper.php";
$db_untils = new DB_UTILS();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => '🔒 Bạn cần đăng nhập để gửi tin nhắn!']);
    exit();
}

// Khởi tạo bảng lưu trữ lịch sử tin nhắn tự động nếu chưa có
$db_untils->execute("CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sender_id` INT NOT NULL,
  `receiver_id` INT NOT NULL,
  `message_text` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$input = json_decode(file_get_contents('php://input'), true);
$receiver_id = (int)($input['receiver_id'] ?? 0);
$message_text = trim($input['message_text'] ?? '');
$sender_id = (int)$_SESSION['user']['id'];

if (empty($message_text) || $receiver_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Nội dung trống!']);
    exit();
}

$sql = "INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)";
$res = $db_untils->execute($sql, [$sender_id, $receiver_id, $message_text]);

if ($res) {
    $pusher_data = [
        'sender_id' => $sender_id,
        'receiver_id' => $receiver_id,
        'message_text' => htmlspecialchars($message_text),
        'time' => date('H:i')
    ];

    try {
        broadcastRealtime('chat-message-event', $pusher_data);
    } catch (Exception $e) {}

    echo json_encode(['status' => 'success', 'data' => $pusher_data]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối dữ liệu.']);
}
exit();