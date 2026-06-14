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
$success_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $fullname = trim($_POST['fullname']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate dữ liệu đầu vào
    if (empty($username) || empty($fullname) || empty($email) || empty($password)) {
        $errors[] = "Vui lòng điền đầy đủ tất cả các trường!";
    } else {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Định dạng Email không hợp lệ!";
        }
        if (strlen($password) < 8) {
            $errors[] = "Mật khẩu phải chứa ít nhất 8 ký tự!";
        }
        if ($password !== $confirm_password) {
            $errors[] = "Nhập lại mật khẩu không trùng khớp!";
        }
    }

    // Kiểm tra trùng lặp trong DB
    if (empty($errors)) {
        $check_user = $db_untils->getOne("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
        if ($check_user) {
            $errors[] = "Tên tài khoản hoặc Email đã tồn tại trên hệ thống!";
        }
    }

    // Tiến hành đăng ký
    if (empty($errors)) {
        // Băm mật khẩu bằng thuật toán an toàn Bcrypt
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        $sql = "INSERT INTO users (username, fullname, email, password) VALUES (?, ?, ?, ?)";
        $result = $db_untils->execute($sql, [$username, $fullname, $email, $hashed_password]);

        if ($result) {
            $success_msg = "Đăng ký thành công! Bạn sẽ được chuyển hướng sang trang đăng nhập.";
            header("refresh:2;url=login.php");
        } else {
            $errors[] = "Có lỗi xảy ra trong quá trình đăng ký, vui lòng thử lại!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title> Đăng ký tài khoản</title>
    <link rel="stylesheet" href="./style.css?v=<?= time() ?>">
</head>

<body>

    <div class="auth-container">
        <h2>Đăng Ký Tài Khoản</h2>

        <?php if (!empty($success_msg)){ ?>
        <div class="alert-success"><?= $success_msg ?></div>
        <?php } ?>

        <?php foreach ($errors as $error) { ?>
        <div class="alert-danger"><?= $error ?></div>
        <?php } ?>

        <form method="POST" action="register.php">
            <div class="form-group">
                <label>Tên tài khoản (Username)</label>
                <input type="text" name="username" placeholder="Nhập tên đăng nhập viết liền"
                    value="<?= isset($username) ? htmlspecialchars($username) : '' ?>">
            </div>

            <div class="form-group">
                <label>Họ và tên</label>
                <input type="text" name="fullname" placeholder="Nhập đầy đủ họ tên"
                    value="<?= isset($fullname) ? htmlspecialchars($fullname) : '' ?>">
            </div>

            <div class="form-group">
                <label>Địa chỉ Email</label>
                <input type="email" name="email" placeholder="example@gmail.com"
                    value="<?= isset($email) ? htmlspecialchars($email) : '' ?>">
            </div>

            <div class="form-group">
                <label>Mật khẩu</label>
                <input type="password" name="password" placeholder="Tối thiểu 8 ký tự">
            </div>

            <div class="form-group">
                <label>Nhập lại mật khẩu</label>
                <input type="password" name="confirm_password" placeholder="Xác nhận lại mật khẩu">
            </div>

            <button type="submit" class="btn-auth">Đăng Ký</button>
            <p>Đã có tài khoản? <a href="login.php">Đăng nhập ngay</a></p>
        </form>
    </div>

</body>

</html>