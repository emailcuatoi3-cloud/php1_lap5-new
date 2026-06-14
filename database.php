<?php


if(session_status() === PHP_SESSION_NONE){
    session_start();
}
require_once 'config.php';
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
class Database {
    private $host;
    private $username;
    private $password;
    private $database;
    public $connection;
    
    public function __construct()
    {
        $this->host = HOST;
        $this->password = PASSWORD;
        $this->username = USERNAME;
        $this->database = DATABASE;
    }

    public function getConnection() {
        try {
            $conn = new PDO("mysql:host=$this->host;dbname=$this->database", $this->username, $this->password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // echo "Connected successfully";
            return $this->connection = $conn;
          } catch(PDOException $e) {
            echo "Connection failed: " . $e->getMessage();
          }
    }
}