-- 1. Add the new decimal column
ALTER TABLE expenses ADD COLUMN amount REAL;

-- 2. Copy and convert data
UPDATE expenses SET amount = amount_cents / 100.0;