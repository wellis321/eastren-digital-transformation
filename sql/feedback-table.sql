-- Feedback submissions from the portal feedback widget.
-- Also created automatically on first use via feedback.php.

CREATE TABLE IF NOT EXISTS feedback (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type         VARCHAR(30)  NOT NULL DEFAULT 'other',
  message      TEXT         NOT NULL,
  page         VARCHAR(500) NOT NULL DEFAULT '',
  submitted_by VARCHAR(120) NOT NULL DEFAULT '',
  ip           VARCHAR(45)  NOT NULL DEFAULT '',
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_feedback_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
