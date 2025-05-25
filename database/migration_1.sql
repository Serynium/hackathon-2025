CREATE INDEX IF NOT EXISTS idx_expenses_user_date ON expenses (user_id, date DESC);

CREATE INDEX IF NOT EXISTS idx_expenses_category ON expenses (category);