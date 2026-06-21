ALTER TABLE job_order_attachments
    ADD COLUMN IF NOT EXISTS revision_notes TEXT NULL AFTER version_no;

UPDATE job_order_attachments
SET version_no = 'Original'
WHERE version_no IS NULL
   OR TRIM(version_no) = ''
   OR LOWER(TRIM(version_no)) IN ('v1', 'version 1');
