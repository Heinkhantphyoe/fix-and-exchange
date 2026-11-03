-- For older databases where item_listings.price is NOT NULL.
-- Match the current setup SQL so Exchange listings can have no cash price.
USE fix_and_exchange;
ALTER TABLE item_listings MODIFY COLUMN price DECIMAL(10, 2) NULL DEFAULT NULL;
