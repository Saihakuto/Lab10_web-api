<?php
// src/Database.php

class Database {
    private string $host = "localhost"; // XAMPP default
    private string $db_name = "webapi_demo";
    private string $username = "root";    // XAMPP default
    private string $password = "";        // XAMPP default
    public ?PDO $conn = null;

    public function getConnection(): ?PDO {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false, // สำคัญสำหรับ Prepared Statements
                ]
            );
        } catch (PDOException $exception) {
            // ในสภาพแวดล้อม Production ไม่ควรแสดงข้อผิดพลาดนี้
            // แต่สำหรับ Lab ให้แสดงเพื่อตรวจสอบ
            echo "Connection error: " . $exception->getMessage();
            exit();
        }
        return $this->conn;
    }
}