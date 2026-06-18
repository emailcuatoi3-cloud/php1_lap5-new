<?php
session_start();
require_once("config.php"); // Chứa $vnp_TmnCode, $vnp_HashSecret, $vnp_Url

// Kiểm tra tham số đầu vào
if (empty($_GET['order_id']) || empty($_GET['amount'])) {
    die('Thiếu thông tin đơn hàng!');
}

$vnp_TxnRef = $_GET['order_id'];
$vnp_Amount = (int) $_GET['amount'] * 100; // VNPay yêu cầu nhân 100 (đơn vị VND × 100)
$vnp_OrderInfo = "Thanh toan don hang " . $vnp_TxnRef;

$inputData = [
    "vnp_Version" => "2.1.0",
    "vnp_TmnCode" => $vnp_TmnCode,
    "vnp_Amount" => $vnp_Amount,
    "vnp_Command" => "pay",
    "vnp_CreateDate" => date('YmdHis'),
    "vnp_CurrCode" => "VND",
    "vnp_IpAddr" => $_SERVER['REMOTE_ADDR'],
    "vnp_Locale" => "vn",
    "vnp_OrderInfo" => $vnp_OrderInfo,
    "vnp_OrderType" => "other",
    "vnp_ReturnUrl" => $vnp_Returnurl,   // Lấy từ config.php, không hardcode
    "vnp_TxnRef" => $vnp_TxnRef,
];

// Sắp xếp theo key trước khi tạo chuỗi hash (bắt buộc theo tài liệu VNPay)
ksort($inputData);

$hashdata = "";
$query = "";
foreach ($inputData as $key => $value) {
    $hashdata .= urlencode($key) . "=" . urlencode($value) . "&";
    $query .= urlencode($key) . "=" . urlencode($value) . "&";
}
$hashdata = rtrim($hashdata, "&");
$query = rtrim($query, "&");

$vnp_SecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
$paymentUrl = $vnp_Url . "?" . $query . "&vnp_SecureHash=" . $vnp_SecureHash;

header('Location: ' . $paymentUrl);
exit;