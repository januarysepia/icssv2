START TRANSACTION;

DELETE sph
FROM supplier_price_history sph
INNER JOIN inventory_items ii ON ii.id = sph.inventory_id
WHERE ii.item_code LIKE 'DEMO-%';

DELETE isp
FROM item_suppliers isp
INNER JOIN inventory_items ii ON ii.id = isp.inventory_id
WHERE ii.item_code LIKE 'DEMO-%';

DELETE FROM inventory_items WHERE item_code LIKE 'DEMO-%';
DELETE FROM suppliers WHERE supplier_code LIKE 'DEMO-SUP-%';

COMMIT;
