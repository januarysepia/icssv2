CREATE TABLE IF NOT EXISTS sales_representatives (
    id INT NOT NULL AUTO_INCREMENT,
    sales_name VARCHAR(150) NOT NULL,
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sales_representatives_name (sales_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO sales_representatives (sales_name)
SELECT DISTINCT TRIM(sales_name)
FROM job_orders
WHERE sales_name IS NOT NULL
  AND TRIM(sales_name) <> '';
