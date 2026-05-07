-- Migration: Add contract_id to payments table
-- This adds support for linking payments to contracts

ALTER TABLE `payments` ADD COLUMN `contract_id` int(11) DEFAULT NULL AFTER `customer_id`;
ALTER TABLE `payments` ADD FOREIGN KEY (`contract_id`) REFERENCES `contracts`(`id`) ON DELETE SET NULL;
