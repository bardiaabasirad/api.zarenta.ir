-- Flatten metal_orders.product JSON into typed columns and drop the JSON column.
-- Final columns: product_name, qty, unit, fee, amount, pricing_unit_id, target_unit_id
-- pricing_unit_id / target_unit_id are FKs to units(id).
--
-- Safe to run when metal_orders has no rows (or after backing up).
-- Requires `units` table to already exist.
-- Run manually on each environment, e.g.:
--   mysql -u USER -p DATABASE < database/sql_queries/alter_metal_orders_drop_product_json.sql

-- 1) Add flattened columns (nullable first)
ALTER TABLE `metal_orders`
    ADD COLUMN `product_name` VARCHAR(255) NULL AFTER `metal_item_id`,
    ADD COLUMN `qty` DECIMAL(16, 3) NULL AFTER `product_name`,
    ADD COLUMN `unit` VARCHAR(16) NULL AFTER `qty`,
    ADD COLUMN `fee` DECIMAL(20, 2) NULL AFTER `unit`,
    ADD COLUMN `amount` DECIMAL(20, 0) NULL AFTER `fee`,
    ADD COLUMN `pricing_unit_id` VARCHAR(20) NULL AFTER `amount`,
    ADD COLUMN `target_unit_id` VARCHAR(20) NULL AFTER `pricing_unit_id`;

-- 2) Drop legacy JSON column
ALTER TABLE `metal_orders`
    DROP COLUMN `product`;

-- 3) Tighten NOT NULL on required fields
ALTER TABLE `metal_orders`
    MODIFY COLUMN `product_name` VARCHAR(255) NOT NULL,
    MODIFY COLUMN `qty` DECIMAL(16, 3) NOT NULL,
    MODIFY COLUMN `fee` DECIMAL(20, 2) NOT NULL,
    MODIFY COLUMN `amount` DECIMAL(20, 0) NOT NULL;

-- 4) Foreign keys to units
ALTER TABLE `metal_orders`
    ADD CONSTRAINT `metal_orders_pricing_unit_id_foreign`
        FOREIGN KEY (`pricing_unit_id`) REFERENCES `units` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT `metal_orders_target_unit_id_foreign`
        FOREIGN KEY (`target_unit_id`) REFERENCES `units` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT;
