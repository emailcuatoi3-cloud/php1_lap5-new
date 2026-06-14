<?php
session_start();
require "./db_utils.php";
require_once "./pusher_helper.php"; // Nhúng tệp hàm helper xử lý Real-time tập trung
$db_untils = new DB_UTILS();

// 🔒 Chặn truy cập nếu không có quyền admin
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] !== 'admin' && $_SESSION['user']['username'] !== 'admin')) {
    die("<h2 style='color:red; text-align:center; margin-top:50px;'>Bạn không có quyền truy cập trang quản lý đơn hàng!</h2>");
}

// Thực hiện thay đổi trạng thái tiến độ đơn hàng
if (isset($_GET['update_status']) && isset($_GET['order_id'])) {
    $order_id = (int)$_GET['order_id'];
    $new_status = $_GET['update_status'];
    
    if (in_array($new_status, ['Chờ xác nhận', 'Đang giao', 'Đã nhận', 'Đã hủy'])) {
        $db_untils->execute("UPDATE orders SET status = ? WHERE id = ?", [$new_status, $order_id]);
        
        // 📡 Gọi tệp helper phát tín hiệu WebSocket cập nhật trạng thái đơn hàng sang phía Khách hàng
        broadcastRealtime('update_status', [
            'event_type' => 'update_status',
            'event_source' => 'admin_panel',
            'id' => $order_id,
            'status' => $new_status
        ]);
    }
    header("Location: admin_orders.php"); exit();
}

