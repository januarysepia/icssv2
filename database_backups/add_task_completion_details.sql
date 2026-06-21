ALTER TABLE job_workflow_steps
    ADD COLUMN IF NOT EXISTS completion_remarks TEXT NULL AFTER remarks,
    ADD COLUMN IF NOT EXISTS completion_proof VARCHAR(255) NULL AFTER completion_remarks;
