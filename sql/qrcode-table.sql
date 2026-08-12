-- Tracked QR codes for the portal QR scan tracker.
-- Also created/upgraded automatically on first use via includes/qrcode.php.

CREATE TABLE IF NOT EXISTS qr_links (
  id          VARCHAR(16)   NOT NULL,
  url         VARCHAR(2048) NOT NULL,
  hits        INT UNSIGNED  NOT NULL DEFAULT 0,
  created_by  VARCHAR(120)  NOT NULL DEFAULT '',
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  archived_at DATETIME      NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_qr_links_created (created_at),
  KEY idx_qr_links_archived (archived_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- For databases created before archive support:
-- ALTER TABLE qr_links ADD COLUMN archived_at DATETIME NULL DEFAULT NULL AFTER created_at;
-- ALTER TABLE qr_links ADD KEY idx_qr_links_archived (archived_at);
