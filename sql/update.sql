-- Email client schema updates

ALTER TABLE mailboxes
  ADD COLUMN attachment_quota_bytes INT NOT NULL DEFAULT 104857600,
  ADD COLUMN last_uid INT NOT NULL DEFAULT 0,
  ADD COLUMN delete_after_retrieve TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN store_sent_on_server TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS email_messages (
  id INT PRIMARY KEY AUTO_INCREMENT,
  mailbox_id INT NOT NULL,
  team_id INT NOT NULL,
  folder ENUM('inbox', 'drafts', 'sent', 'trash') NOT NULL DEFAULT 'inbox',
  subject VARCHAR(255) DEFAULT NULL,
  body TEXT DEFAULT NULL,
  from_name VARCHAR(255) DEFAULT NULL,
  from_email VARCHAR(255) DEFAULT NULL,
  to_emails TEXT DEFAULT NULL,
  cc_emails TEXT DEFAULT NULL,
  bcc_emails TEXT DEFAULT NULL,
  message_id VARCHAR(255) DEFAULT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  received_at DATETIME DEFAULT NULL,
  sent_at DATETIME DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (mailbox_id) REFERENCES mailboxes(id) ON DELETE CASCADE,
  FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_email_messages_mailbox_folder (mailbox_id, folder),
  INDEX idx_email_messages_received (received_at),
  INDEX idx_email_messages_sent (sent_at)
);

CREATE TABLE IF NOT EXISTS email_attachments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  email_id INT NOT NULL,
  mailbox_id INT NOT NULL,
  filename VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) DEFAULT NULL,
  file_size INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (email_id) REFERENCES email_messages(id) ON DELETE CASCADE,
  FOREIGN KEY (mailbox_id) REFERENCES mailboxes(id) ON DELETE CASCADE,
  INDEX idx_email_attachments_mailbox (mailbox_id)
);

CREATE TABLE IF NOT EXISTS email_templates (
  id INT PRIMARY KEY AUTO_INCREMENT,
  team_id INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  subject VARCHAR(255) DEFAULT NULL,
  body TEXT DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  UNIQUE KEY uniq_team_template_name (team_id, name)
);