$orders = $db_untils->getAll("SELECT * FROM orders ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Hệ thống Đơn hàng - Admin Store</title>
    <link rel="stylesheet" href="./style.css?v=<?= time() ?>">
    <style>
    .admin-box {
        max-width: 1400px;
        margin: 20px auto;
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
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

    .action-links a {
        text-decoration: none;
        font-size: 13px;
        font-weight: bold;
        margin-right: 8px;
        padding: 4px 10px;
        border-radius: 4px;
        display: inline-block;
    }

    .btn-confirm {
        background: #2563eb;
        color: white;
    }

    .btn-ship {
        background: #10b981;
        color: white;
    }

    .btn-cancel {
        background: #ef4444;
        color: white;
    }

    @keyframes highlightFlash {
        0% {
            background-color: #fff7ed;
        }

        50% {
            background-color: #ffedd5;
        }

        100% {
            background-color: #ffffff;
        }
    }

    .new-websocket-row {
        animation: highlightFlash 3s ease-out forwards;
    }

    .toast-notification-box {
        position: fixed;
        bottom: 25px;
        left: 25px;
        background: #1e293b;
        color: white;
        padding: 15px 25px;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        display: none;
        z-index: 99999;
        font-weight: bold;
        font-size: 14px;
        border-left: 5px solid #fe2c55;
    }
    </style>
    <script src="https://js.pusher.com/8.0.1/pusher.min.js"></script>
</head>

<body>
    <audio id="live-audio" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-600.wav" preload="auto"></audio>

    <div class="toast-notification-box" id="global-toast-alert"></div>

    <header>
        <div class="header-logo">
            <h1>⚙️ Hệ thống Quản trị Đơn hàng Real-time (Pusher)</h1>
        </div>
        <div class="header-actions"><a href="lap4.php" class="cart-btn" style="background: #4b5563;">← Trang chủ
                shop</a></div>
    </header>

    <div class="admin-box">
        <table class="cart-table">
            <thead>
                <tr>
                    <th>Mã ĐH</th>
                    <th>Thông tin khách nhận</th>
                    <th>Hình thức</th>
                    <th>Tổng tiền</th>
                    <th>Trạng thái đơn</th>
                    <th>Sản phẩm đặt</th>
                    <th>Thao tác xử lý đơn</th>
                </tr>
            </thead>
            <tbody id="orders-tbody-list">
                <?php foreach ($orders as $order) { 
                    $details = $db_untils->getAll("SELECT od.*, p.mota FROM order_details od JOIN products p ON od.product_id = p.maSP WHERE od.order_id = ?", [$order['id']]);
                    $badge = 'status-waiting';
                    if ($order['status'] == 'Đang giao') $badge = 'status-shipping';
                    if ($order['status'] == 'Đã nhận') $badge = 'status-completed';
                    if ($order['status'] == 'Đã hủy') $badge = 'status-cancelled';
                ?>
                <tr id="order_row_id_<?= $order['id'] ?>">
                    <td><strong>#<?= $order['id'] ?></strong></td>
                    <td style="text-align: left; font-size: 13px;">
                        👤 <strong><?= htmlspecialchars($order['fullname']) ?></strong><br>
                        📞 <?= htmlspecialchars($order['phone']) ?><br>
                        📍 <?= htmlspecialchars($order['address']) ?>
                    </td>
                    <td><span style="font-weight: bold; color:#4b5563;"><?= $order['payment_method'] ?></span></td>
                    <td style="color:#dc2626; font-weight:bold;"><?= number_format($order['total_money']) ?> đ</td>
                    <td><span class="status-badge <?= $badge ?>"
                            id="badge_status_text_<?= $order['id'] ?>"><?= $order['status'] ?></span></td>
                    <td style="text-align: left; font-size: 13px;">
                        <?php foreach($details as $d) { echo "- " . htmlspecialchars($d['mota']) . " (SL: <strong>" . $d['quantity'] . "</strong>)<br>"; } ?>
                    </td>
                    <td class="action-links" id="action_btn_container_<?= $order['id'] ?>">
                        <?php if($order['status'] == 'Chờ xác nhận') { ?>
                        <a href="admin_orders.php?order_id=<?= $order['id'] ?>&update_status=Đang giao"
                            class="btn-confirm" onclick="return confirm('Duyệt giao đơn này?')">✔ Xác nhận</a>
                        <a href="admin_orders.php?order_id=<?= $order['id'] ?>&update_status=Đã hủy" class="btn-cancel"
                            onclick="return confirm('Hủy đơn hàng?')">❌ Hủy</a>
                        <?php } elseif($order['status'] == 'Đang giao') { ?>
                        <a href="admin_orders.php?order_id=<?= $order['id'] ?>&update_status=Đã nhận" class="btn-ship"
                            onclick="return confirm('Đơn hàng đã giao thành công?')">📦 Đã nhận hàng</a>
                        <a href="admin_orders.php?order_id=<?= $order['id'] ?>&update_status=Đã hủy" class="btn-cancel"
                            onclick="return confirm('Hủy đơn hàng?')">❌ Hủy</a>
                        <?php } else { echo "<span style='color:#9ca3af; font-size:12px;'>Đơn hoàn thành</span>"; } ?>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <script>
    const pusher = new Pusher('94c4c17f4353f8cdc5af', {
        cluster: 'ap1'
    });
    const channel = pusher.subscribe('store-channel');

    // 📡 LUỒNG 1: LẮNG NGHE ĐƠN HÀNG MỚI NỔ VỀ
    channel.bind('new-order-event', function(data) {
        console.log("PUSHER RECEIVED", data);
        let payloadData = data;
        if (typeof data === 'string') {
            payloadData = JSON.parse(data);
        }

        let order = payloadData;
        if (payloadData.data) {
            order = (typeof payloadData.data === 'string') ? JSON.parse(payloadData.data) : payloadData.data;
        }

        if (!order || !order.id) return;
        if (document.getElementById('order_row_id_' + order.id)) return;

        const tbody = document.getElementById("orders-tbody-list");
        const newRow = document.createElement("tr");
        newRow.id = 'order_row_id_' + order.id;
        newRow.className = "new-websocket-row";

        newRow.innerHTML = `
            <td><strong>#${order.id}</strong></td>
            <td style="text-align: left; font-size: 13px;">
                👤 <strong>${order.fullname}</strong><br>
                📞 ${order.phone}<br>
                📍 ${order.address}
            </td>
            <td><span style="font-weight: bold; color:#4b5563;">${order.payment_method}</span></td>
            <td style="color:#dc2626; font-weight:bold;">${order.total_money} đ</td>
            <td><span class="status-badge status-waiting" id="badge_status_text_${order.id}">${order.status}</span></td>
            <td style="text-align: left; font-size: 13px;">${order.products_html}</td>
            <td class="action-links" id="action_btn_container_${order.id}">
                <a href="admin_orders.php?order_id=${order.id}&update_status=Đang giao" class="btn-confirm" onclick="return confirm('Duyệt giao đơn này?')">✔ Xác nhận</a>
                <a href="admin_orders.php?order_id=${order.id}&update_status=Đã hủy" class="btn-cancel" onclick="return confirm('Hủy đơn hàng?')">❌ Hủy</a>
            </td>
        `;

        if (tbody.firstChild) {
            tbody.insertBefore(newRow, tbody.firstChild);
        } else {
            tbody.appendChild(newRow);
        }

        playNotificationSound();
    });

    // 📡 LUỒNG 2: LẮNG NGHE KHÁCH HÀNG TỰ BẤM HỦY ĐƠN TỪ TRANG KHÁCH (USER_ORDERS.PHP)
    channel.bind('update_status', function(data) {
        let payloadData = data;
        if (typeof data === 'string') {
            payloadData = JSON.parse(data);
        }

        let updateInfo = payloadData;
        if (payloadData.data) {
            updateInfo = (typeof payloadData.data === 'string') ? JSON.parse(payloadData.data) : payloadData
                .data;
        }

        if (!updateInfo || !updateInfo.id) return;

        const badge = document.getElementById('badge_status_text_' + updateInfo.id);
        const btnContainer = document.getElementById('action_btn_container_' + updateInfo.id);

        if (badge) {
            badge.innerText = updateInfo.status;
            badge.className = "status-badge";
            if (updateInfo.status === 'Chờ xác nhận') badge.classList.add('status-waiting');
            if (updateInfo.status === 'Đang giao') badge.classList.add('status-shipping');
            if (updateInfo.status === 'Đã nhận') badge.classList.add('status-completed');
            if (updateInfo.status === 'Đã hủy') badge.classList.add('status-cancelled');
        }

        if (btnContainer) {
            if (updateInfo.status === 'Đang giao') {
                btnContainer.innerHTML = `
                    <a href="admin_orders.php?order_id=${updateInfo.id}&update_status=Đã nhận" class="btn-ship" onclick="return confirm('Đơn hàng đã giao thành công?')">📦 Đã nhận hàng</a>
                    <a href="admin_orders.php?order_id=${updateInfo.id}&update_status=Đã hủy" class="btn-cancel" onclick="return confirm('Hủy đơn hàng?')">❌ Hủy</a>
                `;
            } else {
                btnContainer.innerHTML = `<span style='color:#9ca3af; font-size:12px;'>Đơn hoàn thành</span>`;
            }
        }

        if (updateInfo.event_source === 'user_panel' && updateInfo.status === 'Đã hủy') {
            const toast = document.getElementById('global-toast-alert');
            toast.innerHTML = `⚠️ Chú ý: Đơn hàng #\${updateInfo.id} vừa bị Khách hàng hủy bỏ trực tuyến!`;
            toast.style.display = 'block';
            playNotificationSound();
            setTimeout(() => {
                toast.style.display = 'none';
            }, 5000);
        }
    });

    // 📡 LUỒNG 3: LẮNG NGHE SỰ KIỆN LIÊN KẾT TÀI KHOẢN
    channel.bind('account-link-event', function(data) {
        let payloadData = data;
        if (typeof data === 'string') {
            payloadData = JSON.parse(data);
        }

        let info = payloadData;
        if (payloadData.data) {
            info = (typeof payloadData.data === 'string') ? JSON.parse(payloadData.data) : payloadData.data;
        }

        const toast = document.getElementById('global-toast-alert');
        if (info.event_type === 'link_account') {
            toast.innerHTML =
                `🔔 Khách hàng ${info.user_fullname} vừa liên kết tài khoản ${info.payment_method} (${info.masked_account}) thành công!`;
            toast.style.borderLeftColor = "#10b981";
        } else {
            toast.innerHTML =
                `⚠️ Khách hàng ${info.user_fullname} vừa thực hiện HỦY liên kết tài khoản ${info.payment_method}!`;
            toast.style.borderLeftColor = "#ef4444";
        }
        toast.style.display = 'block';
        playNotificationSound();
        setTimeout(() => {
            toast.style.display = 'none';
        }, 5000);
    });

    function playNotificationSound() {
        try {
            document.getElementById("live-audio").play();
        } catch (e) {
            console.log("Yêu cầu tương tác nhấp chuột lên màn hình trước.");
        }
    }
    </script>
</body>

</html>