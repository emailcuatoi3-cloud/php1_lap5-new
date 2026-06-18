<?php
require "./db_utils.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$db_untils = new DB_UTILS();

$errors = [];
$success = "";

// Kiểm tra quyền Admin
$isAdmin = isset($_SESSION['user']) && ($_SESSION['user']['role'] === 'admin' || $_SESSION['user']['username'] === 'admin');

if(isset($_GET['success'])){
    switch($_GET['success']){
        case 'add': $success = "Thêm sản phẩm thành công!"; break;
        case 'update': $success = "Cập nhật sản phẩm thành công!"; break;
        case 'delete': $success = "Xóa sản phẩm thành công!"; break;
    }
}

// XỬ LÝ THÊM VÀO GIỎ HÀNG KHÔNG ĐỔI TRANG
if (isset($_GET['action']) && $_GET['action'] === 'add' && isset($_GET['id'])) {
    $pId = $_GET['id'];
    $prod = $db_untils->getOne("SELECT ton_kho FROM products WHERE maSP = ?", [$pId]);
    $currentInCart = isset($_SESSION['cart'][$pId]) ? $_SESSION['cart'][$pId]['quantity'] : 0;
    
    if ($prod && $prod['ton_kho'] <= $currentInCart) {
        echo "<script>alert('Sản phẩm này đã đạt giới hạn số lượng tồn kho tối đa có thể mua!'); window.location.href='lap4.php';</script>";
        exit();
    }
    
    if (isset($_SESSION['cart'][$pId]) && is_array($_SESSION['cart'][$pId])) {
        $_SESSION['cart'][$pId]['quantity']++;
    } else {
        $productInfo = $db_untils->getOne("SELECT * FROM products WHERE maSP = ?", [$pId]);
        if ($productInfo) {
            $_SESSION['cart'][$pId] = [
                'id' => $productInfo['maSP'], 'name' => $productInfo['mota'],
                'price' => $productInfo['gia'], 'image' => $productInfo['hinhAnh'], 'quantity' => 1
            ];
        }
    }
    $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $currentKeyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
    header("Location: lap4.php?page=" . $currentPage . "&keyword=" . urlencode($currentKeyword));
    exit();
}

// XÓA SẢN PHẨM (CHỈ ADMIN)
if (isset($_GET['delete'])) {
    if (!$isAdmin) { die("BẠN KHÔNG CÓ QUYỀN THỰC HIỆN HÀNH ĐỘNG NÀY!"); }
    $maSP = $_GET['delete'];
    $db_untils->execute("DELETE FROM products WHERE maSP = ?", [$maSP]);
    header("Location: lap4.php?success=delete");
    exit();
}

// LẤY THÔNG TIN SỬA (CHỈ ADMIN)
$editProduct = null;
if (isset($_GET['edit'])) {
    if (!$isAdmin) { die("BẠN KHÔNG CÓ QUYỀN THỰC HIỆN HÀNH ĐỘNG NÀY!"); }
    $maSP = $_GET['edit'];
    $editProduct = $db_untils->getOne("SELECT * FROM products WHERE maSP = ?", [$maSP]);
}

// CHI TIẾT SẢN PHẨM
$detailProduct = null;
if (isset($_GET['detail'])) {
    $maSP = $_GET['detail'];
    $detailProduct = $db_untils->getOne("SELECT * FROM products WHERE maSP = ?", [$maSP]);
}

// XỬ LÝ FORM THÊM / SỬA (CHỈ ADMIN)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!$isAdmin) { die("BẠN KHÔNG CÓ QUYỀN THỰC HIỆN HÀNH ĐỘNG NÀY!"); }
    $productId = trim($_POST['productId']);
    $description = trim($_POST['description']);
    $price = trim($_POST['price']);
    $ton_kho = (int)$_POST['ton_kho'];
    $image = trim($_POST['image']);

    if (empty($productId)) { $errors[] = "ID không được để trống"; }
    
    // 🛡️ CHỈNH SỬA: Bổ sung validated chặn không cho thêm hoặc cập nhật giá âm
    if (empty($price)) { 
        $errors[] = "Giá tiền không được để trống"; 
    } elseif ((float)$price < 0) {
        $errors[] = "Giá tiền sản phẩm không được là số âm!";
    }
    
    if ($ton_kho < 0) { $errors[] = "Số lượng tồn kho không được âm"; }

    if (isset($_POST['update'])) {
        if (count($errors) == 0) {
            $db_untils->execute("UPDATE products SET mota = ?, gia = ?, ton_kho = ?, hinhAnh = ? WHERE maSP = ?", [$description, $price, $ton_kho, $image, $productId]);
            header("Location: lap4.php?success=update"); exit();
        }
    } else {
        $check_product = $db_untils->getOne("SELECT * FROM products WHERE maSP = ?", [$productId]);
        if ($check_product) { $errors[] = "Mã sản phẩm đã tồn tại!"; }
        if (count($errors) == 0) {
            $db_untils->execute("INSERT INTO products (maSP,mota,gia,ton_kho,hinhAnh) VALUES (?,?,?,?,?)", [$productId, $description, $price, $ton_kho, $image]);
            header("Location: lap4.php?success=add"); exit();
        }
    }
}

