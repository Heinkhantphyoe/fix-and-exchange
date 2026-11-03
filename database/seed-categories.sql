-- Run after fix-and-exchange.sql. Existing categories are kept unchanged.
USE fix_and_exchange;
INSERT INTO categories (category_name, description)
SELECT seed.category_name, seed.description
FROM (
    SELECT 'Textbooks' AS category_name, 'Books and study materials' AS description
    UNION ALL SELECT 'Electronics', 'Devices and electronic accessories'
    UNION ALL SELECT 'Clothing', 'Clothes and campus wear'
    UNION ALL SELECT 'Furniture', 'Furniture and household items'
    UNION ALL SELECT 'Other', 'Other reusable campus items'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM categories);
