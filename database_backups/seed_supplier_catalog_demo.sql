START TRANSACTION;

INSERT INTO suppliers
    (supplier_code, supplier_name, contact_person, mobile_number, email, address, products_supplied, status, created_by)
VALUES
    ('DEMO-SUP-A', 'Demo Alpha Electrical Supply', 'Ana Demo', '09170000001',
     'alpha.demo@example.com', 'Demo Address, Quezon City', 'Electrical components and panel accessories', 'Active', 14),
    ('DEMO-SUP-B', 'Demo Beta Industrial Trading', 'Ben Demo', '09170000002',
     'beta.demo@example.com', 'Demo Address, Makati City', 'Industrial controls and electrical materials', 'Active', 14),
    ('DEMO-SUP-C', 'Demo Gamma Power Solutions', 'Cara Demo', '09170000003',
     'gamma.demo@example.com', 'Demo Address, Pasig City', 'Power distribution and automation products', 'Active', 14)
ON DUPLICATE KEY UPDATE
    supplier_name = VALUES(supplier_name),
    contact_person = VALUES(contact_person),
    mobile_number = VALUES(mobile_number),
    email = VALUES(email),
    address = VALUES(address),
    products_supplied = VALUES(products_supplied),
    status = 'Active';

INSERT INTO inventory_items
    (item_code, item_name, brand, category, quantity, unit, minimum_stock,
     storage_location, unit_price, description, created_by, item_type,
     item_condition, asset_status)
VALUES
    ('DEMO-MCCB-100A', 'Demo MCCB 3P 100A', 'Schneider Demo', 'Circuit Protection', 0, 'pcs', 5,
     'Demo Catalog', 0, 'Supplier comparison demo item', 14, 'Consumable', 'Good', 'Available'),
    ('DEMO-CONTACTOR-32A', 'Demo Magnetic Contactor 32A', 'Omron Demo', 'Controls', 0, 'pcs', 5,
     'Demo Catalog', 0, 'Supplier comparison demo item', 14, 'Consumable', 'Good', 'Available'),
    ('DEMO-CABLE-5.5', 'Demo THHN Cable 5.5mm²', 'Phelps Dodge Demo', 'Electrical Cable', 0, 'meter', 50,
     'Demo Catalog', 0, 'Supplier comparison demo item', 14, 'Consumable', 'Good', 'Available')
ON DUPLICATE KEY UPDATE
    item_name = VALUES(item_name),
    brand = VALUES(brand),
    category = VALUES(category),
    unit = VALUES(unit),
    description = VALUES(description);

INSERT INTO item_suppliers
    (inventory_id, supplier_id, supplier_item_code, unit_price, is_preferred,
     last_purchased_at, remarks, created_by)
SELECT ii.id, s.id, prices.supplier_item_code, prices.unit_price, prices.is_preferred,
       NOW(), prices.remarks, 14
FROM (
    SELECT 'DEMO-MCCB-100A' item_code, 'DEMO-SUP-A' supplier_code, 'ALPHA-MCCB-100' supplier_item_code,
           2450.00 unit_price, 1 is_preferred, 'Lowest price; 7-day lead time' remarks
    UNION ALL SELECT 'DEMO-MCCB-100A', 'DEMO-SUP-B', 'BETA-MCCB-100', 2680.00, 0, 'In stock; 2-day delivery'
    UNION ALL SELECT 'DEMO-MCCB-100A', 'DEMO-SUP-C', 'GAMMA-MCCB-100', 2895.00, 0, '30-day credit terms'

    UNION ALL SELECT 'DEMO-CONTACTOR-32A', 'DEMO-SUP-A', 'ALPHA-MC-32', 1180.00, 0, 'Regular quotation'
    UNION ALL SELECT 'DEMO-CONTACTOR-32A', 'DEMO-SUP-B', 'BETA-MC-32', 1095.00, 1, 'Lowest price and available'
    UNION ALL SELECT 'DEMO-CONTACTOR-32A', 'DEMO-SUP-C', 'GAMMA-MC-32', 1250.00, 0, 'Includes local delivery'

    UNION ALL SELECT 'DEMO-CABLE-5.5', 'DEMO-SUP-A', 'ALPHA-THHN-55', 86.50, 0, 'Price per meter'
    UNION ALL SELECT 'DEMO-CABLE-5.5', 'DEMO-SUP-B', 'BETA-THHN-55', 91.25, 0, 'Price per meter'
    UNION ALL SELECT 'DEMO-CABLE-5.5', 'DEMO-SUP-C', 'GAMMA-THHN-55', 82.75, 1, 'Lowest price; minimum 100 meters'
) prices
INNER JOIN inventory_items ii ON ii.item_code = prices.item_code
INNER JOIN suppliers s ON s.supplier_code = prices.supplier_code
ON DUPLICATE KEY UPDATE
    supplier_item_code = VALUES(supplier_item_code),
    unit_price = VALUES(unit_price),
    is_preferred = VALUES(is_preferred),
    last_purchased_at = VALUES(last_purchased_at),
    remarks = VALUES(remarks);

INSERT INTO supplier_price_history
    (item_supplier_id, inventory_id, supplier_id, unit_price, source_type,
     remarks, recorded_by, recorded_at)
SELECT isp.id, isp.inventory_id, isp.supplier_id,
       ROUND(isp.unit_price * 1.08, 2), 'Demo Quote',
       'Older demo quotation for trend testing', 14, DATE_SUB(NOW(), INTERVAL 60 DAY)
FROM item_suppliers isp
INNER JOIN inventory_items ii ON ii.id = isp.inventory_id
INNER JOIN suppliers s ON s.id = isp.supplier_id
LEFT JOIN supplier_price_history sph
    ON sph.item_supplier_id = isp.id
   AND sph.source_type = 'Demo Quote'
   AND sph.remarks = 'Older demo quotation for trend testing'
WHERE ii.item_code LIKE 'DEMO-%'
  AND s.supplier_code LIKE 'DEMO-SUP-%'
  AND sph.id IS NULL;

INSERT INTO supplier_price_history
    (item_supplier_id, inventory_id, supplier_id, unit_price, source_type,
     remarks, recorded_by, recorded_at)
SELECT isp.id, isp.inventory_id, isp.supplier_id,
       isp.unit_price, 'Demo Quote',
       'Current demo quotation', 14, NOW()
FROM item_suppliers isp
INNER JOIN inventory_items ii ON ii.id = isp.inventory_id
INNER JOIN suppliers s ON s.id = isp.supplier_id
LEFT JOIN supplier_price_history sph
    ON sph.item_supplier_id = isp.id
   AND sph.source_type = 'Demo Quote'
   AND sph.remarks = 'Current demo quotation'
WHERE ii.item_code LIKE 'DEMO-%'
  AND s.supplier_code LIKE 'DEMO-SUP-%'
  AND sph.id IS NULL;

COMMIT;
