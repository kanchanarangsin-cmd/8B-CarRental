-- เพิ่มตารางเก็บประวัติการซ่อมรถ
CREATE TABLE IF NOT EXISTS maintenance_history (
  repair_id VARCHAR(10) NOT NULL PRIMARY KEY,
  car_id VARCHAR(30) NOT NULL,
  repair_date DATE NOT NULL,
  repair_detail VARCHAR(255) NOT NULL,
  repair_cost DECIMAL(10,2) DEFAULT 0,
  repair_status VARCHAR(30) DEFAULT 'Completed',
  CONSTRAINT fk_maintenance_car FOREIGN KEY (car_id) REFERENCES cars(car_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ตัวอย่างข้อมูลเริ่มต้นสำหรับทดสอบจำนวนครั้งที่ซ่อม
INSERT INTO maintenance_history (repair_id, car_id, repair_date, repair_detail, repair_cost, repair_status) VALUES
('REP001', 'CAR013', '2026-03-05', 'เปลี่ยนยางและตรวจเช็กช่วงล่าง', 2500.00, 'Completed'),
('REP002', 'CAR013', '2026-03-20', 'เปลี่ยนน้ำมันเครื่อง', 1800.00, 'Completed'),
('REP003', 'CAR020', '2026-03-18', 'ซ่อมระบบเบรก', 3200.00, 'Completed'),
('REP004', 'CAR028', '2026-03-12', 'ตรวจเช็กเครื่องยนต์', 2100.00, 'Completed'),
('REP005', 'CAR036', '2026-03-25', 'เปลี่ยนแบตเตอรี่', 2900.00, 'Completed')
ON DUPLICATE KEY UPDATE
  car_id = VALUES(car_id),
  repair_date = VALUES(repair_date),
  repair_detail = VALUES(repair_detail),
  repair_cost = VALUES(repair_cost),
  repair_status = VALUES(repair_status);


-- เพิ่มตารางตรวจสภาพรถคืน
CREATE TABLE IF NOT EXISTS car_return_inspections (
  inspection_id VARCHAR(10) NOT NULL PRIMARY KEY,
  rental_id VARCHAR(10) NOT NULL,
  car_id VARCHAR(30) NOT NULL,
  customer_id VARCHAR(30) DEFAULT NULL,
  return_date DATE NOT NULL,
  has_damage VARCHAR(10) NOT NULL DEFAULT 'No',
  damage_detail VARCHAR(255) DEFAULT NULL,
  fine_amount DECIMAL(10,2) DEFAULT 0,
  inspector_name VARCHAR(100) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_rental_inspection (rental_id),
  INDEX idx_return_car (car_id),
  CONSTRAINT fk_return_inspection_rental FOREIGN KEY (rental_id) REFERENCES rentals(rental_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_return_inspection_car FOREIGN KEY (car_id) REFERENCES cars(car_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_return_inspection_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ตัวอย่างข้อมูลตรวจสภาพรถคืน
INSERT INTO car_return_inspections (inspection_id, rental_id, car_id, customer_id, return_date, has_damage, damage_detail, fine_amount, inspector_name) VALUES
('RET001', 'RENT001', 'CAR001', 'CUST001', '2026-03-05', 'No', '', 0, 'Mona Jaidee'),
('RET002', 'RENT002', 'CAR002', 'CUST002', '2026-03-06', 'Yes', 'มีรอยขีดข่วนบริเวณประตูด้านซ้าย', 1500.00, 'Mona Jaidee')
ON DUPLICATE KEY UPDATE
  car_id = VALUES(car_id),
  customer_id = VALUES(customer_id),
  return_date = VALUES(return_date),
  has_damage = VALUES(has_damage),
  damage_detail = VALUES(damage_detail),
  fine_amount = VALUES(fine_amount),
  inspector_name = VALUES(inspector_name);
