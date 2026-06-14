<?php
session_start();
require "./db_utils.php";
$db_untils = new DB_UTILS();

if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] !== 'admin' && $_SESSION['user']['username'] !== 'admin')) {
    die("<h2 style='color:red; text-align:center; margin-top:50px;'>🔒 Bạn không có quyền truy cập vùng quản trị!</h2>");
}

$filter = $_GET['filter'] ?? 'all';
$where_clause = " WHERE status != 'Đã hủy' ";
$params = [];

switch ($filter) {
    case 'today':
        $where_clause .= " AND DATE(created_at) = CURRENT_DATE() ";
        $filter_title = "Hôm nay (" . date('d/m/Y') . ")";
        break;
    case 'month':
        $where_clause .= " AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) ";
        $filter_title = "Tháng này (" . date('m/Y') . ")";
        break;
    case 'year':
        $where_clause .= " AND YEAR(created_at) = YEAR(CURRENT_DATE()) ";
        $filter_title = "Năm này (" . date('Y') . ")";
        break;
    default:
        $filter = 'all';
        $filter_title = "Toàn thời gian";
        break;
}

$total_revenue = $db_untils->getValue("SELECT SUM(total_money) FROM orders $where_clause", $params);
$total_revenue = $total_revenue ? $total_revenue : 0;
$total_orders_count = $db_untils->getValue("SELECT COUNT(id) FROM orders $where_clause", $params);

$chart_data = $db_untils->getAll("
    SELECT DATE(created_at) as order_date, SUM(total_money) as daily_total
    FROM orders
    WHERE status != 'Đã hủy' AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at) ASC
");

