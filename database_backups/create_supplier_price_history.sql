CREATE TABLE IF NOT EXISTS supplier_price_history (
    id INT NOT NULL AUTO_INCREMENT,
    item_supplier_id INT NULL,
    inventory_id INT NOT NULL,
    supplier_id INT NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    source_type VARCHAR(50) NOT NULL DEFAULT 'Manual Quote',
    source_id INT NULL,
    remarks VARCHAR(255) NULL,
    recorded_by INT NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_price_history_item (inventory_id, recorded_at),
    KEY idx_price_history_supplier (supplier_id, recorded_at),
    KEY idx_price_history_link (item_supplier_id),
    CONSTRAINT fk_price_history_item_supplier
        FOREIGN KEY (item_supplier_id) REFERENCES item_suppliers(id) ON DELETE SET NULL,
    CONSTRAINT fk_price_history_inventory
        FOREIGN KEY (inventory_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_price_history_supplier
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO item_suppliers
    (inventory_id, supplier_id, unit_price, last_purchased_at, created_by)
SELECT
    ii.id,
    s.id,
    CAST(SUBSTRING_INDEX(
        GROUP_CONCAT(pri.unit_price ORDER BY pr.created_at DESC, pri.id DESC SEPARATOR ','),
        ',', 1
    ) AS DECIMAL(10,2)),
    MAX(pr.created_at),
    MAX(pr.requested_by)
FROM purchase_request_items pri
INNER JOIN purchase_requests pr ON pr.id = pri.purchase_request_id
INNER JOIN inventory_items ii ON ii.item_code = pri.item_code
INNER JOIN suppliers s ON LOWER(TRIM(s.supplier_name)) = LOWER(TRIM(pri.supplier))
WHERE pri.unit_price > 0
GROUP BY ii.id, s.id;

INSERT INTO supplier_price_history
    (item_supplier_id, inventory_id, supplier_id, unit_price, source_type, source_id, remarks, recorded_by, recorded_at)
SELECT
    isp.id,
    ii.id,
    s.id,
    pri.unit_price,
    'Purchase Request',
    pr.id,
    CONCAT('Imported from ', pr.purchase_no),
    pr.requested_by,
    pr.created_at
FROM purchase_request_items pri
INNER JOIN purchase_requests pr ON pr.id = pri.purchase_request_id
INNER JOIN inventory_items ii ON ii.item_code = pri.item_code
INNER JOIN suppliers s ON LOWER(TRIM(s.supplier_name)) = LOWER(TRIM(pri.supplier))
INNER JOIN item_suppliers isp ON isp.inventory_id = ii.id AND isp.supplier_id = s.id
LEFT JOIN supplier_price_history sph
    ON sph.source_type = 'Purchase Request'
   AND sph.source_id = pr.id
   AND sph.inventory_id = ii.id
   AND sph.supplier_id = s.id
WHERE pri.unit_price > 0
  AND sph.id IS NULL;
