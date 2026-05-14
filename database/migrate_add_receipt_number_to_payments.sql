-- Add receipt_number column to payments table
ALTER TABLE `payments` ADD COLUMN `receipt_number` VARCHAR(100) DEFAULT NULL AFTER `payment_type`;
