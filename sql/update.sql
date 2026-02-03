UPDATE users
  SET role = 'agent'
  WHERE role = 'team_admin';

ALTER TABLE users
  MODIFY COLUMN role ENUM('admin', 'agent') NOT NULL DEFAULT 'agent';

ALTER TABLE team_members
  ADD COLUMN role ENUM('member', 'admin') NOT NULL DEFAULT 'member' AFTER user_id;

UPDATE team_members
  SET role = 'member'
  WHERE role IS NULL;

CREATE TABLE mailboxes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    team_id INT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    name VARCHAR(120) NOT NULL,
    imap_host VARCHAR(255) NOT NULL,
    imap_port INT NOT NULL DEFAULT 993,
    imap_username VARCHAR(255) NOT NULL,
    imap_password TEXT NOT NULL,
    imap_encryption ENUM('ssl', 'tls', 'none') NOT NULL DEFAULT 'ssl',
    smtp_host VARCHAR(255) NOT NULL,
    smtp_port INT NOT NULL DEFAULT 587,
    smtp_username VARCHAR(255) NOT NULL,
    smtp_password TEXT NOT NULL,
    smtp_encryption ENUM('ssl', 'tls', 'none') NOT NULL DEFAULT 'tls',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_team_mailbox_name (team_id, name),
    UNIQUE KEY uniq_user_mailbox_name (user_id, name)
);
