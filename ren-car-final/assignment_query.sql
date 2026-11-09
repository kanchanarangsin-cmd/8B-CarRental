-- Assignment helper queries for the car rental management system
-- Main business query used on pages/analytics.php

SELECT c.customer_id,
       c.name AS customer_name,
       COUNT(r.rental_id) AS rental_count,
       COALESCE(SUM(r.total_price), 0) AS total_revenue,
       MIN(r.rental_date) AS first_rental_date,
       MAX(r.return_date) AS last_return_date,
       GROUP_CONCAT(DISTINCT p.payment_type ORDER BY p.payment_type SEPARATOR ', ') AS payment_types
FROM customers c
INNER JOIN rentals r ON c.customer_id = r.customer_id
LEFT JOIN payments p ON p.payment_id = REPLACE(r.rental_id, 'RENT', 'PAY')
GROUP BY c.customer_id, c.name
HAVING COUNT(r.rental_id) >= 2
ORDER BY rental_count DESC, total_revenue DESC, last_return_date DESC;

-- Monthly revenue summary for the chart on pages/analytics.php
SELECT DATE_FORMAT(r.rental_date, '%Y-%m') AS month_key,
       DATE_FORMAT(r.rental_date, '%m/%Y') AS month_label,
       COUNT(r.rental_id) AS rental_count,
       COALESCE(SUM(r.total_price), 0) AS total_revenue
FROM rentals r
LEFT JOIN payments p ON p.payment_id = REPLACE(r.rental_id, 'RENT', 'PAY')
GROUP BY DATE_FORMAT(r.rental_date, '%Y-%m'), DATE_FORMAT(r.rental_date, '%m/%Y')
ORDER BY month_key ASC;

-- Quick record count checks before submission
SELECT COUNT(*) AS total_cars FROM cars;
SELECT COUNT(*) AS total_customers FROM customers;
SELECT COUNT(*) AS total_employees FROM employees;
SELECT COUNT(*) AS total_rentals FROM rentals;
SELECT COUNT(*) AS total_payments FROM payments;
SELECT COUNT(*) AS total_reviews FROM reviews;
