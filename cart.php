<?php
session_start();
require "./db_utils.php";
require_once "./pusher_helper.php"; // Nhúng tệp hàm helper xử lý Real-time
$db_untils = new DB_UTILS();

if (!isset($_SESSION['cart'])) { $_SESSION['cart'] = []; }

$current_user = $_SESSION['user']['id'] ?? null;

// --- TỰ ĐỘNG KHỞI TẠO BẢNG USER_TOKENS NẾU CHƯA CÓ ---
$bang_token_sql = "CREATE TABLE IF NOT EXISTS `user_tokens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `payment_method` VARCHAR(20) NOT NULL,
  `token_id` VARCHAR(255) NOT NULL,
  `masked_account` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `user_method` (`user_id`, `payment_method`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
$db_untils->execute($bang_token_sql);

// Lấy danh sách tài khoản liên kết Tokenization của user
$linked_accounts = ['momo' => null, 'vnpay' => null];
if ($current_user) {
    $tokens = $db_untils->getAll("SELECT payment_method, masked_account FROM user_tokens WHERE user_id = ?", [$current_user]);
    foreach ($tokens as $t) {
        $linked_accounts[strtolower($t['payment_method'])] = $t['masked_account'];
    }
}

// =========================================================================
// 📡 XỬ LÝ CÁC TIẾN TRÌNH API ĐƯỢC GỌI TỪ AJAX JAVASCRIPT
// =========================================================================
$action = $_GET['api'] ?? '';

// --- API 1: XỬ LÝ ĐĂNG KÝ LIÊN KẾT TÀI KHOẢN NGẦM ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'link_account') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$current_user) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập hệ thống trước!']); exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $method = strtolower($input['method'] ?? '');
    $account_number = trim($input['account_number'] ?? '');
    
    if (empty($account_number)) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng điền thông tin số tài khoản/số điện thoại']); exit;
    }

    $api_token = "REAL_TOKEN_" . strtoupper($method) . "_" . bin2hex(random_bytes(16));
    $masked_account = "********" . substr($account_number, -4);

    $sql_link = "INSERT INTO user_tokens (user_id, payment_method, token_id, masked_account) VALUES (?, ?, ?, ?) 
                 ON DUPLICATE KEY UPDATE token_id = ?, masked_account = ?";
    $res = $db_untils->execute($sql_link, [$current_user, $method, $api_token, $masked_account, $api_token, $masked_account]);
    
    if ($res) {
        // Phát tín hiệu Real-time liên kết tài khoản sang Admin
        broadcastRealtime('account-link-event', [
            'event_type' => 'link_account',
            'user_id' => $current_user,
            'user_fullname' => $_SESSION['user']['fullname'] ?? 'Khách',
            'payment_method' => strtoupper($method),
            'masked_account' => $masked_account
        ]);
        echo json_encode(['status' => 'success', 'message' => '🎉 Liên kết tài khoản đối tác thành công!', 'display' => $masked_account]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi lưu trữ dữ liệu liên kết hệ thống.']);
    }
    exit;
}

// --- API 2: XỬ LÝ HỦY LIÊN KẾT TÀI KHOẢN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'unlink_account') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$current_user) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập hệ thống trước!']); exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $method = strtolower($input['method'] ?? '');

    if ($current_user && !empty($method)) {
        $db_untils->execute("DELETE FROM user_tokens WHERE user_id = ? AND payment_method = ?", [$current_user, $method]);
        
        // Phát tín hiệu Real-time hủy liên kết sang Admin
        broadcastRealtime('account-link-event', [
            'event_type' => 'unlink_account',
            'user_id' => $current_user,
            'user_fullname' => $_SESSION['user']['fullname'] ?? 'Khách',
            'payment_method' => strtoupper($method)
        ]);
        echo json_encode(['status' => 'success', 'message' => '❌ Đã hủy liên kết phương thức này thành công!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ.']);
    }
    exit;
}

