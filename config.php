<?php
const HOST = 'localhost';
const USERNAME = 'root';
const DATABASE = 'php1_lap5tailop';
const PASSWORD = '';

date_default_timezone_set('Asia/Ho_Chi_Minh');
// define('SITE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/php1-2025/website');
// define('SITE_URL', '/php1-2025/website');
// define('USERNAME_EMAIL', 'dinhan27107@gmail.com'); // thay bằng email của các bạn
// define('PASSWORD_EMAIL', 'NTDA.27107'); // thay bằng password của các bạn
$vnp_TmnCode = "2NBWGQ5J"; // Lấy từ trang Sandbox
$vnp_HashSecret = "326YDVX0RIDOJECUKHS4RIZ6C9RTQIGH";
$vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
$vnp_Returnurl = "http://localhost/PHP1/php1_lap5tailop-main/vnpay_return.php"; // URL quay về sau thanh toán

$momo_PartnerCode = "MOMOXDEO20251204_TEST";
$momo_AccessKey = "FCdb8d54bqstOuX7";
$momo_SecretKey = "lkzyRA75mRnW6rw7ZqejEYPZMj8L081T";
$momo_Endpoint = "https://test-payment.momo.vn/v2/gateway/api/create";
$momo_ReturnUrl = "http://localhost/php1-main/baitapnhom/momo_return.php";
$momo_NotifyUrl = "http://localhost/php1-main/baitapnhom/momo_ipn.php";
?>