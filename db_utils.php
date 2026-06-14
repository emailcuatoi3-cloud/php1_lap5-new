<?php
// Thay thế require bằng require_once để bảo vệ hệ thống không bị lỗi trùng lặp khai báo Class
require_once "./database.php";

class DB_UTILS {
    public $connection;
    public function __construct()
    {
        $db = new Database();
        $this->connection = $db->getConnection();
    }

    // Lấy tất cả danh sách dữ liệu
    function getAll($sql, $params = []) {
        $conn = $this->connection;
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Thực thi SELECT trả về 1 hàng duy nhất
    function getOne($sql, $params = []) {
        $conn = $this->connection;
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Thực thi các lệnh toán tử INSERT, UPDATE, DELETE
    function execute($sql, $params = []) {
        $conn = $this->connection;
        $stmt = $conn->prepare($sql);
        return $stmt->execute($params);
    }
    
    // Trả về một giá trị đơn lẻ (Ví dụ: SUM, COUNT, VALUE)
    function getValue($sql, $params = []) {
        $conn = $this->connection;
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        $stmt = null;
        return $value;
    }

    // Lấy mã ID tự động tăng vừa chèn gần nhất
    function getLastInsertId() {
        return $this->connection->lastInsertId();
    }

    function beginTransaction() {
        return $this->connection->beginTransaction();
    }

    function commit() {
        return $this->connection->commit();
    }

    function rollBack() {
        return $this->connection->rollBack();
    }
}
?>