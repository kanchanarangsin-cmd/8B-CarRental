-- Car Rental Management System Database Schema
-- Database: car rental system

CREATE DATABASE IF NOT EXISTS `car rental system`;
USE `car rental system`;

-- Cars table
CREATE TABLE cars (
    car_id VARCHAR(20) PRIMARY KEY,
    license_plate VARCHAR(20) NOT NULL UNIQUE,
    brand VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    year INT NOT NULL,
    color VARCHAR(30) NOT NULL,
    seats INT NOT NULL,
    daily_rate DECIMAL(10,2) NOT NULL,
    status ENUM('Available', 'Rented', 'Maintenance') NOT NULL DEFAULT 'Available',
    mileage INT NOT NULL
);

-- Customers table
CREATE TABLE customers (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    id_card VARCHAR(20) NOT NULL UNIQUE,
    address VARCHAR(255) NOT NULL
);

-- Employees table
CREATE TABLE employees (
    employee_id VARCHAR(20) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    position VARCHAR(50) NOT NULL,
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active'
);

-- Payments table
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    payment_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_type ENUM('Cash', 'Credit Card', 'Bank Transfer', 'QR Payment') NOT NULL
);

-- Reviews table
CREATE TABLE reviews (
    reviews_id INT AUTO_INCREMENT PRIMARY KEY,
    comment TEXT,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review_date DATE NOT NULL
);

-- Rentals table
CREATE TABLE rentals (
    rental_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    car_id VARCHAR(20) NOT NULL,
    rental_date DATE NOT NULL,
    return_date DATE,
    status ENUM('Rented', 'Returned') NOT NULL DEFAULT 'Rented',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE RESTRICT,
    FOREIGN KEY (car_id) REFERENCES cars(car_id) ON DELETE RESTRICT
);