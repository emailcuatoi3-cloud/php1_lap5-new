<?php
session_start();
require "./db_utils.php";
require_once "./pusher_helper.php";
$db_untils = new DB_UTILS();

$secretKey = "at67q66895433100";

$partnerCode = $_GET['partnerCode'] ?? '';
$orderId = $_GET['orderId'] ?? '';
$requestId = $_GET['requestId'] ?? '';
$amount = $_GET['amount'] ?? '';
$orderInfo = $_GET['orderInfo'] ?? '';
$orderType = $_GET['orderType'] ?? '';
$transId = $_GET['transId'] ?? '';
$resultCode = $_GET['resultCode'] ?? '99';
$message_momo = $_GET['message'] ?? '';
$extraData = $_GET['extraData'] ?? '';

$rawHash = "accessKey=klm0566894333044" .
           "&amount=" . $amount .
           "&extraData=" . $extraData .
           "&message=" . $message_momo .
           "&orderId=" . $orderId .
           "&orderInfo=" . $orderInfo .
           "&partnerCode=" . $partnerCode .
           "&requestId=" . $requestId .
           "&requestType=captureWallet" .  
           "&resultCode=" . $resultCode .
           "&transId=" . $transId;

$momo_Signature = $_GET['signature'] ?? '';
$checksum = hash_hmac("sha256", $rawHash, $secretKey);

$payment_status = "Thất bại";
$alert_class = "alert-danger";
$message = "Giao dịch thanh toán qua Ví điện tử MoMo đã bị thất bại hoặc bị hủy bỏ.";

if ($checksum === $momo_Signature) {
    if ($resultCode == '0') {
        $db_untils->execute("UPDATE orders SET status = 'Chờ xác nhận' WHERE id = ?", [(int)$orderId]);
        $payment_status = "Thành công";
        $alert_class = "alert-success";
        $message = "🎉 Thanh toán thành công qua ví MoMo!";

        // 🚀 KÍCH HOẠT PHÁT REAL-TIME CHUẨN PHẲNG SANG ADMIN LẬP TỨC
        $order = $db_untils->getOne("SELECT * FROM orders WHERE id = ?", [(int)$orderId]);
        if($order) {
            $details = $db_untils->getAll("SELECT od.*, p.mota FROM order_details od JOIN products p ON od.product_id = p.maSP WHERE od.order_id = ?", [(int)$orderId]);
            $product_titles = [];
            foreach($details as $item) {
                $product_titles[] = "- " . $item['mota'] . " (SL: <strong>" . $item['quantity'] . "</strong>)";
            }

            broadcastRealtime('new-order-event', [
                'id' => (int)$orderId,
                'fullname' => htmlspecialchars($order['fullname']),
                'phone' => htmlspecialchars($order['phone']),
                'address' => htmlspecialchars($order['address']),
                'payment_method' => "Ví MoMo",
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
    <title>Kết quả giao dịch Ví MoMo</title>
    <link rel="stylesheet" href="./style.css?v=<?= time() ?>">
    <style>
    .momo-box {
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
    <div class="momo-box">
        <h2 style="color: #a50064;">🔴 KẾT QUẢ GIAO DỊCH VÍ MOMO</h2>
        <div class="<?= $alert_class ?>"><?= $message ?></div>
        <table>
            <tr>
                <td>Mã đơn hàng:</td>
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
            style="background: #a50064; color:white; padding:10px 20px; text-decoration:none; border-radius:4px; display:inline-block; font-weight:bold;">🏠
            Quay lại cửa hàng</a>
    </div>
</body>

</html>