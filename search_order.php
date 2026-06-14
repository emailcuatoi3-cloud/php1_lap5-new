<?php
session_start();
require "./db_utils.php";
$db_untils = new DB_UTILS();

// --- BỘ PHẬN XỬ LÝ API NGẦM (AJAX ENDPOINT) ---
// Nếu nhận diện có yêu cầu truy xuất hành trình bằng AJAX, file sẽ chỉ trả về dữ liệu JSON rồi ngắt trang luôn
if (isset($_GET['api']) && $_GET['api'] === 'track') {
    header('Content-Type: application/json');
    $search_input = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
    
    if (empty($search_input)) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập mã đơn hàng hoặc số điện thoại!']);
        exit();
    }

    $orders_data = [];
    
    // Kiểm tra định dạng: Nếu là số điện thoại (Từ 9 đến 11 chữ số)
    if (ctype_digit($search_input) && strlen($search_input) >= 9 && strlen($search_input) <= 11) {
        $orders_data = $db_untils->getAll("SELECT * FROM orders WHERE phone = ? ORDER BY id DESC", [$search_input]);
        if (count($orders_data) == 0) {
            echo json_encode(['status' => 'error', 'message' => "Không tìm thấy bất kỳ đơn hàng nào liên kết với số điện thoại: {$search_input}!"]);
            exit();
        }
    } else {
        // Nếu là mã đơn hàng (ID)
        $order_id = (int)$search_input;
        $single_order = $db_untils->getOne("SELECT * FROM orders WHERE id = ?", [$order_id]);
        if ($single_order) {
            $orders_data[] = $single_order;
        } else {
            echo json_encode(['status' => 'error', 'message' => "Không tìm thấy mã đơn hàng #{$search_input} trên hệ thống!"]);
            exit();
        }
    }

    // Gộp thêm chi tiết sản phẩm vào từng đơn hàng
    $result_orders = [];
    foreach ($orders_data as $order) {
        $details = $db_untils->getAll("
            SELECT od.*, p.mota, p.hinhAnh 
            FROM order_details od 
            JOIN products p ON od.product_id = p.maSP 
            WHERE od.order_id = ?
        ", [$order['id']]);
        
        $order['products'] = $details;
        $result_orders[] = $order;
    }

    echo json_encode(['status' => 'success', 'data' => $result_orders]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tra cứu hành trình đơn hàng Real-time</title>
    <link rel="stylesheet" href="./style.css?v=<?= time() ?>">
    <style>
    .search-box-panel {
        max-width: 750px;
        margin: 30px auto;
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
        text-align: center;
    }

    .search-form-group {
        display: flex;
        gap: 10px;
        margin-top: 15px;
    }

    .search-form-group input {
        flex: 1;
        padding: 12px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 15px;
        font-weight: bold;
        text-align: center;
    }

    /* TIMELINE ĐỨNG REAL-TIME */
    .timeline {
        position: relative;
        margin: 25px 0;
        padding-left: 30px;
        text-align: left;
    }

    .timeline::before {
        content: '';
        position: absolute;
        left: 5px;
        top: 10px;
        width: 2px;
        height: 85%;
        background: #e5e7eb;
    }

    .timeline-item {
        position: relative;
        margin-bottom: 25px;
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
        font-size: 12px;
        color: #6b7280;
        margin-bottom: 3px;
    }

    .timeline-content {
        background: #f9fafb;
        padding: 12px 15px;
        border-radius: 8px;
        border: 1px solid #f3f4f6;
    }

    .timeline-content h4 {
        font-size: 14px;
        color: #1f2937;
        margin-bottom: 3px;
        font-weight: bold;
    }

    .timeline-content p {
        font-size: 13px;
        color: #4b5563;
        margin-bottom: 0;
    }

    .mini-list {
        margin-top: 15px;
        background: #f8fafc;
        padding: 15px;
        border-radius: 8px;
        text-align: left;
        border: 1px solid #e2e8f0;
    }

    .mini-prod-item {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 10px;
        padding-bottom: 10px;
        border-bottom: 1px solid #edf2f7;
    }

    .mini-prod-item:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }

    .mini-prod-item img {
        width: 45px;
        height: 45px;
        object-fit: cover;
        border-radius: 6px;
    }

    .order-result-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 20px;
        margin-top: 25px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
    }

    .delivery-prediction-box {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        color: #166534;
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 15px;
        text-align: left;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* Hiệu ứng loading quay tròn */
    .spinner {
        display: none;
        width: 30px;
        height: 30px;
        border: 4px solid #f3f4f6;
        border-top: 4px solid #f57224;
        border-radius: 50%;
        margin: 20px auto;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }
    </style>
</head>

<body>

    <header>
        <div class="header-logo">
            <h1>🔍 Cổng tra cứu trạng thái đơn hàng</h1>
        </div>
        <div class="header-actions">
            <a href="lap4.php" class="cart-btn" style="background: #4b5563;">← Quay lại cửa hàng</a>
        </div>
    </header>

    <div class="search-box-panel">
        <h2>Tra cứu thông tin vận đơn Real-time</h2>
        <p style="font-size: 13px; color: #6b7280; margin-top: 5px;">Nhập <strong>Mã đơn hàng (Số #)</strong> hoặc
            <strong>Số điện thoại đặt hàng</strong> không cần tải lại trang
        </p>

        <div class="search-form-group">
            <input type="text" id="search_input" placeholder="Ví dụ nhập: 8 hoặc 0948580148" required>
            <button type="button" id="btn_search_realtime" style="width: 130px; background: #f57224;">Tìm kiếm</button>
        </div>

        <div id="error-container" style="display:none;"></div>

        <div id="loading-spinner" class="spinner"></div>

        <div id="results-wrapper"></div>
    </div>

    <script>
    // Định dạng hàm hiển thị tiền đ cho gọn
    function formatMoney(amount) {
        return new Intl.NumberFormat('vi-VN').format(amount) + ' đ';
    }

    // Định dạng chuyển đổi mốc thời gian timestamp sang chuỗi ngày tháng
    function parseDateStr(mysqlDate, addSeconds = 0) {
        let t = new Date(mysqlDate.replace(/-/g, "/"));
        if (addSeconds > 0) t.setSeconds(t.getSeconds() + addSeconds);

        let day = String(t.getDate()).padStart(2, '0');
        let month = String(t.getMonth() + 1).padStart(2, '0');
        let year = t.getFullYear();
        let hours = String(t.getHours()).padStart(2, '0');
        let minutes = String(t.getMinutes()).padStart(2, '0');

        return `${day}/${month}/${year} ${hours}:${minutes}`;
    }

    document.getElementById('btn_search_realtime').addEventListener('click', function() {
        const keyword = document.getElementById('search_input').value.trim();
        const errorContainer = document.getElementById('error-container');
        const spinner = document.getElementById('loading-spinner');
        const wrapper = document.getElementById('results-wrapper');

        // Reset nhanh giao diện
        errorContainer.style.display = 'none';
        wrapper.innerHTML = '';

        if (!keyword) {
            errorContainer.className = 'error';
            errorContainer.innerHTML = '⚠️ Vui lòng điền thông tin mã đơn hoặc SĐT!';
            errorContainer.style.display = 'block';
            return;
        }

        // Bật vòng xoay chờ dữ liệu
        spinner.style.display = 'block';

        // Gọi API ngầm thời gian thực
        fetch(`search_order.php?api=track&keyword=${encodeURIComponent(keyword)}`)
            .then(response => response.json())
            .then(response => {
                spinner.style.display = 'none'; // Tắt vòng xoay

                if (response.status === 'error') {
                    errorContainer.className = 'error';
                    errorContainer.innerHTML = '⚠️ ' + response.message;
                    errorContainer.style.display = 'block';
                } else {
                    // Dựng cấu trúc HTML động đổ vào wrapper
                    let html = '';

                    response.data.forEach(order => {
                        let badgeStyle = 'background: #fef3c7; color: #d97706;';
                        if (order.status == 'Đang giao') badgeStyle =
                            'background: #dbeafe; color: #2563eb;';
                        if (order.status == 'Đã nhận') badgeStyle =
                            'background: #dcfce7; color: #166534;';
                        if (order.status == 'Đã hủy') badgeStyle =
                            'background: #fee2e2; color: #991b1b;';

                        // Tính mốc ngày dự báo
                        let dateBase = new Date(order.created_at.replace(/-/g, "/"));
                        let minDate = new Date(dateBase.getTime() + (2 * 86400000));
                        let maxDate = new Date(dateBase.getTime() + (3 * 86400000));
                        let exactDate = new Date(dateBase.getTime() + (1 * 86400000));

                        let minDateStr =
                            `${String(minDate.getDate()).padStart(2, '0')}/${String(minDate.getMonth()+1).padStart(2, '0')}/${minDate.getFullYear()}`;
                        let maxDateStr =
                            `${String(maxDate.getDate()).padStart(2, '0')}/${String(maxDate.getMonth()+1).padStart(2, '0')}/${maxDate.getFullYear()}`;
                        let exactDateStr =
                            `${String(exactDate.getDate()).padStart(2, '0')}/${String(exactDate.getMonth()+1).padStart(2, '0')}/${exactDate.getFullYear()}`;

                        html += `
                            <div class="order-result-card">
                                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px dashed #edf2f7; padding-bottom: 10px; margin-bottom: 15px;">
                                    <span style="font-size:16px; font-weight: bold; text-align: left;">Đơn hàng: <span style="color:#2563eb;">#${order.id}</span></span>
                                    <span style="padding:4px 12px; border-radius:20px; font-weight:bold; font-size:13px; ${badgeStyle}">${order.status}</span>
                                </div>

                                ${order.status === 'Chờ xác nhận' || order.status === 'Đang giao' ? `
                                    <div class="delivery-prediction-box">
                                        📅 <span>Thời gian dự kiến hàng đến nơi: từ ngày <strong>${minDateStr}</strong> đến ngày <strong>${maxDateStr}</strong> (Khoảng 2-3 ngày làm việc).</span>
                                    </div>
                                ` : ''}
                                ${order.status === 'Đã nhận' ? `
                                    <div class="delivery-prediction-box" style="background: #eff6ff; border-color: #bfdbfe; color: #1e40af;">
                                        📦 <span>Kiện hàng đã giao thành công vào ngày <strong>${exactDateStr}</strong>. Chân thành cảm ơn bạn!</span>
                                    </div>
                                ` : ''}
                                ${order.status === 'Đã hủy' ? `
                                    <div class="delivery-prediction-box" style="background: #fef2f2; border-color: #fca5a5; color: #991b1b;">
                                        🛑 <span>Đơn hàng này đã bị hủy bỏ tiến trình vận chuyển, không có lịch trình dự kiến giao hàng.</span>
                                    </div>
                                ` : ''}
                                
                                <div style="font-size:13px; color:#475569; line-height:1.5; background:#f1f5f9; padding:12px; border-radius:6px; margin-bottom:20px; text-align: left;">
                                    👤 <strong>Người nhận:</strong> ${order.fullname} | 📞 ${order.phone}<br>
                                    📍 <strong>Nơi nhận:</strong> ${order.address}
                                </div>

                                <div class="timeline">
                                    ${order.status === 'Đã nhận' ? `
                                    <div class="timeline-item active">
                                        <div class="timeline-icon"></div>
                                        <div class="timeline-time">${parseDateStr(order.created_at, 86400)}</div>
                                        <div class="timeline-content">
                                            <h4>🎉 Đã giao hàng thành công</h4>
                                            <p>Đơn hàng đã được bưu tá phát hoàn tất đến tận tay quý khách.</p>
                                        </div>
                                    </div>
                                    ` : ''}

                                    ${order.status === 'Đang giao' || order.status === 'Đã nhận' ? `
                                    <div class="timeline-item active">
                                        <div class="timeline-icon"></div>
                                        <div class="timeline-time">${parseDateStr(order.created_at, 3600)}</div>
                                        <div class="timeline-content">
                                            <h4>🚚 Đang trên đường vận chuyển</h4>
                                            <p>Nhân viên giao vận đang di chuyển mang kiện hàng đến vị trí của bạn.</p>
                                        </div>
                                    </div>
                                    ` : ''}

                                    ${order.status === 'Đã hủy' ? `
                                    <div class="timeline-item active cancelled">
                                        <div class="timeline-icon"></div>
                                        <div class="timeline-time">${parseDateStr(new Date().toISOString().slice(0, 19).replace('T', ' '))}</div>
                                        <div class="timeline-content">
                                            <h4 style="color:#dc2626;">❌ Đơn hàng đã hủy bỏ</h4>
                                            <p style="color:#991b1b;"><strong>Thông tin lý do:</strong> ${order.ly_do_huy ? order.ly_do_huy : 'Hủy tự động trên hệ thống.'}</p>
                                        </div>
                                    </div>
                                    ` : ''}

                                    ${order.status !== 'Chờ xác nhận' ? `
                                    <div class="timeline-item active">
                                        <div class="timeline-icon"></div>
                                        <div class="timeline-time">${parseDateStr(order.created_at, 600)}</div>
                                        <div class="timeline-content">
                                            <h4>✔ Đã đóng gói sản phẩm</h4>
                                            <p>Hệ thống phê duyệt thành công. Sản phẩm đã đóng hộp bàn giao bưu cục vận chuyển.</p>
                                        </div>
                                    </div>
                                    ` : ''}

                                    <div class="timeline-item active">
                                        <div class="timeline-icon"></div>
                                        <div class="timeline-time">${parseDateStr(order.created_at)}</div>
                                        <div class="timeline-content">
                                            <h4>📝 Khởi tạo đơn hàng</h4>
                                            <p>Hệ thống nhận đơn đặt hàng trực tuyến từ cổng Checkout website thành công.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="mini-list">
                                    <h4 style="margin-bottom:8px; color:#475569; font-size:14px;">Mặt hàng bên trong đơn:</h4>
                                    <div id="products_inside_${order.id}"></div>
                                </div>
                            </div>
                        `;
                    });

                    wrapper.innerHTML = html;

                    // Đổ danh sách sản phẩm con vào sau khi khối cha dựng xong
                    response.data.forEach(order => {
                        let prodHtml = '';
                        order.products.forEach(p => {
                            prodHtml += `
                                <div class="mini-prod-item">
                                    <img src="${p.hinhAnh}">
                                    <div style="flex:1; font-size:13px; text-align:left;">
                                        <strong>${p.mota}</strong><br>
                                        <span style="color:#64748b;">Số lượng: ${p.quantity} sản phẩm | Giá: ${formatMoney(p.price)}</span>
                                    </div>
                                </div>
                            `;
                        });
                        prodHtml += `
                            <div style="text-align:right; font-weight:bold; color:#dc2626; margin-top:10px; font-size:15px; border-top: 1px solid #edf2f7; padding-top: 10px;">
                                Tổng tiền hóa đơn: ${formatMoney(order.total_money)}
                            </div>
                        `;
                        document.getElementById(`products_inside_${order.id}`).innerHTML = prodHtml;
                    });
                }
            })
            .catch(error => {
                spinner.style.display = 'none';
                errorContainer.className = 'error';
                errorContainer.innerHTML = '⚠️ Có lỗi đường truyền hệ thống xảy ra!';
                errorContainer.style.display = 'block';
                console.error('Error fetching data:', error);
            });
    });
    </script>
</body>

</html>