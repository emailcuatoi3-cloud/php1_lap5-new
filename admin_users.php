<?php
session_start();
require "./db_utils.php";
$db_untils = new DB_UTILS();

// 🔒 Chặn quyền truy cập ngoài Admin
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] !== 'admin' && $_SESSION['user']['username'] !== 'admin')) {
    die("<h2 style='color:red; text-align:center; margin-top:50px;'>🔒 Bạn không có quyền truy cập trang này!</h2>");
}

$error_msg = "";
$success_msg = "";

$edit_mode = false;
$edit_id = "";
$edit_username = "";
$edit_fullname = "";
$edit_email = "";
$edit_role = "user";

// --- XỬ LÝ DELETE ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = (int)$_GET['id'];
    if ($del_id == $_SESSION['user']['id']) {
        $error_msg = "⚠️ Bạn không thể tự xóa tài khoản của chính mình khi đang đăng nhập!";
    } else {
        $db_untils->execute("DELETE FROM users WHERE id = ?", [$del_id]);
        $success_msg = "❌ Đã xóa tài khoản thành công!";
    }
}

// --- XỬ LÝ LẤY DATA EDIT ---
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = (int)$_GET['id'];
    $user_to_edit = $db_untils->getOne("SELECT * FROM users WHERE id = ?", [$edit_id]);
    if ($user_to_edit) {
        $edit_mode = true;
        $edit_username = $user_to_edit['username'];
        $edit_fullname = $user_to_edit['fullname'];
        $edit_email    = $user_to_edit['email'];
        $edit_role     = $user_to_edit['role'];
    }
}

// --- XỬ LÝ LƯU POST ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = $_POST['role'] ?? 'user';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($fullname) || empty($email)) {
        $error_msg = "⚠️ Vui lòng điền đầy đủ thông tin!";
    } else {
        if (isset($_POST['is_edit_mode']) && $_POST['is_edit_mode'] === '1') {
            $u_id = (int)$_POST['user_id'];
            if (!empty($password)) {
                $hashed_pw = password_hash($password, PASSWORD_DEFAULT);
                $db_untils->execute("UPDATE users SET username=?, fullname=?, email=?, role=?, password=? WHERE id=?", [$username, $fullname, $email, $role, $hashed_pw, $u_id]);
            } else {
                $db_untils->execute("UPDATE users SET username=?, fullname=?, email=?, role=? WHERE id=?", [$username, $fullname, $email, $role, $u_id]);
            }
            header("Location: admin_users.php?success=update"); exit();
        } else {
            if (empty($password)) {
                $error_msg = "⚠️ Tài khoản thêm mới bắt buộc phải nhập mật khẩu!";
            } else {
                $check_exist = $db_untils->getOne("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
                if ($check_exist) {
                    $error_msg = "⚠️ Tên tài khoản hoặc Email này đã tồn tại!";
                } else {
                    $hashed_pw = password_hash($password, PASSWORD_DEFAULT);
                    $db_untils->execute("INSERT INTO users (username, fullname, email, password, role) VALUES (?, ?, ?, ?, ?)", [$username, $fullname, $email, $hashed_pw, $role]);
                    $success_msg = "🎉 Thêm thành viên mới thành công!";
                }
            }
        }
    }
}

if(isset($_GET['success']) && $_GET['success'] === 'update') {
    $success_msg = "🎉 Cập nhật thông tin tài khoản thành công!";
}

