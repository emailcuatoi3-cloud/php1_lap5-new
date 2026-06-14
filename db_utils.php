<?php
require "./database.php";
class DB_UTILS {
    public $connection;
    public function __construct()
    {
        $db = new Database();
        $this->connection = $db->getConnection();
        
    }

    // lay tat ca. lay danhs sach
    function getAll($sql, $params = []) {
        $conn = $this->connection;
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Thực thi SELECT trả về 1 hàng
    function getOne($sql, $params = []) {
        $conn = $this->connection;
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Thực thi INSERT, UPDATE, DELETE
    function execute($sql, $params = []) {
        $conn = $this->connection;
        $stmt = $conn->prepare($sql);
        return $stmt->execute($params);
    }
    
    // Trả về giá trị đầu tiên (dạng scalar) từ câu query, ví dụ COUNT(*)
    function getValue($sql, $params = []) {
        $conn = $this->connection;
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        $stmt = null;
        return $value;
    }

    // Lấy ID vừa insert gần nhất
    function getLastInsertId() {
        return $this->connection->lastInsertId();
    }

    // Bắt đầu transaction
    function beginTransaction() {
        return $this->connection->beginTransaction();
    }

    // Xác nhận transaction
    function commit() {
        return $this->connection->commit();
    }

    // Hủy transaction nếu có lỗi
    function rollBack() {
        return $this->connection->rollBack();
    }

}