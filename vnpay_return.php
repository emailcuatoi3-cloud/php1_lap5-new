<?php
session_start();
require "./db_utils.php";
require_once "./pusher_helper.php";
$db_untils = new DB_UTILS();

$vnp_HashSecret = "326YDVX0RIDOJECUKHS4RIZ6C9RTQIGH"; // Chuỗi khóa bí mật đồng bộ của bạn

$vnp_SecureHash = $_GET['vnp_SecureHash'] ?? '';
$inputData = array();
foreach ($_GET as $key => $value) {
    if (substr($key, 0, 4) == "vnp_") {
        $inputData[$key] = $value;
    }
}
unset($inputData['vnp_SecureHash']);
ksort($inputData);

$i = 0;
$hashData = "";
foreach ($inputData as $key => $value) {
    if ($i == 1) {
        $hashData = $hashData . '&' . urlencode($key) . "=" . urlencode($value);
    } else {
        $hashData = $hashData . urlencode($key) . "=" . urlencode($value);
        $i = 1;
    }
}

$secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);

$orderId = (int)($_GET['vnp_TxnRef'] ?? 0);
$amount = ($_GET['vnp_Amount'] ?? 0) / 100;
$responseCode = $_GET['vnp_ResponseCode'] ?? '99';
$vnp_Token = $_GET['vnp_Token'] ?? null; 
$vnp_CardNo = $_GET['vnp_CardNo'] ?? ''; 

$payment_status = "Thất bại";
$alert_class = "alert-danger";
$message = "Giao dịch thanh toán trực tuyến qua VNPAY đã bị thất bại hoặc hủy bỏ.";

if ($secureHash === $vnp_SecureHash) {
    if ($responseCode == '00') {
        $payment_status = "Thành công";
        $alert_class = "alert-success";
        $message = "🎉 Thanh toán thành công! Đơn hàng #" . $orderId . " đã được hệ thống ghi nhận.";

        // 1. Cập nhật cơ sở dữ liệu
        $db_untils->execute("UPDATE orders SET status = 'Chờ xác nhận' WHERE id = ?", [$orderId]);

        // 2. Kiểm tra nếu có Tokenization thì lưu lại để kích hoạt 1-Click
        $current_user = $_SESSION['user']['id'] ?? null;
        if ($current_user && !empty($vnp_Token)) {
            $masked_account = "********" . substr($vnp_CardNo, -4);
            $sql_save_token = "INSERT INTO user_tokens (user_id, payment_method, token_id, masked_account) VALUES (?, 'vnpay', ?, ?)
                               ON DUPLICATE KEY UPDATE token_id = ?, masked_account = ?";
            $db_untils->execute($sql_save_token, [$current_user, $vnp_Token, $masked_account, $vnp_Token, $masked_account]);
        }

        // 3. 🚀 PHÁT TÍN HIỆU WEBSOCKET SANG ADMIN NGAY LẬP TỨC 
        $order = $db_untils->getOne("SELECT * FROM orders WHERE id = ?", [$orderId]);
        if ($order) {
            $details = $db_untils->getAll("SELECT od.*, p.mota FROM order_details od JOIN products p ON od.product_id = p.maSP WHERE od.order_id = ?", [$orderId]);
            $product_titles = [];
            foreach ($details as $item) {
                $product_titles[] = "- " . $item['mota'] . " (SL: <strong>" . $item['quantity'] . "</strong>)";
            }

            broadcastRealtime('new-order-event', [
                'id' => $orderId,
                'fullname' => htmlspecialchars($order['fullname']),
                'phone' => htmlspecialchars($order['phone']),
                'address' => htmlspecialchars($order['address']),
                'payment_method' => "VNPAY",
                'total_money' => number_format($order['total_money']),
                'status' => 'Chờ xác nhận',
                'products_html' => implode('<br>', $product_titles)
            ]);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Kết quả giao dịch VNPAY</title>
    <link rel="stylesheet" href="./style.css?v=<?= time() ?>">
    <style>
    .vnpay-box {
        max-width: 600px;
        margin: 60px auto;
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
        text-align: center;
    }

    .alert-success {
        color: #15803d;
        background: #f0fdf4;
        padding: 12px;
        border-radius: 6px;
        font-weight: bold;
        margin-bottom: 20px;
    }

    .alert-danger {
        color: #b91c1c;
        background: #fef2f2;
        padding: 12px;
        border-radius: 6px;
        font-weight: bold;
        margin-bottom: 20px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
        text-align: left;
    }

    table td {
        padding: 12px 10px;
        border-bottom: 1px solid #e2e8f0;
    }
    </style>
</head>

<body>
    <div class="vnpay-box">
        <h2 style="color: #005baa;">🔵 KẾT QUẢ GIAO DỊCH VNPAY</h2>
        <div class="<?= $alert_class ?>"><?= $message ?></div>
        <table>
            <tr>
                <td>Mã hóa đơn:</td>
                <td><strong>#<?= htmlspecialchars($orderId) ?></strong></td>
            </tr>
            <tr>
                <td>Số tiền:</td>
                <td style="color:#dc2626; font-weight:bold;"><?= number_format((int)$amount) ?> đ</td>
            </tr>
            <tr>
                <td>Trạng thái:</td>
                <td style="font-weight:bold; color: <?= ($payment_status === 'Thành công') ? '#16a34a' : '#dc2626' ?>">
                    <?= $payment_status ?></td>
            </tr>
        </table>
        <a href="lap4.php" class="btn"
            style="background: #005baa; color:white; padding:10px 20px; text-decoration:none; border-radius:4px; display:inline-block; font-weight:bold;">🏠
            Quay lại cửa hàng</a>
    </div>
</body>

</html>