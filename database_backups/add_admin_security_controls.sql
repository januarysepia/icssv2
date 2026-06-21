CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT NOT NULL AUTO_INCREMENT,
    user_id INT NULL,
    username_attempted VARCHAR(100) NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_attempts_time (attempted_at),
    KEY idx_login_attempts_user (user_id, attempted_at),
    CONSTRAINT fk_login_attempts_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE departments
    ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'Active' AFTER department_name;