$all_users = $db_untils->getAll("SELECT * FROM users ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Quản Lý Thành Viên - Admin Store</title>
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

    .crud-layout {
        display: flex;
        gap: 25px;
        max-width: 1300px;
        margin: 30px auto;
        padding: 0 20px;
    }

    .form-panel {
        flex: 1;
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        height: fit-content;
    }

    .table-panel {
        flex: 2.5;
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: bold;
    }

    .badge-admin {
        background: #fee2e2;
        color: #ef4444;
    }

    .badge-user {
        background: #e0f2fe;
        color: #0284c7;
    }

    select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        background: #fff;
        margin-bottom: 12px;
        font-size: 14px;
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

    <div style="max-width: 1300px; margin: 15px auto 0; padding: 0 20px;">
        <?php if(!empty($error_msg)){ echo "<div class='error'>$error_msg</div>"; } ?>
        <?php if(!empty($success_msg)){ echo "<div class='success'>$success_msg</div>"; } ?>
    </div>

    <div class="crud-layout">
        <div class="form-panel">
            <h2><?= $edit_mode ? "📝 Sửa Thành Viên" : "➕ Thêm Thành Viên" ?></h2>
            <form method="POST" action="admin_users.php">
                <?php if($edit_mode){ ?>
                <input type="hidden" name="is_edit_mode" value="1">
                <input type="hidden" name="user_id" value="<?= $edit_id ?>">
                <?php } ?>
                <div class="form-group">
                    <label>Tài khoản (Username)</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($edit_username) ?>" required>
                </div>
                <div class="form-group">
                    <label>Họ và tên người dùng</label>
                    <input type="text" name="fullname" value="<?= htmlspecialchars($edit_fullname) ?>" required>
                </div>
                <div class="form-group">
                    <label>Địa chỉ Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($edit_email) ?>" required>
                </div>
                <div class="form-group">
                    <label>Phân quyền hệ thống</label>
                    <select name="role">
                        <option value="user" <?= $edit_role === 'user' ? 'selected' : '' ?>>👤 Khách mua hàng (user)
                        </option>
                        <option value="admin" <?= $edit_role === 'admin' ? 'selected' : '' ?>>🛡️ Quản trị viên (admin)
                        </option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Mật khẩu</label>
                    <input type="password" name="password"
                        placeholder="<?= $edit_mode ? 'Bỏ trống nếu giữ nguyên' : 'Nhập mật khẩu' ?>">
                </div>
                <button type="submit" class="btn" style="width: 100%; font-size:14px; margin-top: 10px;">
                    <?= $edit_mode ? "💾 Cập nhật" : "🚀 Thêm mới" ?>
                </button>
                <?php if($edit_mode){ ?>
                <a href="admin_users.php" class="btn"
                    style="background:#64748b; display:block; text-align:center; text-decoration:none; margin-top:8px; font-size:14px; box-sizing:border-box; width:100%;">❌
                    Hủy sửa</a>
                <?php } ?>
            </form>
        </div>

        <div class="table-panel">
            <h2>📋 Cơ sở dữ liệu tài khoản</h2>
            <table class="cart-table" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tài khoản</th>
                        <th>Thông tin</th>
                        <th>Vai trò</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($all_users as $u){ ?>
                    <tr style="<?= ($edit_mode && $edit_id == $u['id']) ? 'background: #fff7ed;' : '' ?>">
                        <td><strong>#<?= $u['id'] ?></strong></td>
                        <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                        <td style="text-align: left; font-size: 13px;">
                            👤 <strong><?= htmlspecialchars($u['fullname']) ?></strong><br>
                            ✉️ <span style="color:#64748b;"><?= htmlspecialchars($u['email']) ?></span>
                        </td>
                        <td>
                            <span class="badge <?= $u['role'] === 'admin' ? 'badge-admin' : 'badge-user' ?>">
                                <?= $u['role'] === 'admin' ? '🛡️ Admin' : '👤 User' ?>
                            </span>
                        </td>
                        <td class="action-links">
                            <a href="admin_users.php?action=edit&id=<?= $u['id'] ?>" class="btn-confirm"
                                style="background: #e2e8f0; color: #1e293b; border: 1px solid #cbd5e1; padding: 4px 10px;">✏️
                                Sửa</a>
                            <a href="admin_users.php?action=delete&id=<?= $u['id'] ?>" class="btn-cancel"
                                style="padding: 4px 10px;" onclick="return confirm('Xóa tài khoản này?')">❌ Xóa</a>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>