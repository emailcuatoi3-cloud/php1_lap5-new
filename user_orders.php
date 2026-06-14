<?php
session_start();
require "./db_utils.php";
require_once "./pusher_helper.php"; // Nhúng tệp hàm helper dùng chung
$db_untils = new DB_UTILS();

// Chặn truy cập nếu chưa đăng nhập
if (!isset($_SESSION['user'])) {
    header("Location: login.php"); exit();
}

$userId = $_SESSION['user']['id'];

// --- XỬ LÝ KHÁCH HÀNG TỰ HỦY ĐƠN HÀNG TRỰC TUYẾN ---
if (isset($_GET['action']) && $_GET['action'] === 'cancel_order' && isset($_GET['order_id'])) {
    $order_id = (int)$_GET['order_id'];
    
    // Kiểm tra tính hợp lệ bảo mật xem đơn hàng có thuộc về user này và đang chờ duyệt không
    $check_order = $db_untils->getOne("SELECT status FROM orders WHERE id = ? AND user_id = ?", [$order_id, $userId]);
    
    if ($check_order && $check_order['status'] === 'Chờ xác nhận') {
        $db_untils->execute("UPDATE orders SET status = 'Đã hủy' WHERE id = ?", [$order_id]);
        
        // 📡 BẮN WEBSOCKET REAL-TIME: Đồng bộ thông tin đơn hàng sang trang Admin lập tức
        broadcastRealtime('update_status', [
            'event_type' => 'update_status',
            'event_source' => 'user_panel', 
            'id' => $order_id,
            'status' => 'Đã hủy'
        ]);
    }
    header("Location: user_orders.php"); exit();
}

$orders = $db_untils->getAll("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC", [$userId]);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Đơn hàng của tôi - Store</title>
    <link rel="stylesheet" href="./style.css?v=<?= time() ?>">
    <style>
    .container {
        max-width: 1200px;
        margin: 30px auto;
        background: white;
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .status-badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: bold;
    }

    .status-waiting {
        background: #fef3c7;
        color: #d97706;
    }

    .status-shipping {
        background: #dbeafe;
        color: #2563eb;
    }

    .status-completed {
        background: #dcfce7;
        color: #166534;
    }

    .status-cancelled {
        background: #fee2e2;
        color: #991b1b;
    }

    .btn-cancel-order {
        background: #ef4444;
        color: white;
        padding: 4px 10px;
        border-radius: 4px;
        text-decoration: none;
        font-size: 12px;
        font-weight: bold;
    }

    .btn-cancel-order:hover {
        background: #dc2626;
    }
    </style>
    <script src="https://js.pusher.com/8.0.1/pusher.min.js"></script>
</head>

