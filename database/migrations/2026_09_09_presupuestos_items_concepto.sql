ALTER TABLE quote_items
  ADD COLUMN is_concept TINYINT(1) NOT NULL DEFAULT 0 AFTER is_manual;

ALTER TABLE quotes
  ADD COLUMN materials_total_concept VARCHAR(190) NULL AFTER materials_amount;
