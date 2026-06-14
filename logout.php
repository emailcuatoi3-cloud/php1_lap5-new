<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Xóa dữ liệu user, giữ lại giỏ hàng nếu muốn (hoặc xóa hết tùy bạn)
unset($_SESSION['user']);

// Chuyển hướng về lại trang đăng nhập
header("Location: login.php");
exit();