-- Punto ERP - Series independientes y bloqueo de versiones enviadas
-- Preserva todos los presupuestos, ítems, pagos y seguimientos existentes.

ALTER TABLE quotes
  ADD COLUMN quote_series_key CHAR(32) NULL AFTER project_id,
  ADD COLUMN locked_at DATETIME NULL AFTER sent_at;

UPDATE quotes
SET quote_series_key=LOWER(LEFT(SHA2(CONCAT(
  project_id,'|',quote_category,'|',COALESCE(NULLIF(TRIM(proposal_name),''),CONCAT('presupuesto-',id))
),256),32))
WHERE quote_series_key IS NULL OR quote_series_key='';

SET @old_unique=(
  SELECT INDEX_NAME
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='quotes'
    AND NON_UNIQUE=0
    AND INDEX_NAME<>'PRIMARY'
  GROUP BY INDEX_NAME
  HAVING GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)='project_id,version_no'
  LIMIT 1
);
SET @drop_sql=IF(@old_unique IS NULL,'SELECT 1',CONCAT('ALTER TABLE quotes DROP INDEX `',REPLACE(@old_unique,'`','``'),'`'));
PREPARE drop_stmt FROM @drop_sql;
EXECUTE drop_stmt;
DEALLOCATE PREPARE drop_stmt;

ALTER TABLE quotes
  MODIFY quote_series_key CHAR(32) NOT NULL,
  MODIFY status ENUM('borrador','enviado','aprobado_inicial','aprobado_definitivo','final','rechazado') NOT NULL DEFAULT 'borrador',
  ADD UNIQUE KEY uq_quote_series_version (quote_series_key,version_no),
  ADD INDEX idx_quote_project_series (project_id,quote_series_key);

UPDATE quotes SET locked_at=COALESCE(locked_at,sent_at,created_at)
WHERE status<>'borrador' AND locked_at IS NULL;
