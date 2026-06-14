<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nếu đã đăng nhập rồi thì tự chuyển hướng về trang chủ sản phẩm
if (isset($_SESSION['user'])) {
    header("Location: lap4.php");
    exit();
}

require "./db_utils.php";
$db_untils = new DB_UTILS();

$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username_or_email = trim($_POST['username_or_email']);
    $password = $_POST['password'];

    if (empty($username_or_email) || empty($password)) {
        $errors[] = "Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu!";
    } else {
        // Tìm tài khoản theo username hoặc email
        $user = $db_untils->getOne("SELECT * FROM users WHERE username = ? OR email = ?", [$username_or_email, $username_or_email]);
        
        if ($user) {        
            if (password_verify($password, $user['password'])) {
                // Đăng nhập thành công, lưu thông tin VÀ QUYỀN vào Session
                $_SESSION['user'] = [
                    'id'       => $user['id'],
                    'username' => $user['username'],
                    'fullname' => $user['fullname'],
                    'email'    => $user['email'],
                    'role'     => $user['role'] // Lưu quyền (admin hoặc user)
                ];
                
                // Kiểm tra xem nếu trong giỏ hàng đang có sản phẩm thì ưu tiên quay lại trang giỏ hàng để chốt đơn
                if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0) {
                    header("Location: cart.php");
                } else {
                    header("Location: lap4.php");
                }
                exit();
            } else {
                $errors[] = "Mật khẩu không chính xác!";
            }
        } else {
            $errors[] = "Tài khoản hoặc email này không tồn tại trên hệ thống!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Đăng nhập hệ thống</title>
    <link rel="stylesheet" href="./style.css?v=<?= time() ?>">
</head>

<body>

    <div class="auth-container">
        <h2>Đăng Nhập</h2>

        <?php foreach ($errors as $error) { ?>
        <div class="alert-danger"><?= $error ?></div>
        <?php } ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label>Tài khoản hoặc Email</label>
                <input type="text" name="username_or_email" placeholder="Nhập username hoặc email của bạn"
                    value="<?= isset($username_or_email) ? htmlspecialchars($username_or_email) : '' ?>">
            </div>

            <div class="form-group">
                <label>Mật khẩu</label>
                <input type="password" name="password" placeholder="Nhập mật khẩu tài khoản">
            </div>

            <button type="submit" class="btn-auth">Đăng Nhập</button>
            <p>Chưa có tài khoản? <a href="register.php">Đăng ký thành viên</a></p>
        </form>
    </div>

</body>

</html>