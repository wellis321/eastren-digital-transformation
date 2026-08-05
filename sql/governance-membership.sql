-- Governance membership working state + custom people + per-user opinions.
-- Also created / altered automatically on first use via includes/governance_membership.php.

CREATE TABLE IF NOT EXISTS governance_membership (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id        VARCHAR(120) NOT NULL,
  group_id         VARCHAR(40)  NOT NULL,
  vote             VARCHAR(10)  NOT NULL DEFAULT 'unset',
  sort_order       INT          NOT NULL DEFAULT 0,
  needs_assessment TINYINT(1)   NOT NULL DEFAULT 0,
  updated_by       VARCHAR(120) NOT NULL DEFAULT '',
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_person_group (person_id, group_id),
  KEY idx_group_sort (group_id, sort_order),
  KEY idx_needs_assessment (needs_assessment)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS governance_people (
  person_id   VARCHAR(120) NOT NULL PRIMARY KEY,
  name        VARCHAR(200) NOT NULL,
  role        VARCHAR(300) NOT NULL DEFAULT '',
  created_by  VARCHAR(120) NOT NULL DEFAULT '',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One Yes/Maybe/No per signed-in user, per person, per group.
CREATE TABLE IF NOT EXISTS governance_votes (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  user_label  VARCHAR(200) NOT NULL DEFAULT '',
  person_id   VARCHAR(120) NOT NULL,
  group_id    VARCHAR(80)  NOT NULL,
  vote        VARCHAR(10)  NOT NULL,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_person_group (user_id, person_id, group_id),
  KEY idx_person_group (person_id, group_id),
  KEY idx_group (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Core + custom working groups. Core four are seeded as is_core = 1.
CREATE TABLE IF NOT EXISTS governance_groups (
  group_id    VARCHAR(80)  NOT NULL PRIMARY KEY,
  label       VARCHAR(200) NOT NULL,
  blurb       VARCHAR(500) NOT NULL DEFAULT '',
  is_core     TINYINT(1)   NOT NULL DEFAULT 0,
  status      VARCHAR(20)  NOT NULL DEFAULT 'development',
  sort_order  INT          NOT NULL DEFAULT 100,
  created_by  VARCHAR(120) NOT NULL DEFAULT '',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Point-in-time membership snapshots (JSON). Auto-captured on composition changes.
CREATE TABLE IF NOT EXISTS governance_history (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  captured_at    DATETIME     NOT NULL,
  captured_by    VARCHAR(120) NOT NULL DEFAULT '',
  reason         VARCHAR(80)  NOT NULL DEFAULT '',
  content_hash   CHAR(64)     NOT NULL,
  snapshot_json  LONGTEXT     NOT NULL,
  KEY idx_captured_at (captured_at DESC),
  KEY idx_content_hash (content_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pending membership suggestions (add / remove / move) awaiting confirmer decision.
CREATE TABLE IF NOT EXISTS governance_proposals (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id            VARCHAR(120) NOT NULL,
  group_id             VARCHAR(80)  NOT NULL,
  action               VARCHAR(20)  NOT NULL,
  from_group_id        VARCHAR(80)  NULL,
  proposed_by          VARCHAR(120) NOT NULL DEFAULT '',
  proposed_by_user_id  INT UNSIGNED NOT NULL DEFAULT 0,
  status               VARCHAR(20)  NOT NULL DEFAULT 'pending',
  decided_by           VARCHAR(120) NOT NULL DEFAULT '',
  decided_at           DATETIME     NULL,
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_status_created (status, created_at),
  KEY idx_person_group (person_id, group_id),
  KEY idx_group_status (group_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
