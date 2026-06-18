<?php
/**
 * vnpay_ipn.php — Instant Payment Notification
 * VNPay gọi URL này ở phía server để xác nhận giao dịch (không qua trình duyệt).
 * File này KHÔNG hiển thị giao diện, chỉ trả về JSON cho VNPay.
 */
require_once("config.php");
require_once("db_utils.php");

$db = new DB_UTILS();

// ── 1. Nhận tham số ──────────────────────────────────────────────────────────
$vnp_SecureHash = $_GET['vnp_SecureHash'] ?? '';
$inputData = $_GET;
unset($inputData['vnp_SecureHash']);
unset($inputData['vnp_SecureHashType']);

// ── 2. Tạo lại hash để xác minh chữ ký ─────────────────────────────────────
ksort($inputData);
$hashData = "";
foreach ($inputData as $key => $value) {
    $hashData .= urlencode($key) . "=" . urlencode($value) . "&";
}
$hashData = rtrim($hashData, "&");
$secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);

// ── 3. Xử lý kết quả ────────────────────────────────────────────────────────
header('Content-Type: application/json');

if ($secureHash !== $vnp_SecureHash) {
    // Chữ ký sai → từ chối
    echo json_encode(['RspCode' => '97', 'Message' => 'Invalid signature']);
    exit;
}

$orderId = $inputData['vnp_TxnRef'] ?? '';
$responseCode = $inputData['vnp_ResponseCode'] ?? '';
$vnpAmount = (int) ($inputData['vnp_Amount'] ?? 0); // Đơn vị ×100

// Kiểm tra đơn hàng tồn tại trong DB
$order = $db->getOne("SELECT * FROM orders WHERE order_id = ?", [$orderId]);

if (!$order) {
    echo json_encode(['RspCode' => '01', 'Message' => 'Order not found']);
    exit;
}

// Kiểm tra số tiền khớp (VNPay gửi amount × 100)
if ((int) $order['total'] * 100 !== $vnpAmount) {
    echo json_encode(['RspCode' => '04', 'Message' => 'Invalid amount']);
    exit;
}

// Tránh xử lý lại đơn đã được cập nhật
if ($order['status'] === 'Đã thanh toán') {
    echo json_encode(['RspCode' => '02', 'Message' => 'Order already confirmed']);
    exit;
}

// Cập nhật trạng thái theo kết quả giao dịch
if ($responseCode === '00') {
    $db->execute(
        "UPDATE orders SET status = 'Đã thanh toán' WHERE order_id = ?",
        [$orderId]
    );
    echo json_encode(['RspCode' => '00', 'Message' => 'Confirm Success']);
} else {
    $db->execute(
        "UPDATE orders SET status = 'Thanh toán thất bại' WHERE order_id = ?",
        [$orderId]
    );
    echo json_encode(['RspCode' => '00', 'Message' => 'Confirm Fail acknowledged']);
}
exit;