$labels = []; $values = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $labels[$date] = date('d/m', strtotime($date));
    $values[$date] = 0;
}
foreach ($chart_data as $row) {
    if (isset($values[$row['order_date']])) { $values[$row['order_date']] = (int)$row['daily_total']; }
}
$orders_list = $db_untils->getAll("SELECT * FROM orders $where_clause ORDER BY id DESC", $params);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Báo Cáo Chi Tiết Doanh Thu - Admin</title>
    <link rel="stylesheet" href="./style.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
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

    .revenue-container {
        max-width: 1200px;
        margin: 30px auto;
        padding: 0 20px;
    }

    .filter-bar {
        display: flex;
        gap: 10px;
        margin-bottom: 25px;
        background: #fff;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .filter-btn {
        padding: 8px 16px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        text-decoration: none;
        color: #475569;
        font-weight: bold;
        font-size: 14px;
        background: #f8fafc;
        transition: all 0.2s;
    }

    .filter-btn.active {
        background: #2563eb;
        color: #fff;
        border-color: #2563eb;
    }

    .revenue-summary-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 25px;
    }

    .summary-card {
        background: #fff;
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        border-left: 5px solid #2563eb;
    }

    .summary-card.orders {
        border-left-color: #10b981;
    }

    .summary-card h3 {
        margin: 0;
        font-size: 14px;
        color: #64748b;
        text-transform: uppercase;
    }

    .summary-card .value {
        font-size: 32px;
        font-weight: bold;
        margin: 10px 0;
        color: #1e293b;
    }

    .chart-section {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        margin-bottom: 25px;
    }

    .chart-section h2 {
        font-size: 16px;
        margin-top: 0;
        margin-bottom: 15px;
        color: #1e293b;
        border-bottom: 2px solid #f1f5f9;
        padding-bottom: 10px;
    }

    .table-section {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .table-section h2 {
        font-size: 16px;
        margin-top: 0;
        margin-bottom: 15px;
        color: #1e293b;
        border-bottom: 2px solid #f1f5f9;
        padding-bottom: 10px;
    }
    </style>
</head>

<body>
    <header>
        <div class="header-logo">
            <h1 style="cursor: pointer;" onclick="window.location.href='index.php'">⚙️ Hệ Thống Quản Trị</h1>
        </div>
        <div class="header-actions">
            <a href="admin_orders.php" class="cart-btn" style="background: #2563eb;">Quản lý đơn hàng</a>
            <a href="lap4.php" class="cart-btn" style="background: #10b981;">Quản lý sản phẩm</a>
            <a href="admin_users.php" class="cart-btn" style="background: #f59e0b;">Quản lý user</a>
            <span style="margin: 0 10px; color: #fff; font-size: 14px;">Chào,
                <strong><?= htmlspecialchars($_SESSION['user']['fullname']) ?></strong></span>
            <a href="logout.php" class="logout-btn-custom">Đăng xuất</a>
        </div>
    </header>

    <div class="revenue-container">
        <div class="filter-bar">
            <span style="align-self: center; font-weight: bold; margin-right: 10px; color: #334155;">Xem theo thời
                gian:</span>
            <a href="admin_revenue.php?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">Toàn thời
                gian</a>
            <a href="admin_revenue.php?filter=today" class="filter-btn <?= $filter === 'today' ? 'active' : '' ?>">Hôm
                nay</a>
            <a href="admin_revenue.php?filter=month" class="filter-btn <?= $filter === 'month' ? 'active' : '' ?>">Tháng
                này</a>
            <a href="admin_revenue.php?filter=year" class="filter-btn <?= $filter === 'year' ? 'active' : '' ?>">Năm
                này</a>
        </div>

        <div class="revenue-summary-grid">
            <div class="summary-card">
                <h3>💰 Tổng doanh thu (<?= $filter_title ?>)</h3>
                <div class="value" style="color: #dc2626;"><?= number_format($total_revenue) ?> đ</div>
                <small style="color: #64748b;">Doanh thu thực tế (đã loại trừ các đơn hủy)</small>
            </div>
            <div class="summary-card orders">
                <h3>📦 Tổng số đơn hàng ghi nhận</h3>
                <div class="value" style="color: #10b981;"><?= $total_orders_count ?> đơn</div>
                <small style="color: #64748b;">Đơn hàng phát sinh trong khoảng thời gian lọc</small>
            </div>
        </div>

        <div class="chart-section">
            <h2>📈 Biểu đồ xu hướng doanh thu (7 ngày gần nhất)</h2>
            <div style="max-height: 300px; position: relative;">
                <canvas id="revenueChart" style="max-height: 300px;"></canvas>
            </div>
        </div>

        <div class="table-section">
            <h2>📋 Chi tiết danh sách hóa đơn đóng góp doanh thu</h2>
            <table class="cart-table" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th>Mã đơn</th>
                        <th>Khách hàng</th>
                        <th>Phương thức</th>
                        <th>Thời gian đặt</th>
                        <th>Tổng tiền đơn</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orders_list) > 0) {
                        foreach ($orders_list as $order) { ?>
                    <tr>
                        <td><strong>#<?= $order['id'] ?></strong></td>
                        <td style="text-align: left; font-size: 13px;">
                            👤 <strong><?= htmlspecialchars($order['fullname']) ?></strong><br>
                            📞 <?= htmlspecialchars($order['phone']) ?>
                        </td>
                        <td><code><?= $order['payment_method'] ?></code></td>
                        <td style="font-size: 13px; color:#4b5563;">
                            <?= date('H:i d/m/Y', strtotime($order['created_at'])) ?></td>
                        <td style="color: #dc2626; font-weight: bold;"><?= number_format($order['total_money']) ?> đ
                        </td>
                        <td>
                            <span
                                style="background:#d1fae5; color:#10b981; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:bold;"><?= $order['status'] ?></span>
                        </td>
                    </tr>
                    <?php }
                    } else { echo "<tr><td colspan='6' style='padding:30px; color:#64748b;'>Không có dữ liệu hóa đơn nào.</td></tr>"; } ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    const ctx = document.getElementById('revenueChart').getContext('2d');
    const revenueChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_values($labels)) ?>,
            datasets: [{
                label: 'Doanh thu (đ)',
                data: <?= json_encode(array_values($values)) ?>,
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                borderColor: 'rgba(37, 99, 235, 1)',
                borderWidth: 3,
                tension: 0.3,
                fill: true,
                pointBackgroundColor: 'rgba(37, 99, 235, 1)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('vi-VN') + ' đ';
                        }
                    }
                }
            }
        }
    });
    </script>
</body>

</html>