<?php
// src/ApplianceController.php

class ApplianceController {
    private PDO $db;
    private string $table_name = "appliances";

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    // --- 4.1. GET /api/appliances (Read All/Search) ---
    public function index(): void {
        // การจัดการ Query Parameters (ตัวอย่าง: category, sort)
        $category = $_GET['category'] ?? null;
        $sort = $_GET['sort'] ?? 'id_asc'; // id_asc, price_desc, price_asc
        $where_clauses = [];
        $params = [];

        if ($category) {
            $where_clauses[] = "category = ?";
            $params[] = $category;
        }
        
        // สามารถเพิ่ม min_price, max_price, pagination ได้ตามโจทย์

        $where_sql = $where_clauses ? "WHERE " . implode(" AND ", $where_clauses) : "";

        $order_sql = match ($sort) {
            'price_desc' => 'ORDER BY price DESC',
            'price_asc' => 'ORDER BY price ASC',
            default => 'ORDER BY id ASC',
        };

        $query = "SELECT * FROM " . $this->table_name . " {$where_sql} {$order_sql}";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // ส่ง 200 OK
            Response::json(["data" => $result], 200);

        } catch (PDOException $e) {
            Response::json(["error" => "Internal Server Error", "details" => $e->getMessage()], 500);
        }
    }

    // --- 4.2. GET /api/appliances/{id} (Read One) ---
    public function show(int $id): void {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 1";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($item) {
                // ส่ง 200 OK
                Response::json(["data" => $item], 200);
            } else {
                // ส่ง 404 Not Found
                Response::json(["error" => "Not found"], 404);
            }
        } catch (PDOException $e) {
            Response::json(["error" => "Internal Server Error"], 500);
        }
    }

    // --- 4.3. POST /api/appliances (Create) ---
    public function store(array $data): void {
        // 1. Validation (ตัวอย่าง: ตรวจสอบฟิลด์ที่จำเป็น)
        if (empty($data['sku']) || empty($data['name']) || empty($data['price'])) {
            Response::json(["error" => "Validation failed", "details" => "Missing required fields (sku, name, price)"], 400);
        }
        
        // 2. ตรวจสอบ SKU ซ้ำ (409 Conflict)
        if ($this->isSkuExists($data['sku'])) {
             Response::json(["error" => "SKU already exists"], 409);
        }
        
        // 3. เตรียม Query และ Parameters
        $fields = ['sku', 'name', 'brand', 'category', 'price', 'stock', 'warranty_months', 'energy_rating'];
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        $field_names = implode(', ', $fields);
        
        $params = [];
        foreach ($fields as $field) {
            // ใช้ค่าที่ส่งมา หรือ Default/NULL ถ้าไม่ได้ระบุ (ตามโครงสร้างตาราง)
            $params[] = $data[$field] ?? ($field === 'stock' ? 0 : ($field === 'warranty_months' ? 12 : null));
        }

        $query = "INSERT INTO " . $this->table_name . " ({$field_names}) VALUES ({$placeholders})";

        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $last_id = $this->db->lastInsertId();

            // ดึงข้อมูลสินค้าที่สร้างใหม่กลับมา
            $this->show((int)$last_id); // ใช้ show เพื่อดึงข้อมูลพร้อม 200 OK
            // หากต้องการ 201 Created ที่แน่นอน:
            // Response::json(["message" => "Created", "id" => (int)$last_id], 201);
            
        } catch (PDOException $e) {
            Response::json(["error" => "Internal Server Error: " . $e->getMessage()], 500);
        }
    }

    // --- 4.4. PUT/PATCH /api/appliances/{id} (Update) ---
    public function update(int $id, array $data): void {
        // 1. ตรวจสอบว่าสินค้ามีอยู่จริงหรือไม่ (404 Not Found)
        if (!$this->showItemExists($id)) {
            Response::json(["error" => "Not found"], 404);
        }

        // 2. ตรวจสอบ SKU ซ้ำ (409 Conflict)
        if (!empty($data['sku']) && $this->isSkuExists($data['sku'], $id)) {
             Response::json(["error" => "SKU already exists"], 409);
        }
        
        // 3. สร้าง SET clause สำหรับฟิลด์ที่ต้องการอัพเดทเท่านั้น
        $set_clauses = [];
        $params = [];
        $allowed_fields = ['sku', 'name', 'brand', 'category', 'price', 'stock', 'warranty_months', 'energy_rating'];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $set_clauses[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($set_clauses)) {
            Response::json(["message" => "No data provided for update."], 200);
        }

        $query = "UPDATE " . $this->table_name . " SET " . implode(", ", $set_clauses) . " WHERE id = ?";
        $params[] = $id;

        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            // ดึงข้อมูลล่าสุดกลับมา (200 OK)
            $this->show($id);

        } catch (PDOException $e) {
            Response::json(["error" => "Internal Server Error: " . $e->getMessage()], 500);
        }
    }

    // --- 4.5. DELETE /api/appliances/{id} (Delete) ---
    public function destroy(int $id): void {
        // 1. ตรวจสอบว่าสินค้ามีอยู่จริงหรือไม่ (404 Not Found)
        if (!$this->showItemExists($id)) {
            Response::json(["error" => "Not found"], 404);
        }
        
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute([$id]);

            // ส่ง 200 OK
            Response::json(["message" => "Deleted"], 200);
            
        } catch (PDOException $e) {
            Response::json(["error" => "Internal Server Error"], 500);
        }
    }

    // --- Private Helpers ---

    private function isSkuExists(string $sku, ?int $ignore_id = null): bool {
        $query = "SELECT COUNT(*) FROM " . $this->table_name . " WHERE sku = ?";
        $params = [$sku];
        
        if ($ignore_id !== null) {
            $query .= " AND id != ?";
            $params[] = $ignore_id;
        }

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }
    
    private function showItemExists(int $id): bool {
        $query = "SELECT COUNT(*) FROM " . $this->table_name . " WHERE id = ? LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetchColumn() > 0;
    }
}