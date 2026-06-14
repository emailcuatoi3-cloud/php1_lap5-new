<?php
session_start();
require "./db_utils.php";
$db_untils = new DB_UTILS();

// Lấy mã đơn hàng từ URL (?id=...)
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id <= 0) {
    die("<h3 style='text-align:center; margin-top:50px; color:#ef4444;'>Mã đơn hàng không hợp lệ!</h3>");
}

// Lấy thông tin tổng quan của đơn hàng
$order = $db_untils->getOne("SELECT * FROM orders WHERE id = ?", [$order_id]);

if (!$order) {
    die("<h3 style='text-align:center; margin-top:50px; color:#ef4444;'>Không tìm thấy đơn hàng #{$order_id} trên hệ thống!</h3>");
}

// Bảo mật: Nếu là khách thường (User), chỉ cho phép xem đơn hàng của chính mình
$isAdmin = isset($_SESSION['user']) && ($_SESSION['user']['role'] === 'admin' || $_SESSION['user']['username'] === 'admin');
if (!$isAdmin) {
    if (!isset($_SESSION['user']) || $_SESSION['user']['id'] != $order['user_id']) {
        die("<h3 style='text-align:center; margin-top:50px; color:#ef4444;'>Bạn không có quyền xem hành trình của đơn hàng này!</h3>");
    }
}

// Lấy danh sách sản phẩm thuộc đơn hàng
$details = $db_untils->getAll("
    SELECT od.*, p.mota, p.hinhAnh 
    FROM order_details od 
    JOIN products p ON od.product_id = p.maSP 
    WHERE od.order_id = ?
", [$order_id]);

// Giả lập mốc thời gian dựa theo trạng thái thực tế để tạo Timeline chi tiết như ảnh mẫu
$order_time = strtotime($order['created_at']);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theo dõi kiện hàng #<?= $order['id'] ?></title>
    <link rel="stylesheet" href="./style.css?v=<?= time() ?>">
    <style>
    .track-container {
        max-width: 700px;
        margin: 30px auto;
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }

    .track-title {
        font-size: 20px;
        font-weight: bold;
        border-bottom: 2px solid #e5e7eb;
        padding-bottom: 15px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* CSS TIMELINE ĐỨNG PHONG CÁCH ĐIỆN THOẠI (GIỐNG ẢNH MẪU) */
    .timeline {
        position: relative;
        margin: 20px 0;
        padding-left: 30px;
    }

    .timeline::before {
        content: '';
        position: absolute;
        left: 5px;
        top: 10px;
        width: 2px;
        height: 90%;
        background: #e5e7eb;
    }

    .timeline-item {
        position: relative;
        margin-bottom: 30px;
        opacity: 0.5;
        transition: 0.3s;
    }

    .timeline-item.active {
        opacity: 1;
    }

    .timeline-icon {
        position: absolute;
        left: -30px;
        top: 4px;
        width: 12px;
        height: 12px;
        background: #cbd5e1;
        border-radius: 50%;
        border: 2px solid white;
        box-shadow: 0 0 0 2px #cbd5e1;
    }

    .timeline-item.active .timeline-icon {
        background: #10b981;
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.2);
    }

    .timeline-item.cancelled .timeline-icon {
        background: #ef4444;
        box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.2);
    }

    .timeline-time {
        font-size: 13px;
        color: #6b7280;
        margin-bottom: 3px;
        font-weight: 500;
    }

    .timeline-content {
        background: #f9fafb;
        padding: 15px;
        border-radius: 8px;
        border: 1px solid #f3f4f6;
    }

    .timeline-content h4 {
        font-size: 15px;
        color: #1f2937;
        margin-bottom: 5px;
        font-weight: bold;
    }

    .timeline-content p {
        font-size: 13px;
        color: #4b5563;
        line-height: 1.4;
    }

    /* Khung hiển thị tóm tắt sản phẩm đã mua */
    .mini-product-list {
        margin-top: 25px;
        background: #f8fafc;
        padding: 15px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }

    .mini-item {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 10px;
        padding-bottom: 10px;
        border-bottom: 1px solid #edf2f7;
    }

    .mini-item:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }

    .mini-item img {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
    }
    </style>
</head>