<body>

    <header>
        <div class="header-logo">
            <h1>📋 Đơn Hàng Của Bạn</h1>
        </div>
        <div class="header-actions">
            <span style="color:#febd69; font-size:14px; margin-right: 10px;">👤
                <strong><?= htmlspecialchars($_SESSION['user']['fullname']) ?></strong></span>
            <a href="lap4.php" class="cart-btn" style="background: #4b5563;">← Quay lại cửa hàng</a>
        </div>
    </header>

    <div class="container">
        <table class="cart-table">
            <thead>
                <tr>
                    <th>Mã ĐH</th>
                    <th>Người nhận</th>
                    <th>Hình thức</th>
                    <th>Tổng tiền</th>
                    <th>Trạng thái tiến độ</th>
                    <th>Chi tiết sản phẩm đặt mua</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody id="user-orders-tbody">
                <?php if(count($orders) > 0){
                    foreach ($orders as $order) { 
                        $details = $db_untils->getAll("SELECT od.*, p.mota FROM order_details od JOIN products p ON od.product_id = p.maSP WHERE od.order_id = ?", [$order['id']]);
                        $badge = 'status-waiting';
                        if ($order['status'] == 'Đang giao') $badge = 'status-shipping';
                        if ($order['status'] == 'Đã nhận') $badge = 'status-completed';
                        if ($order['status'] == 'Đã hủy') $badge = 'status-cancelled';
                ?>
                <tr id="user_order_row_<?= $order['id'] ?>">
                    <td><strong>#<?= $order['id'] ?></strong></td>
                    <td style="text-align: left; font-size: 13px;">
                        👤 <strong><?= htmlspecialchars($order['fullname']) ?></strong><br>
                        📍 <?= htmlspecialchars($order['address']) ?>
                    </td>
                    <td><code><?= $order['payment_method'] ?></code></td>
                    <td style="color:#dc2626; font-weight:bold;"><?= number_format($order['total_money']) ?> đ</td>
                    <td><span class="status-badge <?= $badge ?>"
                            id="user_badge_<?= $order['id'] ?>"><?= $order['status'] ?></span></td>
                    <td style="text-align: left; font-size: 13px;">
                        <?php foreach($details as $d) { echo "- " . htmlspecialchars($d['mota']) . " (SL: <strong>" . $d['quantity'] . "</strong>)<br>"; } ?>
                    </td>
                    <td id="user_action_container_<?= $order['id'] ?>">
                        <?php if($order['status'] === 'Chờ xác nhận'){ ?>
                        <a href="user_orders.php?action=cancel_order&order_id=<?= $order['id'] ?>"
                            class="btn-cancel-order"
                            onclick="return confirm('Bạn có chắc chắn muốn hủy đơn hàng này?')">❌ Hủy đơn</a>
                        <?php } else { echo "<span style='color:#9ca3af; font-size:12px;'>Không thể can thiệp</span>"; } ?>
                    </td>
                </tr>
                <?php } 
                } else { echo "<tr><td colspan='7' style='padding:30px; color:#64748b; font-weight:bold;'>Bạn chưa có lịch sử đặt mua đơn hàng nào.</td></tr>"; } ?>
            </tbody>
        </table>
    </div>

    <script>
    // Khởi chạy đồng bộ lắng nghe Websocket từ phía Admin
    const pusher = new Pusher('94c4c17f4353f8cdc5af', {
        cluster: 'ap1'
    });
    const channel = pusher.subscribe('store-channel');

    // 📡 LẮNG NGHE ADMIN DUYỆT ĐƠN / ĐỔI TRẠNG THÁI ĐỂ UI KHÁCH TỰ ĐỘNG THAY ĐỔI
    channel.bind('update_status', function(data) {
        let updateInfo = (typeof data === 'string') ? JSON.parse(data) : data;
        if (typeof updateInfo.data === 'string') {
            updateInfo = JSON.parse(updateInfo.data);
        } else if (updateInfo.data) {
            updateInfo = updateInfo.data;
        }

        if (!updateInfo || !updateInfo.id) return;

        const badge = document.getElementById('user_badge_' + updateInfo.id);
        const actionBox = document.getElementById('user_action_container_' + updateInfo.id);

        if (badge) {
            badge.innerText = updateInfo.status;
            badge.className = "status-badge";
            if (updateInfo.status === 'Chờ xác nhận') badge.classList.add('status-waiting');
            if (updateInfo.status === 'Đang giao') badge.classList.add('status-shipping');
            if (updateInfo.status === 'Đã nhận') badge.classList.add('status-completed');
            if (updateInfo.status === 'Đã hủy') badge.classList.add('status-cancelled');
        }

        // Nếu Admin đã chuyển trạng thái (Ví dụ duyệt "Đang giao"), tước quyền hủy đơn của khách ngay lập tức
        if (actionBox && updateInfo.status !== 'Chờ xác nhận') {
            actionBox.innerHTML = `<span style='color:#9ca3af; font-size:12px;'>Không thể can thiệp</span>`;
        }
    });
    </script>
</body>

</html>