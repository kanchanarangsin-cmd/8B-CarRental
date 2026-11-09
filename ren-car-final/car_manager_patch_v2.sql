CREATE TABLE IF NOT EXISTS maintenance_history (
  repair_id VARCHAR(10) NOT NULL PRIMARY KEY,
  car_id VARCHAR(30) NOT NULL,
  repair_date DATE NOT NULL,
  repair_detail VARCHAR(255) DEFAULT NULL,
  repair_cost DECIMAL(10,2) DEFAULT 0,
  repair_status VARCHAR(30) DEFAULT 'Completed',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_maintenance_car FOREIGN KEY (car_id) REFERENCES cars(car_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  CONSTRAINT fk_return_inspection_rental FOREIGN KEY (rental_id) REFERENCES rentals(rental_id) ON DELETE CASCADE,
  CONSTRAINT fk_return_inspection_car FOREIGN KEY (car_id) REFERENCES cars(car_id) ON DELETE CASCADE,
  CONSTRAINT fk_return_inspection_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
