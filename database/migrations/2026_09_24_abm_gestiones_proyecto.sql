ALTER TABLE project_followup_history ADD COLUMN contact_method VARCHAR(30) NULL AFTER event_type;
ALTER TABLE project_followup_history ADD COLUMN contact_result VARCHAR(60) NULL AFTER contact_method;
