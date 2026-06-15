<?php
session_start();
require "./db_utils.php";
$db_untils = new DB_UTILS();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die("Bạn không có quyền truy cập vùng này!");
}

$users = $db_untils->getAll("SELECT id, fullname, username FROM users WHERE role != 'admin' ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Trung tâm hỗ trợ khách hàng - Admin</title>
    <link rel="stylesheet" href="./style.css?v=<?= time() ?>">
    <script src="https://js.pusher.com/8.0.1/pusher.min.js"></script>
</head>

<body>
    <header>
        <div class="header-logo">
            <h1>⚙️ Trung tâm tư vấn trực tuyến (Real-time Chat)</h1>
        </div>
        <div class="header-actions">

            <a href="index.php" class="cart-btn" style="background: #4b5563;">📊 Bảng điều khiển</a>
            <a href="admin_orders.php" class="cart-btn" style="background: #2563eb;">📦 Quản lý đơn hàng</a>
        </div>
    </header>

    <div class="admin-chat-container">
        <div class="admin-user-list-panel">
            <div class="admin-panel-title">DANH SÁCH USER</div>
            <?php foreach($users as $index => $u){ ?>
            <div class="admin-user-item <?= $index===0?'active':'' ?>" id="user_item_row_<?= $u['id'] ?>"
                onclick="changeTargetUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['fullname']) ?>', this)">
                <span>👤 <?= htmlspecialchars($u['fullname']) ?> (ID: <?= $u['id'] ?>)</span>

                <span class="unread-msg-badge" id="unread_notification_<?= $u['id'] ?>"
                    style="display: none; color: #ef4444; font-size: 11px; margin-left: 6px; font-weight: bold; font-style: italic; animation: blinker 1.5s linear infinite;">(Tin
                    mới)</span>
            </div>
            <?php } ?>
        </div>

        <div class="admin-message-chat-panel">
            <div class="amazon-chat-header" id="target-user-title">Đang trò chuyện với: Khách hàng</div>
            <div class="admin-chat-messages-body" id="admin-chat-body">
                <div
                    style="background: #fff; padding: 10px; border-radius: 8px; align-self: flex-start; max-width: 80%;">
                    Hệ thống đã kết nối trực tuyến. Bạn có thể tư vấn cho khách hàng ngay.</div>
            </div>
            <div class="admin-chat-footer">
                <input type="text" id="admin-input-text" placeholder="Nhập câu trả lời tư vấn...">
                <button type="button" class="admin-btn-send" onclick="sendAdminMessage()">Gửi API</button>
            </div>
        </div>
    </div>

    <style>
    @keyframes blinker {
        50% {
            opacity: 0;
        }
    }
    </style>

    <script>
    let activeReceiverId = <?= $users[0]['id'] ?? 0 ?>;
    const adminUserId = <?= (int)$_SESSION['user']['id'] ?>;

    function changeTargetUser(id, name, element) {
        activeReceiverId = id;
        document.querySelectorAll('.admin-user-item').forEach(el => el.classList.remove('active'));
        element.classList.add('active');
        document.getElementById('target-user-title').innerText = "Đang trò chuyện với: " + name;
        document.getElementById('admin-chat-body').innerHTML =
            `<div style="background: #fff; padding: 10px; border-radius: 8px; align-self: flex-start;">Đã chuyển sang hội thoại của ${name}.</div>`;

        // 🔔 FIX: Khi Admin click vào xem cuộc hội thoại, tự động ẩn dòng trạng thái "(Tin mới)" đi
        const alertBadge = document.getElementById('unread_notification_' + id);
        if (alertBadge) {
            alertBadge.style.display = 'none';
        }
    }

    function sendAdminMessage(e) {
        if (e) e.preventDefault();
        const text = document.getElementById('admin-input-text').value.trim();
        if (!text || activeReceiverId === 0) return;

        appendMsgRow(text, 'my');
        document.getElementById('admin-input-text').value = '';

        fetch('send_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    receiver_id: activeReceiverId,
                    message_text: text
                })
            })
            .then(res => res.json())
            .then(res => {
                if (res.status !== 'success') {
                    console.error("Lỗi gửi tin nhắn");
                }
            });
    }

    function appendMsgRow(text, type) {
        const body = document.getElementById('admin-chat-body');
        const row = document.createElement('div');
        if (type === 'my') {
            row.className = "msg-bubble-user";
            row.style.background = "#1e293b";
        } else {
            row.className = "msg-bubble-admin";
        }

        // 🔑 FIX QUAN TRỌNG: Sử dụng innerHTML để hiển thị thẻ sản phẩm đồng bộ phía Admin
        row.innerHTML = text;

        body.appendChild(row);
        body.scrollTop = body.scrollHeight;
    }
    document.getElementById('admin-input-text').onkeypress = (e) => {
        if (e.key === 'Enter') sendAdminMessage();
    };

    const pusher = new Pusher('94c4c17f4353f8cdc5af', {
        cluster: 'ap1'
    });
    const channel = pusher.subscribe('store-channel');

    channel.bind('chat-message-event', function(data) {
        let payloadData = data;
        if (typeof data === 'string') {
            payloadData = JSON.parse(data);
        }

        let msg = payloadData;
        if (payloadData.data) {
            msg = (typeof payloadData.data === 'string') ? JSON.parse(payloadData.data) : payloadData.data;
        }

        if (!msg || !msg.sender_id) return;

        // Trường hợp A: Khách hàng đang mở tab hội thoại nhắn tin tới
        if (parseInt(msg.sender_id) === parseInt(activeReceiverId) && parseInt(msg.receiver_id) ===
            adminUserId) {
            appendMsgRow(msg.message_text, 'guest');
            playChatSound();
        }
        // Trường hợp B: Khách hàng VÃNG LAI/KHÁC nhắn tin tới (khi admin đang ở tab người khác)
        else if (parseInt(msg.receiver_id) === adminUserId) {
            // 🔔 KÍCH HOẠT HIỂN THỊ CHỮ "(Tin mới)" MÀU ĐỎ NHẤP NHÁY Ở USER ĐÓ
            const targetBadge = document.getElementById('unread_notification_' + msg.sender_id);
            if (targetBadge) {
                targetBadge.style.display = 'inline-block';
            }
            playChatSound();
        }
    });

    function playChatSound() {
        try {
            let audio = new Audio("https://assets.mixkit.co/active_storage/sfx/2869/2869-600.wav");
            audio.play();
        } catch (e) {}
    }
    </script>
</body>

</html>