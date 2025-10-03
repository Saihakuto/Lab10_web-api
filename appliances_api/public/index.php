<?php
// public/index.php

// 1. กำหนด path และ autoload
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Response.php';
require_once __DIR__ . '/../src/ApplianceController.php';

// 2. ตั้งค่า Header (รวมถึง CORS)
Response::setHeaders();

// 3. จัดการ OPTIONS Request สำหรับ CORS Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 4. เตรียมข้อมูลพื้นฐานของ Request
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
// แยก path ออกมา (ตัวอย่าง: /appliances_api/public/api/appliances/3 -> ['', 'appliances_api', 'public', 'api', 'appliances', '3'])
$path = explode('/', trim(parse_url($uri, PHP_URL_PATH), '/'));

// 5. เชื่อมต่อฐานข้อมูล
$database = new Database();
$db = $database->getConnection();
$controller = new ApplianceController($db);

// 6. Routing (จัดการเส้นทาง)
// คาดหวังรูปแบบ: /.../api/appliances[/id]
// index = 4 คือ 'appliances'
$resource = $path[4] ?? null; 
$id = $path[5] ?? null;

if ($resource === 'appliances') {
    switch ($method) {
        // GET /api/appliances
        case 'GET':
            if ($id) {
                // GET /api/appliances/{id}
                $controller->show((int)$id);
            } else {
                // GET /api/appliances
                $controller->index();
            }
            break;

        // POST /api/appliances
        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);
            $controller->store($data ?? []);
            break;

        // PUT/PATCH /api/appliances/{id}
        case 'PUT':
        case 'PATCH':
            if ($id) {
                $data = json_decode(file_get_contents("php://input"), true);
                $controller->update((int)$id, $data ?? []);
            } else {
                Response::json(["error" => "Method Not Allowed"], 405);
            }
            break;

        // DELETE /api/appliances/{id}
        case 'DELETE':
            if ($id) {
                $controller->destroy((int)$id);
            } else {
                Response::json(["error" => "Method Not Allowed"], 405);
            }
            break;

        default:
            // หาก Method อื่นๆ
            Response::json(["error" => "Method Not Allowed"], 405);
            break;
    }
} else {
    // 404 Not Found สำหรับเส้นทางที่ไม่รองรับ
    Response::json(["error" => "Not Found"], 404);
}