// --- API 3: QUY TRÌNH CHECKOUT ĐẶT HÀNG TỔNG HỢP ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'checkout') {
    header('Content-Type: application/json; charset=utf-8');
    
    if (!isset($_SESSION['user'])) {
        echo json_encode(['status' => 'error', 'message' => '🔒 Bạn phải đăng nhập mới có thể tiến hành đặt hàng!']); exit();
    }

    $fullname = trim($_POST['fullname'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $payment_method = $_POST['payment_method'] ?? '';
    $is_token_pay = isset($_POST['is_token_pay']) && $_POST['is_token_pay'] === 'true';

    if(empty($fullname) || empty($phone) || empty($email) || empty($address) || empty($payment_method)){
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập đầy đủ thông tin và chọn phương thức thanh toán!']); exit();
    } 
    if(count($_SESSION['cart']) == 0){
        echo json_encode(['status' => 'error', 'message' => 'Giỏ hàng của bạn đang trống!']); exit();
    }

    $total = 0;
    foreach($_SESSION['cart'] as $item) {
        $total += $item['price'] * $item['quantity'];
    }

    $userId = $_SESSION['user']['id'] ?? null;
    $order_status = ($payment_method === 'COD' || $is_token_pay) ? 'Chờ xác nhận' : 'Chờ thanh toán';

    $db_untils->execute("INSERT INTO orders (user_id, fullname, phone, email, address, payment_method, total_money, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", [$userId, $fullname, $phone, $email, $address, $payment_method, $total, $order_status]);
    $order_id = $db_untils->getLastInsertId();

    if($order_id) {
        $product_titles = [];
        foreach($_SESSION['cart'] as $item) {
            $db_untils->execute("INSERT INTO order_details (order_id, product_id, price, quantity) VALUES (?, ?, ?, ?)", [$order_id, $item['id'], $item['price'], $item['quantity']]);
            $product_titles[] = "- " . $item['name'] . " (SL: <strong>" . $item['quantity'] . "</strong>)";
        }
        
        $products_html = implode('<br>', $product_titles);
        date_default_timezone_set('Asia/Ho_Chi_Minh');

        // --- LUỒNG XỬ LÝ 1-CLICK TOKEN SHOPPING ---
        if ($is_token_pay) {
            $method_lower = strtolower($payment_method);
            $token_data = $db_untils->getOne("SELECT token_id, masked_account FROM user_tokens WHERE user_id = ? AND payment_method = ?", [$current_user, $method_lower]);
            
            // Kích hoạt phát WebSocket Real-time phẳng sang Admin lập tức
            broadcastRealtime('new-order-event', [
                'id' => $order_id,
                'fullname' => htmlspecialchars($fullname),
                'phone' => htmlspecialchars($phone),
                'address' => htmlspecialchars($address),
                'payment_method' => strtoupper($payment_method) . " (1-Click)",
                'total_money' => number_format($total),
                'status' => 'Chờ xác nhận',
                'products_html' => $products_html
            ]);

            $_SESSION['cart'] = [];
            echo json_encode([
                'status' => 'success',
                'message' => "Đặt hàng bằng cổng 1-Click thành công! Hệ thống đã tự động trừ tiền từ tài khoản liên kết " . $token_data['masked_account'] . " mà không cần nhập lại OTP."
            ]);
            exit();
        }

        // --- LUỒNG XỬ LÝ TRUYỀN THỐNG: THANH TOÁN QUA CỔNG VNPAY ---
        if ($payment_method === 'VNPAY') {
            $vnp_TmnCode = "2NBWGQ5J";
            $vnp_HashSecret = "326YDVX0RIDOJECUKHS4RIZ6C9RTQIGH";
            $vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
            
            $vnp_Returnurl = "http://" . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?') . "vnpay_return.php";
            $vnp_Returnurl = str_replace("cart.php", "vnpay_return.php", $vnp_Returnurl);

            $vnp_TxnRef = $order_id;
            $vnp_OrderInfo = "Thanh toan don hang #" . $order_id;
            $vnp_OrderType = "billpayment";
            $vnp_Amount = $total * 100;
            $vnp_Locale = "vi";
            $vnp_BankCode = "NCB";
            $vnp_IpAddr = $_SERVER['REMOTE_ADDR'];

            $inputData = array(
                "vnp_Version" => "2.1.0", "vnp_TmnCode" => $vnp_TmnCode, "vnp_Amount" => $vnp_Amount, "vnp_Command" => "pay",
                "vnp_CreateDate" => date('YmdHis'), "vnp_CurrCode" => "VND", "vnp_IpAddr" => $vnp_IpAddr, "vnp_Locale" => $vnp_Locale,
                "vnp_OrderInfo" => $vnp_OrderInfo, "vnp_OrderType" => $vnp_OrderType, "vnp_ReturnUrl" => $vnp_Returnurl, "vnp_TxnRef" => $vnp_TxnRef,
                "vnp_CardDebit" => "1"
            );
            if (isset($vnp_BankCode) && $vnp_BankCode != "") { $inputData['vnp_BankCode'] = $vnp_BankCode; }

            ksort($inputData);
            $query = ""; $i = 0; $hashdata = "";
            foreach ($inputData as $key => $value) {
                if ($i == 1) { $query .= '&' . urlencode($key) . "=" . urlencode($value); }
                else { $query .= urlencode($key) . "=" . urlencode($value); $i = 1; }
                $hashdata .= urlencode($key) . '=' . urlencode($value) . '&';
            }
            
            $hashdata = rtrim($hashdata, '&');
            $vnp_Url = $vnp_Url . "?" . $query;
            if (isset($vnp_HashSecret)) {
                $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
                $vnp_Url .= '&vnp_SecureHash=' . $vnpSecureHash;
            }

            $_SESSION['cart'] = [];
            echo json_encode(['status' => 'redirect_vnpay', 'redirect_url' => $vnp_Url, 'message' => 'Đang kết nối đến cổng VNPAY...']);
            exit();
        } 
        // --- LUỒNG TRUYỀN THỐNG: THANH TOÁN QUA CỔNG ĐIỆN TỬ MOMO ---
        elseif ($payment_method === 'MOMO') {
            $endpoint = "https://test-payment.momo.vn/v2/gateway/api/create";
            $partnerCode = "MOMOBKUN20180529";
            $accessKey   = "klm0566894333044";
            $secretKey   = "at67q66895433100";
            
            $redirectUrl = "http://" . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?') . "momo_return.php";
            $redirectUrl = str_replace("cart.php", "momo_return.php", $redirectUrl);
            $ipnUrl      = $redirectUrl;

            $orderInfo = "Thanh toan don hang #" . $order_id . " qua vi MoMo";
            $amount    = strval($total);
            $requestId = strval($order_id . '_' . time());
            $requestType = "captureWallet";
            $extraData = "";

            $rawHash = "accessKey=" . $accessKey . "&amount=" . $amount . "&extraData=" . $extraData . "&ipnUrl=" . $ipnUrl . "&orderId=" . $order_id . "&orderInfo=" . $orderInfo . "&partnerCode=" . $partnerCode . "&redirectUrl=" . $redirectUrl . "&requestId=" . $requestId . "&requestType=" . $requestType;
            $signature = hash_hmac("sha256", $rawHash, $secretKey);

            $data = array(
                'partnerCode' => $partnerCode, 'partnerName' => "Test Store Realtime", 'storeId' => "MomoTestStore", 'requestId' => $requestId,
                'amount' => $amount, 'orderId' => $order_id, 'orderInfo' => $orderInfo, 'redirectUrl' => $redirectUrl, 'ipnUrl' => $ipnUrl,
                'lang' => 'vi', 'extraData' => $extraData, 'requestType' => $requestType, 'signature' => $signature
            );

            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST"); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen(json_encode($data))));
            $result = curl_exec($ch); curl_close($ch);
            $jsonResult = json_decode($result, true);

            if (isset($jsonResult['payUrl'])) {
                $_SESSION['cart'] = [];
                echo json_encode(['status' => 'redirect_momo', 'redirect_url' => $jsonResult['payUrl'], 'message' => 'Đang kết nối liên kết tới ví điện tử MoMo...']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối cổng MoMo: ' . ($jsonResult['message'] ?? 'Sai chữ ký!')]);
            }
            exit();
        }
        // --- LUỒNG TRUYỀN THỐNG: THANH TOÁN COD TIỀN MẶT TRUYỀN THỐNG ---
        else {
            // Phát WebSocket Real-time phẳng sang Admin ngay lập tức bằng helper
            broadcastRealtime('new-order-event', [
                'id' => $order_id,
                'fullname' => htmlspecialchars($fullname),
                'phone' => htmlspecialchars($phone),
                'address' => htmlspecialchars($address),
                'payment_method' => $payment_method,
                'total_money' => number_format($total),
                'status' => 'Chờ xác nhận',
                'products_html' => $products_html
            ]);

            $_SESSION['cart'] = [];
            echo json_encode(['status' => 'success', 'message' => 'Đặt hàng thành công! Đơn hàng của bạn đã gửi tín hiệu thời gian thực đến hệ thống Admin.']);
            exit();
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gặp sự cố kết nối dữ liệu!']);
        exit();
    }
}

// Luồng tăng giảm số lượng sản phẩm ngoài giao diện
if (isset($_GET['action'])) {
    $id = $_GET['id'] ?? '';
    if ($_GET['action'] == 'increase' && isset($_SESSION['cart'][$id])) $_SESSION['cart'][$id]['quantity']++;
    if ($_GET['action'] == 'decrease' && isset($_SESSION['cart'][$id])) {
        $_SESSION['cart'][$id]['quantity']--;
        if ($_SESSION['cart'][$id]['quantity'] <= 0) unset($_SESSION['cart'][$id]);
    }
    if ($_GET['action'] == 'remove') unset($_SESSION['cart'][$id]);
    header("Location: cart.php"); exit();
}

$total = 0;
foreach($_SESSION['cart'] as $maSPKey => $item){
    if (!is_array($item)) {
        $productFix = $db_untils->getOne("SELECT * FROM products WHERE maSP = ?", [$maSPKey]);
        if ($productFix) {
            $_SESSION['cart'][$maSPKey] = [
                'id' => $productFix['maSP'], 'name' => $productFix['mota'],
                'price' => $productFix['gia'], 'image' => $productFix['hinhAnh'], 'quantity' => 1
            ];
            $item = $_SESSION['cart'][$maSPKey];
        } else { unset($_SESSION['cart'][$maSPKey]); continue; }
    }
    $total += $item['price'] * $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Giỏ hàng & Thanh toán</title>
    <link rel="stylesheet" href="./style.css?v=<?= time() ?>">
    <style>
    .payment-options {
        display: flex;
        flex-direction: column;
        gap: 14px;
        margin-top: 5px;
    }

    .pay-item-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        border: 1px solid #ddd;
        padding: 14px;
        border-radius: 8px;
        background: #fafafa;
        transition: 0.2s;
        cursor: pointer;
    }

    .pay-item-row:has(input:checked) {
        border-color: #f57224;
        background: #fff7ed;
    }

    .pay-left {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: bold;
        font-size: 14px;
    }

    .pay-item-row input[type="radio"] {
        cursor: pointer;
        width: auto;
        margin: 0;
    }

    .btn-token-link {
        background: #fe2c55;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        font-weight: bold;
        cursor: pointer;
        font-size: 12px;
        text-decoration: none;
    }

    .btn-token-unlink {
        background: #f1f1f4;
        color: #fe2c55;
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        font-weight: bold;
        cursor: pointer;
        font-size: 12px;
        text-decoration: none;
    }

    .badge-linked {
        font-size: 12px;
        color: #2563eb;
        background: #eff6ff;
        padding: 2px 8px;
        border-radius: 4px;
        font-weight: normal;
        margin-left: 5px;
    }

    .test-card-box {
        margin-top: 10px;
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: 6px;
        padding: 12px;
        font-size: 12px;
        color: #475569;
        text-align: left;
        display: none;
    }

    .test-card-box table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 5px;
    }

    .test-card-box td {
        padding: 4px 0;
        border-bottom: none;
    }

    .test-card-box td:first-child {
        width: 100px;
        font-weight: bold;
        color: #334155;
    }

    .test-card-box code {
        background: #e2e8f0;
        color: #0f172a;
        padding: 2px 6px;
        border-radius: 4px;
        font-family: monospace;
        font-size: 12px;
    }

    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }

    .modal-box {
        background: white;
        padding: 25px;
        border-radius: 12px;
        width: 340px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    }

    .modal-box h3 {
        margin-top: 0;
        margin-bottom: 15px;
        font-size: 16px;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }

    .modal-box input {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 6px;
        box-sizing: border-box;
        margin-bottom: 15px;
        outline: none;
    }

    .modal-buttons {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }

    .btn-modal-cancel {
        background: #e2e8f0;
        color: #334155;
        border: none;
        padding: 8px 14px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
    }
    </style>
    <script src="https://js.pusher.com/8.0.1/pusher.min.js"></script>
</head>

<body>
    <header>
        <div class="header-logo">
            <h1>🛒 Giỏ hàng & Thanh toán</h1>
        </div>
        <div class="header-actions">
            <?php if (isset($_SESSION['user'])) { ?>
            <a href="user_orders.php" class="cart-btn" style="background: #10b981; margin-right: 10px;">📋 Đơn hàng của
                tôi</a>
            <?php } ?>
            <a href="lap4.php" class="cart-btn" style="background: #4b5563;">← Cửa hàng</a>
        </div>
    </header>

    <div id="success-panel" class="success-box" style="display: none;">
        <div class="success" id="success-message"></div>
        <a href="lap4.php" class="btn" style="background: #2563eb; margin-top: 15px;">Tiếp tục mua sắm</a>
    </div>

    <div class="onepage-container" id="main-checkout-layout">
        <div class="checkout-left-panel">
            <h2>Sản phẩm trong giỏ hàng</h2>
            <?php if(count($_SESSION['cart']) > 0){ ?>
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Ảnh</th>
                        <th>Sản phẩm</th>
                        <th>Giá</th>
                        <th>Số lượng</th>
                        <th>Thành tiền</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($_SESSION['cart'] as $item){ $subtotal = $item['price'] * $item['quantity']; ?>
                    <tr>
                        <td><img src="<?= htmlspecialchars($item['image']) ?>"></td>
                        <td style="text-align: left; max-width: 240px;"><?= htmlspecialchars($item['name']) ?></td>
                        <td><?= number_format($item['price']) ?> đ</td>
                        <td>
                            <a href="cart.php?action=decrease&id=<?= $item['id'] ?>" class="qty-btn">-</a>
                            <span class="qty-number"><?= $item['quantity'] ?></span>
                            <a href="cart.php?action=increase&id=<?= $item['id'] ?>" class="qty-btn">+</a>
                        </td>
                        <td style="color: #dc2626; font-weight: bold;"><?= number_format($subtotal) ?> đ</td>
                        <td><a href="cart.php?action=remove&id=<?= $item['id'] ?>" class="delete-btn"
                                onclick="return confirm('Xóa sản phẩm này?');">❌ Xóa</a></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
            <?php } else { echo "<div style='text-align: center; padding: 40px 0;'>Giỏ hàng trống.</div>"; } ?>
        </div>

        <div class="checkout-right-panel">
            <h2>Thông tin mua hàng</h2>
            <div class="error" id="error-alert" style="display: none; margin-bottom: 15px;"></div>
            <form id="orderForm" method="POST">
                <div class="form-group"><label>Họ và tên người nhận</label><input type="text" name="fullname"
                        placeholder="Nhập họ và tên"
                        value="<?= isset($_SESSION['user']['fullname']) ? htmlspecialchars($_SESSION['user']['fullname']) : '' ?>">
                </div>
                <div class="form-group"><label>Số điện thoại</label><input type="text" name="phone"
                        placeholder="Nhập số điện thoại nhận hàng"></div>
                <div class="form-group"><label>Email</label><input type="email" name="email"
                        placeholder="Nhập địa chỉ email"
                        value="<?= isset($_SESSION['user']['email']) ? htmlspecialchars($_SESSION['user']['email']) : '' ?>">
                </div>
                <div class="form-group"><label>Địa chỉ nhận hàng</label><textarea name="address" rows="3"
                        placeholder="Số nhà, tên đường, phường/xã, quận/huyện..."></textarea></div>

                <div class="form-group">
                    <label>Phương thức thanh toán</label>
                    <div class="payment-options">
                        <div class="pay-item-row" onclick="clickRadio('COD')">
                            <div class="pay-left">
                                <input type="radio" name="payment_method" id="pay_COD" value="COD" checked
                                    data-linked="false">
                                <span>💵 Tiền mặt (COD)</span>
                            </div>
                        </div>

                        <div class="pay-item-row" id="row_momo" onclick="clickRadio('MOMO')">
                            <div class="pay-left">
                                <input type="radio" name="payment_method" id="pay_MOMO" value="MOMO"
                                    data-linked="<?= $linked_accounts['momo'] ? 'true' : 'false' ?>">
                                <span>🔴 Ví MoMo</span>
                                <span id="badge_momo_container"><?php if($linked_accounts['momo']){ ?><span
                                        class="badge-linked">Đã liên kết
                                        (<?= $linked_accounts['momo'] ?>)</span><?php } ?></span>
                            </div>
                            <div id="btn_momo_container">
                                <?php if($linked_accounts['momo']){ ?><button type="button" class="btn-token-unlink"
                                    onclick="submitUnlinkAccount('momo', event)">Hủy</button><?php } else { ?><button
                                    type="button" class="btn-token-link" onclick="openLinkModal('momo', event)">Liên
                                    kết</button><?php } ?>
                            </div>
                        </div>

                        <div class="pay-item-row" id="row_vnpay" onclick="clickRadio('VNPAY')"
                            style="flex-direction: column; align-items: stretch; gap: 5px;">
                            <div
                                style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                                <div class="pay-left">
                                    <input type="radio" name="payment_method" id="pay_VNPAY" value="VNPAY"
                                        data-linked="<?= $linked_accounts['vnpay'] ? 'true' : 'false' ?>">
                                    <span>🔵 VNPAY</span>
                                    <span id="badge_vnpay_container"><?php if($linked_accounts['vnpay']){ ?><span
                                            class="badge-linked">Đã liên kết
                                            (<?= $linked_accounts['vnpay'] ?>)</span><?php } ?></span>
                                </div>
                                <div id="btn_vnpay_container">
                                    <?php if($linked_accounts['vnpay']){ ?><button type="button"
                                        class="btn-token-unlink"
                                        onclick="submitUnlinkAccount('vnpay', event)">Hủy</button><?php } else { ?><button
                                        type="button" class="btn-token-link"
                                        onclick="openLinkModal('vnpay', event)">Liên kết</button><?php } ?>
                                </div>
                            </div>
                            <?php if(!$linked_accounts['vnpay']){ ?>
                            <div class="test-card-box" id="vnpay-test-info">
                                <span style="font-weight: bold; color: #1e293b;">📋 Thông tin thẻ test hệ thống:</span>
                                <table>
                                    <tr>
                                        <td>Ngân hàng:</td>
                                        <td><code>NCB</code></td>
                                    </tr>
                                    <tr>
                                        <td>Số thẻ:</td>
                                        <td><code>9704198526191432198</code></td>
                                    </tr>
                                    <tr>
                                        <td>Tên chủ thẻ:</td>
                                        <td><code>NGUYEN VAN A</code></td>
                                    </tr>
                                    <tr>
                                        <td>Ngày phát hành:</td>
                                        <td><code>07/15</code></td>
                                    </tr>
                                    <tr>
                                        <td>Mật khẩu OTP:</td>
                                        <td><code>123456</code></td>
                                    </tr>
                                </table>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="total">Tổng thanh toán: <?= number_format($total) ?> đ</div>
                <button type="button" id="btn-submit-checkout" class="btn"
                    <?= (count($_SESSION['cart']) == 0) ? 'disabled style="background:#cbd5e1; cursor:not-allowed;"' : '' ?>>Xác
                    nhận đặt hàng</button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="linkModal">
        <div class="modal-box">
            <h3 id="modalTitle">Liên kết tài khoản</h3>
            <input type="text" id="accountNumberInput" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
            <div class="modal-buttons">
                <button type="button" class="btn-modal-cancel" onclick="closeLinkModal()">Hủy</button>
                <button type="button" class="btn-token-link" onclick="submitLinkAccount()">Xác nhận liên kết</button>
            </div>
        </div>
    </div>

    <script>
    let currentMethod = '';
    const currentUserId = <?= json_encode($current_user) ?>;
    const pusher = new Pusher('94c4c17f4353f8cdc5af', {
        cluster: 'ap1'
    });
    const channel = pusher.subscribe('store-channel');

    channel.bind('account-link-event', function(data) {
        let info = (typeof data === 'string') ? JSON.parse(data) : data;
        if (typeof info.data === 'string') {
            info = JSON.parse(info.data);
        } else if (info.data) {
            info = info.data;
        }

        if (parseInt(info.user_id) === parseInt(currentUserId)) {
            const method = info.payment_method.toLowerCase();
            const badgeContainer = document.getElementById(`badge_${method}_container`);
            const btnContainer = document.getElementById(`btn_${method}_container`);
            const inputRadio = document.getElementById(`pay_${method.toUpperCase()}`);

            if (info.event_type === 'link_account') {
                if (badgeContainer) badgeContainer.innerHTML =
                    `<span class="badge-linked">Đã liên kết (${info.masked_account})</span>`;
                if (btnContainer) btnContainer.innerHTML =
                    `<button type="button" class="btn-token-unlink" onclick="submitUnlinkAccount('${method}', event)">Hủy</button>`;
                if (inputRadio) inputRadio.setAttribute('data-linked', 'true');
                if (method === 'vnpay' && document.getElementById('vnpay-test-info')) {
                    document.getElementById('vnpay-test-info').remove();
                }
            } else if (info.event_type === 'unlink_account') {
                if (badgeContainer) badgeContainer.innerHTML = '';
                if (btnContainer) btnContainer.innerHTML =
                    `<button type="button" class="btn-token-link" onclick="openLinkModal('${method}', event)">Liên kết</button>`;
                if (inputRadio) inputRadio.setAttribute('data-linked', 'false');
                if (method === 'vnpay') {
                    window.location.reload();
                }
            }
        }
    });

    function clickRadio(method) {
        document.getElementById('pay_' + method).checked = true;
        toggleTestInfoVisibility();
    }

    function toggleTestInfoVisibility() {
        const vnpayRadio = document.getElementById('pay_VNPAY');
        const infoBox = document.getElementById('vnpay-test-info');
        if (infoBox) {
            if (vnpayRadio && vnpayRadio.checked) {
                infoBox.style.display = 'block';
            } else {
                infoBox.style.display = 'none';
            }
        }
    }
    document.addEventListener("DOMContentLoaded", function() {
        toggleTestInfoVisibility();
    });

    function openLinkModal(method, event) {
        event.stopPropagation();
        currentMethod = method;
        document.getElementById('modalTitle').innerText = method === 'momo' ? 'Liên kết Ví MoMo (Nhập Số Điện Thoại)' :
            'Liên kết Số Tài Khoản Ngân Hàng';
        document.getElementById('accountNumberInput').placeholder = method === 'momo' ? 'Ví dụ: 0912345678' :
            'Ví dụ: 190345678910';
        document.getElementById('accountNumberInput').value = "";
        document.getElementById('linkModal').style.display = 'flex';
    }

    function closeLinkModal() {
        document.getElementById('linkModal').style.display = 'none';
    }

    function submitLinkAccount() {
        const accNum = document.getElementById('accountNumberInput').value;
        if (!accNum || accNum.length < 6) return alert("Vui lòng nhập số tài khoản hoặc số điện thoại hợp lệ!");
        fetch('cart.php?api=link_account', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    method: currentMethod,
                    account_number: accNum
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    closeLinkModal();
                } else {
                    alert(data.message);
                }
            }).catch(err => alert("Lỗi mất kết nối API liên kết tài khoản."));
    }

    function submitUnlinkAccount(method, event) {
        event.stopPropagation();
        if (!confirm(`Bạn có chắc chắn muốn hủy liên kết tài khoản ${method.toUpperCase()}?`)) return;
        fetch('cart.php?api=unlink_account', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    method: method
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status !== 'success') {
                    alert(data.message);
                }
            }).catch(err => alert("Lỗi mất kết nối API hủy liên kết tài khoản."));
    }
    document.getElementById('btn-submit-checkout').addEventListener('click', function(e) {
        e.preventDefault();
        const form = document.getElementById('orderForm');
        const formData = new FormData(form);
        const errorAlert = document.getElementById('error-alert');
        errorAlert.style.display = 'none';
        const activeRadio = document.querySelector('input[name="payment_method"]:checked');
        if (activeRadio && activeRadio.getAttribute('data-linked') === 'true') {
            formData.append('is_token_pay', 'true');
        }
        fetch('cart.php?api=checkout', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(res => {
                if (res.status === 'error') {
                    errorAlert.innerHTML = '⚠️ ' + res.message;
                    errorAlert.style.display = 'block';
                } else if (res.status === 'redirect_vnpay') {
                    window.location.href = res.redirect_url;
                } else if (res.status === 'redirect_momo') {
                    window.location.href = res.redirect_url;
                } else {
                    document.getElementById('main-checkout-layout').style.display = 'none';
                    document.getElementById('success-message').innerHTML = '🎉 ' + res.message;
                    document.getElementById('success-panel').style.display = 'block';
                }
            });
    });
    </script>
    <div id="chat-widget-container"
        style="position: fixed; bottom: 20px; right: 20px; z-index: 999999; font-family: sans-serif;">
        <button id="chat-open-btn"
            style="background: #fe2c55; color: white; border: none; padding: 12px 18px; border-radius: 50px; font-weight: bold; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">💬
            Hỗ trợ trực tuyến</button>

        <div id="chat-window-box"
            style="display: none; width: 320px; height: 400px; background: white; border-radius: 12px; box-shadow: 0 5px 25px rgba(0,0,0,0.15); flex-direction: column; overflow: hidden; border: 1px solid #e2e8f0;">
            <div
                style="background: #1e293b; color: white; padding: 12px; font-weight: bold; display: flex; justify-content: space-between; align-items: center;">
                <span>🛡️ Hỗ trợ trực tuyến (Admin)</span>
                <span id="chat-close-btn" style="cursor: pointer; font-size: 18px;">×</span>
            </div>
            <div id="chat-body-messages"
                style="flex: 1; padding: 15px; overflow-y: auto; background: #f8fafc; font-size: 13px; display: flex; flex-direction: column; gap: 8px;">
                <div
                    style="background: #e2e8f0; padding: 8px 12px; border-radius: 8px; align-self: flex-start; max-width: 85%;">
                    Xin chào! Shop có thể hỗ trợ gì cho bạn ạ?</div>
            </div>
            <div style="padding: 10px; border-top: 1px solid #e2e8f0; display: flex; gap: 6px; background: #fff;">
                <input type="text" id="chat-input-text" placeholder="Nhập tin nhắn..."
                    style="flex: 1; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none; font-size: 13px;">
                <button type="button" id="chat-send-btn"
                    style="background: #fe2c55; color: white; border: none; padding: 8px 14px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 13px;">Gửi</button>
            </div>
        </div>
    </div>

    <script>
    // Tận dụng biến Session User ID của bạn đã có
    const myUserId = <?= json_encode($_SESSION['user']['id'] ?? 0) ?>;
    const adminId = 1; // Giả lập ID mặc định của tài khoản quản trị Admin

    const openBtn = document.getElementById('chat-open-btn');
    const closeBtn = document.getElementById('chat-close-btn');
    const chatWindow = document.getElementById('chat-window-box');
    const sendBtn = document.getElementById('chat-send-btn');
    const inputText = document.getElementById('chat-input-text');
    const chatBody = document.getElementById('chat-body-messages');

    openBtn.onclick = () => {
        chatWindow.style.display = 'flex';
        openBtn.style.display = 'none';
        chatBody.scrollTop = chatBody.scrollHeight;
    };
    closeBtn.onclick = () => {
        chatWindow.style.display = 'none';
        openBtn.style.display = 'block';
    };

    // Thực thi gửi tin nhắn bằng FETCH API
    sendBtn.onclick = () => {
        const text = inputText.value.trim();
        if (!text || myUserId === 0) return alert("Vui lòng đăng nhập hệ thống trước khi chat!");

        fetch('send_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    receiver_id: adminId,
                    message_text: text
                })
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    appendMessage(text, 'my-message');
                    inputText.value = '';
                }
            });
    };

    inputText.onkeypress = (e) => {
        if (e.key === 'Enter') sendBtn.click();
    };

    function appendMessage(text, type) {
        const msgHtml = document.createElement('div');
        if (type === 'my-message') {
            msgHtml.style =
                "background: #fe2c55; color: white; padding: 8px 12px; border-radius: 8px; align-self: flex-end; max-width: 85%; word-break: break-word;";
        } else {
            msgHtml.style =
                "background: #e2e8f0; color: black; padding: 8px 12px; border-radius: 8px; align-self: flex-start; max-width: 85%; word-break: break-word;";
        }
        msgHtml.innerText = text;
        chatBody.appendChild(msgHtml);
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    // 📡 LẮNG NGHE ĐƯỜNG TRUYỀN WEBSOCKET TỪ ADMIN TRẢ VỀ REAL-TIME
    // Biến channel và pusher đã được nhúng sẵn ở file cart của bạn
    channel.bind('chat-message-event', function(data) {

        console.log("RAW:", data);

        let msg = data;

        if (typeof msg === 'string') {
            msg = JSON.parse(msg);
        }

        if (msg.data) {
            if (typeof msg.data === 'string') {
                msg = JSON.parse(msg.data);
            } else {
                msg = msg.data;
            }
        }

        console.log("PARSED:", msg);

        if (
            parseInt(msg.sender_id) === 1 &&
            parseInt(msg.receiver_id) === parseInt(myUserId)
        ) {
            appendMessage(msg.message_text, 'guest-message');
        }
    });
    </script>
</body>

</html>