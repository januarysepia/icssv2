CREATE TABLE IF NOT EXISTS drawing_revision_views (
    id INT NOT NULL AUTO_INCREMENT,
    attachment_id INT NOT NULL,
    user_id INT NOT NULL,
    viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_revision_view (attachment_id, user_id),
    KEY idx_revision_views_user (user_id, viewed_at),
    CONSTRAINT fk_revision_views_attachment
        FOREIGN KEY (attachment_id) REFERENCES job_order_attachments(id) ON DELETE CASCADE,
    CONSTRAINT fk_revision_views_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
