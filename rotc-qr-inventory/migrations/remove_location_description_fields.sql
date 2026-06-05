-- Remove location and description fields from inventory tables
-- Migration: Remove location and description columns
-- Date: 2024-01-16

-- Remove location and description columns from items table
ALTER TABLE items DROP COLUMN IF EXISTS location;
ALTER TABLE items DROP COLUMN IF EXISTS description;

-- Remove location and description columns from inventory_items table if they exist
ALTER TABLE inventory_items DROP COLUMN IF EXISTS location;
ALTER TABLE inventory_items DROP COLUMN IF EXISTS description;

-- Update any views or procedures that might reference these columns
-- (Add specific view/procedure updates if needed)

CO