// TÌM KIẾM + PHÂN TRANG
$limit = 6;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) { $page = 1; }
$start = ($page - 1) * $limit;
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : "";

if (!empty($keyword)) {
    $search = "%$keyword%";
    $countProducts = $db_untils->getAll("SELECT * FROM products WHERE maSP LIKE ? OR mota LIKE ?", [$search, $search]);
    $products = $db_untils->getAll("SELECT * FROM products WHERE maSP LIKE ? OR mota LIKE ? LIMIT $start,$limit", [$search, $search]);
} else {
    $countProducts = $db_untils->getAll("SELECT * FROM products");
    $products = $db_untils->getAll("SELECT * FROM products LIMIT $start,$limit");
}
$totalProducts = count($countProducts);
$totalPages = ceil($totalProducts / $limit);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cửa hàng sản phẩm</title>
    <link rel="stylesheet" href="./style.css?v=<?= time() ?>">
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
    </style>
</head>

<body>

    <header>
        <div class="header-logo">
            <h1 style="cursor: pointer;" onclick="window.location.href='<?= $isAdmin ? 'index.php' : 'lap4.php' ?>'">⚙️
                Hệ Thống Cửa Hàng</h1>
        </div>
        <div class="header-actions">
            <?php if ($isAdmin) { ?>
            <a href="index.php" class="cart-btn" style="background: #4b5563;">Về trang chủ</a>
            <a href="admin_orders.php" class="cart-btn" style="background: #2563eb;">Quản lý đơn hàng</a>
            <a href="lap4.php" class="cart-btn" style="background: #10b981;">Quản lý sản phẩm</a>
            <a href="admin_users.php" class="cart-btn" style="background: #f59e0b;">Quản lý user</a>
            <?php } else { ?>
            <a href="search_order.php" class="cart-btn" style="background: #0284c7; box-shadow: none;">🔍 Tra cứu đơn
                hàng</a>
            <a href="javascript:void(0)" onclick="document.getElementById('chat-open-btn').click();" class="cart-btn"
                style="background:#8b5cf6;">
                💬 Trung tâm hỗ trợ
            </a>
            <?php } ?>

            <div class="user-nav-box"
                style="display: inline-flex; align-items: center; gap: 5px; font-size: 14px; color: #fff;">
                <?php if (isset($_SESSION['user'])) { ?>
                <span>Chào, <strong><?= htmlspecialchars($_SESSION['user']['fullname']) ?></strong></span>

                <?php if (!$isAdmin) { ?>
                <a href="user_orders.php"
                    style="color: #10b981; font-weight: bold; text-decoration: none; margin-left: 5px;">📋 Đơn của
                    tôi</a>
                <?php } ?>

                <a href="logout.php" class="logout-btn-custom" style="margin-left: 5px;">Đăng xuất</a>
                <?php } else { ?>
                <a href="login.php" style="color: #fff; text-decoration: none;">🔑 Đăng nhập</a>
                <?php } ?>
            </div>
            <a href="cart.php" class="cart-btn">🛒 Giỏ hàng (<span
                    id="cart-counter"><?= count($_SESSION['cart']) ?></span>)</a>
        </div>
    </header>

    <?php if ($detailProduct) { ?>
    <div class="amazon-detail-container" style="margin-top: 25px;">
        <div class="detail-navigation"><a href="?page=<?= $page ?>&keyword=<?= urlencode($keyword) ?>"
                class="btn-back-amazon">‹ Quay lại danh sách</a></div>
        <div class="detail-main-layout">
            <div class="detail-image-panel">
                <div class="detail-image-wrapper"><img src="<?= htmlspecialchars($detailProduct['hinhAnh']) ?>"></div>
            </div>
            <div class="detail-info-panel">
                <h2 class="amazon-title">Sản phẩm mã số: <?= htmlspecialchars($detailProduct['maSP']) ?></h2>
                <div class="divider"></div>
                <div class="amazon-price-row">
                    <span class="price-label">Giá bán:</span><span
                        class="amazon-detail-price"><?= number_format($detailProduct['gia'], 0, ',', '.') ?> đ</span>
                </div>
                <div style="margin-bottom: 10px; font-size: 14px;">
                    <strong>Hàng tồn kho còn lại:</strong>
                    <span
                        style="color: <?= $detailProduct['ton_kho'] > 0 ? '#007600' : '#dc2626' ?>; font-weight: bold;">
                        <?= $detailProduct['ton_kho'] > 0 ? $detailProduct['ton_kho'] . ' sản phẩm' : 'Hết hàng' ?>
                    </span>
                </div>
                <div class="divider"></div>
                <div class="amazon-description-box">
                    <h3>Mô tả chi tiết:</h3>
                    <p class="amazon-description-text"><?= htmlspecialchars($detailProduct['mota']) ?></p>
                </div>
            </div>
            <div class="detail-buy-box">
                <span class="buy-box-price"><?= number_format($detailProduct['gia'], 0, ',', '.') ?> đ</span>
                <div class="stock-status" style="color: <?= $detailProduct['ton_kho'] > 0 ? '#007600' : '#dc2626' ?>;">
                    <?= $detailProduct['ton_kho'] > 0 ? 'Còn hàng' : 'Tạm hết hàng' ?>
                </div>
                <div class="delivery-text">Giao hàng COD miễn phí toàn quốc nhanh chóng từ 2-3 ngày.</div>
                <?php if ($detailProduct['ton_kho'] > 0) { ?>
                <a href="lap4.php?action=add&id=<?= $detailProduct['maSP'] ?>&page=<?= $page ?>&keyword=<?= urlencode($keyword) ?>"
                    class="amazon-cart-btn ajax-add-to-cart">🛒 Thêm vào giỏ hàng</a>
                <?php } else { ?>
                <button class="amazon-cart-btn" style="background:#cbd5e1; border-color:#cbd5e1; cursor:not-allowed;"
                    disabled>❌ Hết hàng</button>
                <?php } ?>
            </div>
        </div>
    </div>
    <?php } ?>

    <?php if (!$detailProduct) { ?>
    <div class="main-layout" style="margin-top: 25px;">
        <?php if ($isAdmin) { ?>
        <div class="left-panel">
            <form class="product-form" method="POST">
                <h2><?= $editProduct ? "Sửa sản phẩm" : "Thêm sản phẩm mới" ?></h2>
                <div class="form-group"><label>ID sản phẩm</label><input type="text" name="productId"
                        value="<?= $editProduct['maSP'] ?? '' ?>" <?= $editProduct ? "readonly" : "" ?>></div>
                <div class="form-group"><label>Mô tả</label><textarea
                        name="description"><?= $editProduct['mota'] ?? '' ?></textarea></div>
                <div class="form-group"><label>Giá tiền</label><input type="text" name="price"
                        value="<?= $editProduct['gia'] ?? '' ?>"></div>
                <div class="form-group"><label>Số lượng tồn kho</label><input type="number" name="ton_kho"
                        value="<?= $editProduct['ton_kho'] ?? '50' ?>"></div>
                <div class="form-group"><label>Đường dẫn hình ảnh (URL)</label><input type="url" name="image"
                        value="<?= $editProduct['hinhAnh'] ?? '' ?>"></div>
                <?php if ($editProduct) { ?><button type="submit" name="update" class="btn-edit-form">Cập nhật sản
                    phẩm</button>
                <?php } else { ?><button type="submit" class="btn-add">Thêm sản phẩm</button><?php } ?>
                <?php foreach($errors as $error){ echo "<div class='alert-danger'>$error</div>"; } ?>
                <?php if(!empty($success)){ echo "<div class='alert-success'>$success</div>"; } ?>
            </form>
        </div>
        <?php } ?>

        <div class="right-panel" style="<?= !$isAdmin ? 'width: 100%;' : '' ?>">
            <form method="GET" style="max-width:600px; margin:0 auto 20px auto; display:flex; gap:10px;">
                <input type="text" name="keyword" placeholder="Nhập mã sản phẩm hoặc mô tả..."
                    value="<?= htmlspecialchars($keyword) ?>"
                    style="flex:1; padding:10px; border:1px solid #ccc; border-radius:8px;">
                <button type="submit" style="width:120px; background:#2563eb;">Tìm kiếm</button>
                <a href="lap4.php"><button type="button" style="width:120px; background:#6b7280;">Xóa bộ
                        lọc</button></a>
            </form>

            <h2>Danh sách sản phẩm hiện có</h2>
            <?php if(count($products) == 0){ echo "<div style='text-align:center; color:#6b7280; font-weight:600;'>🔍 Không tìm thấy sản phẩm phù hợp</div>"; } ?>

            <div class="product-list">
                <?php foreach($products as $product){ $image = !empty($product['hinhAnh']) ? $product['hinhAnh'] : 'https://via.placeholder.com/400x250'; ?>
                <div class="product-card">
                    <img src="<?= htmlspecialchars($image) ?>">
                    <div class="product-info">
                        <p><strong>ID:</strong> <?= htmlspecialchars($product['maSP']) ?></p>
                        <p><strong>Mô tả:</strong> <?= htmlspecialchars($product['mota']) ?></p>
                        <p class="product-price"><?= number_format($product['gia'], 0, ',', '.') ?> đ</p>
                        <p style="font-size: 13px; color: #565959;">
                            Kho: <span
                                style="font-weight: bold; color: <?= $product['ton_kho'] > 0 ? '#007600' : '#dc2626' ?>;"><?= $product['ton_kho'] > 0 ? $product['ton_kho'] . ' sản phẩm' : 'Hết hàng' ?></span>
                        </p>
                    </div>
                    <div class="action-group">
                        <a href="?detail=<?= $product['maSP'] ?>&page=<?= $page ?>&keyword=<?= urlencode($keyword) ?>"
                            class="btn-amazon btn-detail">Chi tiết</a>
                        <?php if ($isAdmin) { ?>
                        <a href="?edit=<?= $product['maSP'] ?>&page=<?= $page ?>&keyword=<?= urlencode($keyword) ?>"
                            class="btn-amazon btn-edit">Sửa</a>
                        <a href="?delete=<?= $product['maSP'] ?>" class="btn-amazon btn-delete"
                            onclick="return confirm('Bạn có chắc muốn xóa sản phẩm này?')">Xóa</a>
                        <?php } ?>
                    </div>
                    <?php if ($product['ton_kho'] > 0) { ?>
                    <a href="lap4.php?action=add&id=<?= $product['maSP'] ?>&page=<?= $page ?>&keyword=<?= urlencode($keyword) ?>"
                        class="amazon-cart-btn ajax-add-to-cart">🛒 Thêm vào giỏ hàng</a>
                    <?php } else { ?>
                    <button class="amazon-cart-btn"
                        style="background:#cbd5e1; border-color:#cbd5e1; margin:15px; cursor:not-allowed;" disabled>❌
                        Hết hàng</button>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>

            <div class="pagination">
                <?php for($i = 1; $i <= $totalPages; $i++){ ?><a class="<?= $page == $i ? 'active' : '' ?>"
                    href="?page=<?= $i ?>&keyword=<?= urlencode($keyword) ?>"><?= $i ?></a><?php } ?>
            </div>
        </div>
    </div>
    <?php } ?>

    <script>
    document.querySelectorAll('.ajax-add-to-cart').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            fetch(this.getAttribute('href')).then(res => {
                if (res.ok) {
                    const counter = document.getElementById('cart-counter');
                    if (counter) counter.innerText = parseInt(counter.innerText) + 1;
                } else {
                    alert('Sản phẩm đã vượt quá giới hạn hàng tồn kho!');
                }
            });
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
    <script src="https://js.pusher.com/8.0.1/pusher.min.js"></script>

    <script>
    Pusher.logToConsole = true;

    const pusher = new Pusher('94c4c17f4353f8cdc5af', {
        cluster: 'ap1'
    });

    const channel = pusher.subscribe('store-channel');

    console.log("Pusher connected");
    </script>
    <script>
    // Tận dụng biến Session User ID của bạn đã có
    const myUserId = <?= json_encode($_SESSION['user']['id'] ?? 0) ?>;
    const adminId =
        <?= (int)$db_untils->getValue("SELECT id FROM users WHERE role='admin' LIMIT 1") ?>; // Giả lập ID mặc định của tài khoản quản trị Admin


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

        appendMessage(text, 'my-message');
        inputText.value = '';

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
                if (res.status !== 'success') {
                    console.error("Lỗi đồng bộ tin nhắn phía máy chủ!");
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

        // 🔑 SỬA LỖI HIỂN THỊ HTML THÔ: Đổi từ innerText thành innerHTML để dựng cấu trúc sản phẩm của Bot
        msgHtml.innerHTML = text;

        chatBody.appendChild(msgHtml);
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    // 📡 LẮNG NGHE ĐƯỜNG TRUYỀN WEBSOCKET TỪ ADMIN TRẢ VỀ REAL-TIME
    channel.bind('chat-message-event', function(data) {
        console.log("EVENT RECEIVED:", data);
        let msg = data;

        if (typeof msg === 'string') {
            msg = JSON.parse(msg);
        }

        if (msg.data) {
            msg = typeof msg.data === 'string' ?
                JSON.parse(msg.data) :
                msg.data;
        }

        console.log("FINAL:", msg);

        if (
            parseInt(msg.sender_id) === parseInt(adminId) &&
            parseInt(msg.receiver_id) === parseInt(myUserId)
        ) {
            appendMessage(msg.message_text, 'guest-message');
        }
    });
    </script>
</body>

</html>