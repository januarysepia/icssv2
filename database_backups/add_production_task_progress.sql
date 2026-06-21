ALTER TABLE job_workflow_steps
    ADD COLUMN IF NOT EXISTS progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER status;

UPDATE job_workflow_steps
SET progress_percent = CASE
    WHEN status = 'Completed' THEN 100
    WHEN status = 'In Progress' AND progress_percent = 0 THEN 1
    ELSE progress_percent
END;

CREATE TABLE IF NOT EXISTS production_progress_logs (
    id BIGINT NOT NULL AUTO_INCREMENT,
    workflow_step_id INT NOT NULL,
    jo_id INT NOT NULL,
    user_id INT NOT NULL,
    progress_percent TINYINT UNSIGNED NOT NULL,
    remarks VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_progress_step_time (workflow_step_id, created_at),
    KEY idx_progress_jo_time (jo_id, created_at),
    CONSTRAINT fk_progress_log_step FOREIGN KEY (workflow_step_id) REFERENCES job_workflow_steps(id) ON DELETE CASCADE,
    CONSTRAINT fk_progress_log_jo FOREIGN KEY (jo_id) REFERENCES job_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_progress_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
