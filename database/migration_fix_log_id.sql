-- Migration to allow incident creation without assigned device
-- Run this script to update the existing database

USE vitalwear;

-- Make log_id nullable in incident table
ALTER TABLE incident MODIFY COLUMN log_id INT DEFAULT NULL;
