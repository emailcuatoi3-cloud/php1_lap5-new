<?php
session_start();
require_once("config.php");
require_once("db_utils.php");

$db = new DB_UTILS();

// ── 1. Lấy secure hash từ VNPay gửi về ──────────────────────────────────────
$vnp_SecureHash = $_GET['vnp_SecureHash'] ?? '';

// Tách hash ra khỏi mảng tham số trước khi kiểm tra
$inputData = $_GET;
unset($inputData['vnp_SecureHash']);
unset($inputData['vnp_SecureHashType']); // Một số phiên bản VNPay trả thêm key này

// ── 2. Tạo lại hash để xác minh chữ ký ─────────────────────────────────────
ksort($inputData);
$hashData = "";
foreach ($inputData as $key => $value) {
    $hashData .= urlencode($key) . "=" . urlencode($value) . "&";
}
$hashData = rtrim($hashData, "&");
$secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);

// ── 3. Kiểm tra chữ ký và phản hồi ─────────────────────────────────────────
$orderId = $inputData['vnp_TxnRef'] ?? '';
$responseCode = $inputData['vnp_ResponseCode'] ?? '';
$amount = isset($inputData['vnp_Amount']) ? (int) $inputData['vnp_Amount'] / 100 : 0;
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Kết Quả Thanh Toán VNPay</title>
    <style>
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
        font-family: 'Segoe UI', Arial, sans-serif;
    }

    body {
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        padding: 20px;
    }

    .card {
        background: white;
        max-width: 480px;
        width: 100%;
        padding: 40px 30px;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        text-align: center;
    }

    .icon {
        font-size: 56px;
        margin-bottom: 16px;
    }

    h2 {
        font-size: 22px;
        margin-bottom: 10px;
    }

    .info {
        background: #f8fafc;
        border-radius: 8px;
        padding: 14px;
        margin: 20px 0;
        text-align: left;
        font-size: 14px;
        line-height: 2;
    }

    .info span {
        font-weight: bold;
    }

    a.btn {
        display: inline-block;
        margin-top: 10px;
        padding: 12px 28px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: bold;
        font-size: 15px;
    }

    .btn-home {
        background: #2563eb;
        color: white;
    }

    .btn-order {
        background: #10b981;
        color: white;
        margin-left: 10px;
    }

    .success h2 {
        color: #059669;
    }

    .fail h2 {
        color: #dc2626;
    }

    .invalid h2 {
        color: #92400e;
    }
    </style>
</head>

<body>
    <div class="card">
        <?php if ($secureHash !== $vnp_SecureHash): ?>
        <!-- Chữ ký không hợp lệ -->
        <div class="invalid">
            <div class="icon">⚠️</div>
            <h2>Chữ ký không hợp lệ!</h2>
            <p style="color:#78716c; margin-top:8px;">Phản hồi từ VNPay có thể đã bị giả mạo hoặc thay đổi.</p>
        </div>

        <?php elseif ($responseCode === '00'): ?>

        <?php
$db->execute(
    "UPDATE orders SET status = 'Đã thanh toán' WHERE id = ?",
    [$orderId]
);
?>
        <div class="success">
            <div class="icon">✅</div>
            <h2>Thanh toán thành công!</h2>
            <div class="info">
                <div>Mã đơn hàng: <span><?= htmlspecialchars($orderId) ?></span></div>
                <div>Số tiền: <span><?= number_format($amount, 0, ',', '.') ?>đ</span></div>
                <div>Trạng thái: <span style="color:#059669">Đã thanh toán</span></div>
            </div>
        </div>

        <?php else: ?>

        <?php
$status = ($responseCode == '24')
    ? 'Đã hủy'
    : 'Thanh toán thất bại';

$db->execute(
    "UPDATE orders SET status = ? WHERE id = ?",
    [$status, $orderId]
);
?>
        <div class="fail">
            <div class="icon">❌</div>
            <h2>Thanh toán không thành công!</h2>
            <div class="info">
                <div>Mã đơn hàng: <span><?= htmlspecialchars($orderId) ?></span></div>
                <div>Mã lỗi VNPay: <span><?= htmlspecialchars($responseCode) ?></span></div>
                <div>Trạng thái: <span style="color:#dc2626">Thanh toán thất bại</span></div>
            </div>
            <p style="color:#78716c; font-size:14px;">Đơn hàng của bạn đã được lưu. Bạn có thể thử thanh toán lại trong
                trang quản lý đơn hàng.</p>
        </div>
        <?php endif; ?>

        <div style="margin-top: 20px;">
            <a href="index.php" class="btn btn-home">🏠 Trang chủ</a>
            <a href="user_orders.php" class="btn btn-order">📦 Đơn hàng của tôi</a>
        </div>
    </div>
</body>

</html>