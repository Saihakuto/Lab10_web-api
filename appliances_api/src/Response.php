<?php
// src/Response.php

class Response {

    /**
     * ตั้งค่า Header ที่จำเป็น: CORS และ Content-Type JSON
     */
    public static function setHeaders(): void {
        // อนุญาตให้ทุก Domain เรียกใช้ (CORS)
        header("Access-Control-Allow-Origin: *");
        // อนุญาต Method CRUD
        header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
        // ตั้งค่า Content-Type เป็น JSON เสมอ
        header("Content-Type: application/json; charset=utf-8");
    }

    /**
     * ส่ง JSON Response พร้อม HTTP Status Code
     */
    public static function json(array $data, int $status = 200): void {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit();
    }
}