<body>

    <header>
        <div class="header-logo">
            <h1>📦 Theo dõi trạng thái kiện hàng</h1>
        </div>
        <div class="header-actions">
            <a href="<?= $isAdmin ? 'admin_orders.php' : 'user_orders.php' ?>" class="cart-btn"
                style="background: #4b5563;">← Quay lại</a>
        </div>
    </header>

    <div class="track-container">
        <div class="track-title">
            <span>Mã vận đơn: <span style="color:#2563eb;">#<?= $order['id'] ?></span></span>
            <span style="font-size: 14px; color:#6b7280;">Hình thức:
                <strong><?= $order['payment_method'] ?></strong></span>
        </div>

        <div
            style="font-size:14px; background:#eff6ff; padding:12px; border-radius:6px; color:#1e40af; margin-bottom:25px;">
            📍 <strong>Địa chỉ nhận hàng:</strong> <?= htmlspecialchars($order['fullname']) ?> |
            <?= htmlspecialchars($order['phone']) ?><br>
            <span style="font-size:13px; color:#4b5563; display:inline-block; margin-top:4px;">🏡
                <?= htmlspecialchars($order['address']) ?></span>
        </div>

        <h3>Hành trình đơn hàng</h3>

        <div class="timeline">

            <?php if($order['status'] === 'Đã nhận') { ?>
            <div class="timeline-item active">
                <div class="timeline-icon"></div>
                <div class="timeline-time"><?= date('d/m/Y H:i', $order_time + 86400) ?></div>
                <div class="timeline-content">
                    <h4>🎉 Giao hàng thành công</h4>
                    <p>Kiện hàng đã được giao đến tay quý khách. Cảm ơn bạn đã tin tưởng mua sắm tại Store!</p>
                </div>
            </div>
            <?php } ?>

            <?php if($order['status'] === 'Đang giao' || $order['status'] === 'Đã nhận') { ?>
            <div class="timeline-item active">
                <div class="timeline-icon"></div>
                <div class="timeline-time"><?= date('d/m/Y H:i', $order_time + 3600) ?></div>
                <div class="timeline-content">
                    <h4>🚚 Đang trên đường giao</h4>
                    <p>Đơn vị vận chuyển của hệ thống đã tiếp nhận kiện hàng và đang tiến hành giao đến địa chỉ của bạn.
                    </p>
                </div>
            </div>
            <?php } ?>

            <?php if($order['status'] === 'Đã hủy') { ?>
            <div class="timeline-item active cancelled">
                <div class="timeline-icon"></div>
                <div class="timeline-time"><?= date('d/m/Y H:i') ?></div>
                <div class="timeline-content" style="background:#fef2f2; border-color:#fca5a5;">
                    <h4 style="color:#dc2626;">❌ Đơn hàng đã hủy</h4>
                    <p style="color:#991b1b;"><strong>Thông tin chi tiết:</strong>
                        <?= !empty($order['ly_do_huy']) ? htmlspecialchars($order['ly_do_huy']) : 'Hủy theo yêu cầu của hệ thống.' ?>
                    </p>
                </div>
            </div>
            <?php } ?>

            <?php if($order['status'] !== 'Chờ xác nhận') { ?>
            <div class="timeline-item active">
                <div class="timeline-icon"></div>
                <div class="timeline-time"><?= date('d/m/Y H:i', $order_time + 600) ?></div>
                <div class="timeline-content">
                    <h4>✔ Đã xác nhận đơn hàng</h4>
                    <p>Hệ thống đã phê duyệt kiểm tra thông tin. Đơn hàng đang được đóng gói và chuẩn bị bàn giao vận
                        chuyển.</p>
                </div>
            </div>
            <?php } ?>

            <div class="timeline-item active">
                <div class="timeline-icon"></div>
                <div class="timeline-time"><?= date('d/m/Y H:i', $order_time) ?></div>
                <div class="timeline-content">
                    <h4>📝 Đặt hàng thành công</h4>
                    <p>Đơn hàng đã được ghi nhận trên hệ thống. Tổng số tiền thanh toán:
                        <strong><?= number_format($order['total_money']) ?> đ</strong>.
                    </p>
                </div>
            </div>

        </div>

        <div class="mini-product-list">
            <h4 style="margin-bottom: 10px; color:#475569;">Kiện hàng gồm có:</h4>
            <?php foreach($details as $item) { ?>
            <div class="mini-item">
                <img src="<?= htmlspecialchars($item['hinhAnh']) ?>" alt="">
                <div style="flex:1;">
                    <div style="font-size:14px; font-weight:bold;"><?= htmlspecialchars($item['mota']) ?></div>
                    <div style="font-size:12px; color:#64748b; margin-top:2px;">Số lượng:
                        <strong><?= $item['quantity'] ?></strong> | Giá: <?= number_format($item['price']) ?> đ
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>

    </div>
</body>

</html>