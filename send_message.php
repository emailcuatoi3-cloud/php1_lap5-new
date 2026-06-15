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

$input = json_decode(file_get_contents('php://input'), true);
$receiver_id = (int)($input['receiver_id'] ?? 0);
$message_text = trim($input['message_text'] ?? '');
$sender_id = (int)$_SESSION['user']['id'];

if (empty($message_text) || $receiver_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Nội dung trống!']);
    exit();
}

// 1. Lưu tin nhắn gốc của khách hàng vào Database
$sql = "INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)";
$res = $db_untils->execute($sql, [$sender_id, $receiver_id, $message_text]);

if ($res) {
    // Phát tín hiệu tin nhắn của khách sang trang quản trị của Admin trực tuyến
    $pusher_data = [
        'sender_id' => $sender_id,
        'receiver_id' => $receiver_id,
        'message_text' => htmlspecialchars($message_text),
        'time' => date('H:i')
    ];
    try { broadcastRealtime('chat-message-event', $pusher_data); } catch (Exception $e) {}

    // =========================================================================
    // 🛡️ CHỨC NĂNG NÂNG CẤP: CHATBOT THÔNG MINH TỰ ĐỘNG PHÂN TÍCH KHOẢNG GIÁ
    // =========================================================================
    $bot_reply_text = "";
    
    // Chuẩn hóa tin nhắn: loại bỏ dấu chấm phân cách hàng nghìn (ví dụ: 5.000 -> 5000) và chữ "đ", "k" để dễ bắt Regex
    $clean_msg = str_replace(['.', 'đ', 'Đ'], '', strtolower($message_text));
    // Thay thế chữ "k" viết tắt thành hàng nghìn (ví dụ: 50k -> 50000)
    $clean_msg = preg_replace_callback('/(\d+)k/', function($matches) {
        return $matches[1] * 1000;
    }, $clean_msg);

    $min_price = null;
    $max_price = null;

    // Regex 1: Tìm cấu hình khoảng giá "từ X đến Y" hoặc "X - Y" hoặc "X đến Y"
    if (preg_replace('/[^0-9]/', '', $clean_msg) !== '') {
        if (preg_match('/(?:từ\s*)?(\d+)\s*(?:đến|-|->|\s+)\s*(\d+)/', $clean_msg, $matches)) {
            $min_price = (float)$matches[1];
            $max_price = (float)$matches[2];
        } 
        // Regex 2: Tìm cấu hình giá trần đơn lẻ như "dưới X", "nhỏ hơn X"
        elseif (preg_match('/(?:dưới|nhỏ hơn|rẻ hơn)\s*(\d+)/', $clean_msg, $matches)) {
            $min_price = 0;
            $max_price = (float)$matches[1];
        }
        // Regex 3: Tìm cấu hình giá sàn đơn lẻ như "trên X", "lớn hơn X", "cao hơn X"
        elseif (preg_match('/(?:trên|lớn hơn|cao hơn|từ)\s*(\d+)/', $clean_msg, $matches)) {
            $min_price = (float)$matches[1];
            $max_price = 999999999; // Giá trị trần vô cực
        }
    }

    // Nếu bóc tách Regex tìm thấy khoảng giá hợp lệ, tiến hành quét tìm sản phẩm trong kho
    if ($min_price !== null && $max_price !== null) {
        // Hoán đổi vị trí nếu người dùng gõ ngược (Ví dụ: từ 1000000 đến 5000)
        if ($min_price > $max_price) {
            $temp = $min_price; $min_price = $max_price; $max_price = $temp;
        }

        // Truy vấn tìm các sản phẩm thỏa mãn điều kiện khoảng giá
        $products = $db_untils->getAll("SELECT * FROM products WHERE gia >= ? AND gia <= ? LIMIT 5", [$min_price, $max_price]);

        if (count($products) > 0) {
            $bot_reply_text = "🎉 Dạ có ạ! Website hiện đang có " . count($products) . " sản phẩm phù hợp trong khoảng từ " . number_format($min_price) . "đ đến " . number_format($max_price) . "đ dành cho bạn:<br><div class='bot-prod-container'>";
            
            foreach ($products as $p) {
                $bot_reply_text .= "
                <div class='bot-prod-card'>
                    <img src='" . htmlspecialchars($p['hinhAnh']) . "' class='bot-prod-img'>
                    <div class='bot-prod-info'>
                        <div class='bot-prod-name'>" . htmlspecialchars($p['mota']) . "</div>
                        <div class='bot-prod-price'>" . number_format($p['gia']) . " đ</div>
                        <a href='lap4.php?detail=" . $p['maSP'] . "' target='_blank' class='bot-prod-link'>Xem chi tiết</a>
                    </div>
                </div>";
            }
            $bot_reply_text .= "</div>";
        } else {
            $bot_reply_text = "😢 Dạ tiếc quá, hiện tại website không có sản phẩm nào nằm trong khoảng giá từ " . number_format($min_price) . "đ đến " . number_format($max_price) . "đ rồi ạ. Bạn có muốn tìm khoảng giá khác không?";
        }
    }

    // 🤖 GỬI TIN NHẮN PHẢN HỒI TỰ ĐỘNG CỦA CHATBOT NẾU ĐIỀU KIỆN ĐƯỢC KÍCH HOẠT
    if (!empty($bot_reply_text)) {
        $admin_user_id = 1; // Mặc định ID tài khoản Chatbot phản hồi thay mặt Admin
        
        // Lưu tin nhắn phản hồi của Bot vào Database
        $db_untils->execute("INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)", [$admin_user_id, $sender_id, $bot_reply_text]);
        
        // Phát tín hiệu WebSocket phản hồi của Chatbot ngược lại cho Khách hàng thấy ngay lập tức
        $bot_pusher_data = [
            'sender_id' => $admin_user_id,
            'receiver_id' => $sender_id,
            'message_text' => $bot_reply_text, // Giữ nguyên mã HTML thẻ sản phẩm để giao diện tự dựng
            'time' => date('H:i')
        ];
        try { broadcastRealtime('chat-message-event', $bot_pusher_data); } catch (Exception $e) {}
    }

    echo json_encode(['status' => 'success', 'data' => $pusher_data]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối dữ liệu.']);
}
exit();