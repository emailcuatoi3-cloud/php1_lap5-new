<?php
session_start();
require "./db_utils.php";
$db_untils = new DB_UTILS();

// 🔒 Chặn truy cập nếu không phải admin
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] !== 'admin' && $_SESSION['user']['username'] !== 'admin')) {
    die("<h2 style='color:red; text-align:center; margin-top:50px;'>🔒 Bạn không có quyền truy cập vùng quản trị!</h2>");
}

// ==========================================
// 📊 1. THỐNG KÊ CƠ BẢN (Tổng số lượng)
// ==========================================
$total_products = $db_untils->getValue("SELECT COUNT(*) FROM products");
$total_orders   = $db_untils->getValue("SELECT COUNT(*) FROM orders WHERE status != 'Đã hủy'");
$total_users    = $db_untils->getValue("SELECT COUNT(*) FROM users");

// ==========================================
// 💰 2. THỐNG KÊ DOANH THU (Theo ngày, Theo tháng)
// ==========================================
$revenue_today = $db_untils->getValue("
    SELECT SUM(total_money) 
    FROM orders 
    WHERE DATE(created_at) = CURRENT_DATE() AND status != 'Đã hủy'
");
$revenue_today = $revenue_today ? $revenue_today : 0;

$revenue_month = $db_untils->getValue("
    SELECT SUM(total_money) 
    FROM orders 
    WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
      AND YEAR(created_at) = YEAR(CURRENT_DATE()) 
      AND status != 'Đã hủy'
");
$revenue_month = $revenue_month ? $revenue_month : 0;

// ==========================================
// 👥 3. THỐNG KÊ KHÁCH HÀNG (Mua nhiều nhất / Ít nhất)
// ==========================================
$top_buyer = $db_untils->getOne("
    SELECT fullname, phone, SUM(total_money) as total_spent, COUNT(id) as total_orders
    FROM orders 
    WHERE status != 'Đã hủy'
    GROUP BY phone, fullname
    ORDER BY total_spent DESC 
    LIMIT 1
");

$lowest_buyer = $db_untils->getOne("
    SELECT fullname, phone, SUM(total_money) as total_spent, COUNT(id) as total_orders
    FROM orders 
    WHERE status != 'Đã hủy'
    GROUP BY phone, fullname
    ORDER BY total_spent ASC 
    LIMIT 1
");

// ==========================================
// 📦 4. THỐNG KÊ SẢN PHẨM BÁN CHẠY NHẤT
// ==========================================
$hot_products = $db_untils->getAll("
    SELECT p.mota, p.hinhAnh, p.gia, SUM(od.quantity) as total_sold
    FROM order_details od
    JOIN products p ON od.product_id = p.maSP
    JOIN orders o ON od.order_id = o.id
    WHERE o.status != 'Đã hủy'
    GROUP BY p.maSP, p.mota, p.hinhAnh, p.gia
    ORDER BY total_sold DESC 
    LIMIT 3
");
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Bảng Điều Khiển Admin - Store</title>
    <link rel="stylesheet" href="./style.css?v=<?= time() ?>">
    <style>
    /* CSS custom bổ sung cho Header mới */
    .header-actions {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .logout-btn-custom {
        padding: 8px 16px;
        background: #ef4444;
        color: white !important;
        border-radius: 6px;
        text-decoration: none;
        font-weight: bold;
        font-size: 13px;
        transition: background 0.2s, transform 0.1s;
    }

    .logout-btn-custom:hover {
        background: #dc2626;
        transform: translateY(-1px);
    }

    .logout-btn-custom:active {
        transform: translateY(0);
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
        margin-top: 20px;
    }

    .stat-card {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        border-left: 5px solid #2563eb;
        text-align: center;
    }

    .stat-card.products {
        border-left-color: #10b981;
    }

    .stat-card.users {
        border-left-color: #f59e0b;
    }

    .stat-card.revenue {
        border-left-color: #ec4899;
    }

    .stat-card h3 {
        margin: 0;
        font-size: 13px;
        color: #64748b;
        text-transform: uppercase;
    }

    .stat-card .number {
        font-size: 24px;
        font-weight: bold;
        margin: 10px 0;
        color: #1e293b;
    }

    .advanced-stats {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-top: 25px;
    }

    .stat-box {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .stat-box h2 {
        border-bottom: 2px solid #f1f5f9;
        padding-bottom: 10px;
        font-size: 16px;
        color: #1e293b;
        margin-bottom: 15px;
        margin-top: 0;
    }

    .buyer-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        margin-bottom: 10px;
    }

    .prod-list-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 10px 0;
        border-bottom: 1px dashed #e2e8f0;
    }

    .prod-list-item:last-child {
        border-bottom: none;
    }

    .prod-list-item img {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 4px;
        border: 1px solid #e2e8f0;
    }

    .menu-list {
        margin-top: 25px;
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .menu-list h2 {
        border-bottom: 2px solid #f1f5f9;
        padding-bottom: 10px;
        font-size: 16px;
        margin-top: 0;
        margin-bottom: 15px;
    }

    .admin-links {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .admin-link-btn {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 20px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        text-decoration: none;
        color: #334155;
        font-weight: bold;
        font-size: 14px;
        transition: all 0.2s;
    }

    .admin-link-btn:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
        color: #2563eb;
        transform: translateX(5px);
    }
    </style>
</head>

<body>
    <header>
        <div class="header-logo">
            <h1 style="cursor: pointer;" onclick="window.location.href='index.php'">⚙️ Hệ Thống Quản Trị</h1>
        </div>
        <div class="header-actions">
            <!-- 💬 NÚT BẤM REAL-TIME CHAT CỦA ADMIN ĐƯỢC BỔ SUNG LÊN HEADER -->
            <a href="admin_chat.php" class="cart-btn" style="background: #fe2c55;">💬 Trung tâm Chat</a>

            <!-- Các nút phân hệ có sẵn của bạn -->
            <a href="admin_orders.php" class="cart-btn" style="background: #2563eb;">Quản lý đơn hàng</a>
            <a href="lap4.php" class="cart-btn" style="background: #10b981;">Quản lý sản phẩm</a>
            <a href="admin_users.php" class="cart-btn" style="background: #f59e0b;">Quản lý user</a>

            <span style="margin: 0 10px; color: #fff; font-size: 14px;">Chào,
                <strong><?= htmlspecialchars($_SESSION['user']['fullname']) ?></strong></span>
            <a href="logout.php" class="logout-btn-custom">Đăng xuất</a>
        </div>
    </header>

    <div class="main-container" style="max-width: 1200px; margin: 30px auto; padding: 0 20px;">

        <div class="dashboard-grid">
            <div class="stat-card revenue">
                <h3>💰 Doanh Thu Hôm Nay</h3>
                <div class="number" style="color: #db2777;"><?= number_format($revenue_today) ?> đ</div>
                <small>Dựa trên đơn phát sinh hôm nay</small>
            </div>
            <div class="stat-card revenue">
                <h3>📅 Doanh Thu Tháng Này</h3>
                <div class="number" style="color: #2563eb;"><?= number_format($revenue_month) ?> đ</div>
                <small>Tổng cộng trong tháng hiện tại</small>
            </div>
            <div class="stat-card products">
                <h3>🛍️ Sản Phẩm Đang Bán</h3>
                <div class="number"><?= $total_products ?></div>
                <small>Tổng số danh mục hàng hóa</small>
            </div>
            <div class="stat-card users">
                <h3>👥 Tổng Thành Viên</h3>
                <div class="number"><?= $total_users ?></div>
                <small>Bao gồm Khách hàng & Admin</small>
            </div>
        </div>

        <div class="advanced-stats">
            <div class="stat-box">
                <h2>👥 Hành Vi Mua Sắm Của Khách Hàng</h2>
                <p style="font-weight: bold; margin-bottom: 5px; color: #16a34a; font-size: 13px;">🏆 Người mua nhiều
                    nhất:</p>
                <?php if ($top_buyer) { ?>
                <div class="buyer-item" style="border-left: 4px solid #16a34a;">
                    <div>
                        <strong><?= htmlspecialchars($top_buyer['fullname']) ?></strong> <br>
                        <small style="color: #64748b;">📞 SĐT: <?= htmlspecialchars($top_buyer['phone']) ?></small>
                    </div>
                    <div style="text-align: right;">
                        <span style="color: #dc2626; font-weight: bold;"><?= number_format($top_buyer['total_spent']) ?>
                            đ</span> <br>
                        <small style="color: #64748b;">(<?= $top_buyer['total_orders'] ?> đơn)</small>
                    </div>
                </div>
                <?php } else { echo "<div class='buyer-item'>Chưa có dữ liệu mua hàng</div>"; } ?>

                <p style="font-weight: bold; margin-bottom: 5px; color: #ea580c; font-size: 13px; margin-top: 15px;">📉
                    Người mua ít nhất:</p>
                <?php if ($lowest_buyer) { ?>
                <div class="buyer-item" style="border-left: 4px solid #ea580c;">
                    <div>
                        <strong><?= htmlspecialchars($lowest_buyer['fullname']) ?></strong> <br>
                        <small style="color: #64748b;">📞 SĐT: <?= htmlspecialchars($lowest_buyer['phone']) ?></small>
                    </div>
                    <div style="text-align: right;">
                        <span
                            style="color: #dc2626; font-weight: bold;"><?= number_format($lowest_buyer['total_spent']) ?>
                            đ</span> <br>
                        <small style="color: #64748b;">(<?= $lowest_buyer['total_orders'] ?> đơn)</small>
                    </div>
                </div>
                <?php } else { echo "<div class='buyer-item'>Chưa có dữ liệu mua hàng</div>"; } ?>
            </div>

            <div class="stat-box">
                <h2>🔥 Top 3 Sản Phẩm Bán Chạy Nhất</h2>
                <div style="display: flex; flex-direction: column;">
                    <?php if (count($hot_products) > 0) { 
                        foreach($hot_products as $index => $prod) { ?>
                    <div class="prod-list-item">
                        <span style="font-weight: bold; color: #64748b; width: 20px;">#<?= $index + 1 ?></span>
                        <img src="<?= htmlspecialchars($prod['hinhAnh']) ?>" alt="">
                        <div style="flex: 1;">
                            <strong
                                style="font-size: 13px; color: #1e293b;"><?= htmlspecialchars($prod['mota']) ?></strong><br>
                            <small style="color: #dc2626; font-weight: bold;"><?= number_format($prod['gia']) ?>
                                đ</small>
                        </div>
                        <div style="text-align: right;">
                            <span
                                style="background: #ef4444; color: #fff; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: bold;">
                                Đã bán: <?= $prod['total_sold'] ?>
                            </span>
                        </div>
                    </div>
                    <?php } 
                    } else { echo "<p style='text-align:center; color:#64748b; padding-top:20px;'>Chưa có sản phẩm nào được bán ra.</p>"; } ?>
                </div>
            </div>
        </div>

        <div class="menu-list">
            <h2>🛠️ Phân Hệ Chức Năng Quản Lý Chi Tiết</h2>
            <div class="admin-links" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">

                <!-- Phân hệ báo cáo doanh thu hiện tại của bạn -->
                <a href="admin_revenue.php" class="admin-link-btn"
                    style="background: #fff7ed; border-color: #fed7aa; margin-bottom: 0;">
                    <span>📈 Xem Báo Cáo & Phân Tích Chi Tiết Doanh Thu</span>
                    <span style="color: #ea580c;">Phân tích ngay →</span>
                </a>

                <!-- 💬 BỔ SUNG: Phân hệ trung tâm hỗ trợ trực tuyến tư vấn khách hàng -->
                <a href="admin_chat.php" class="admin-link-btn"
                    style="background: #fff1f2; border-color: #fecdd3; transition: all 0.2s;">
                    <span>💬 Trung tâm hỗ trợ & Tư vấn khách hàng trực tuyến</span>
                    <span style="color: #fe2c55;">Tư vấn ngay →</span>
                </a>

            </div>
        </div>
    </div>
</body>

</html>