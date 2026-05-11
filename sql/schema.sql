CREATE TABLE `venues` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) NOT NULL,
  `country` enum('DE','CH','AT','IT','FR') DEFAULT NULL,
  `latitude` decimal(9,6) DEFAULT NULL,
  `longitude` decimal(9,6) DEFAULT NULL,
  `type` enum('Kulturlokal','Kneipe','Festival','Shop','Café','Bar','Restaurant') DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_venues_name ON venues(name(100));
CREATE INDEX idx_venues_city ON venues(city(100));
CREATE INDEX idx_venues_address ON venues(address(100));
CREATE INDEX idx_venues_contact_person ON venues(contact_person(100));
CREATE INDEX idx_venues_contact_email ON venues(contact_email(100));
CREATE INDEX idx_venues_coordinates ON venues(latitude, longitude);
CREATE FULLTEXT INDEX idx_venues_fulltext ON venues(
    name,
    address,
    city,
    state,
    contact_email,
    contact_phone,
    contact_person,
    website,
    notes
);

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(120) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'agent') DEFAULT 'agent',
    ui_theme VARCHAR(20) NOT NULL DEFAULT 'forest',
    venues_page_size INT NOT NULL DEFAULT 25,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    expires_at TIMESTAMP,
    last_refreshed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(255),
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE teams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE team_members (
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('member', 'admin') NOT NULL DEFAULT 'member',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (team_id, user_id),
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE mailboxes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    team_id INT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    name VARCHAR(120) NOT NULL,
    display_name VARCHAR(120) DEFAULT NULL,
    imap_host VARCHAR(255) NOT NULL,
    imap_port INT NOT NULL DEFAULT 993,
    imap_username VARCHAR(255) NOT NULL,
    imap_password TEXT DEFAULT NULL,
    imap_encryption ENUM('ssl', 'tls', 'none') NOT NULL DEFAULT 'ssl',
    smtp_host VARCHAR(255) NOT NULL,
    smtp_port INT NOT NULL DEFAULT 587,
    smtp_username VARCHAR(255) NOT NULL,
    smtp_password TEXT DEFAULT NULL,
    smtp_encryption ENUM('ssl', 'tls', 'none') NOT NULL DEFAULT 'tls',
    attachment_quota_bytes INT NOT NULL DEFAULT 104857600,
    last_uid INT NOT NULL DEFAULT 0,
    delete_after_retrieve TINYINT(1) NOT NULL DEFAULT 0,
    store_sent_on_server TINYINT(1) NOT NULL DEFAULT 0,
    auto_start_conversation_inbound TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_team_mailbox_name (team_id, name),
    UNIQUE KEY uniq_user_mailbox_name (user_id, name)
);

CREATE TABLE email_conversations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mailbox_id INT DEFAULT NULL,
    team_id INT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    subject VARCHAR(255) NOT NULL,
    subject_normalized VARCHAR(255) NOT NULL,
    participant_key VARCHAR(255) NOT NULL,
    is_closed TINYINT(1) NOT NULL DEFAULT 0,
    closed_at DATETIME DEFAULT NULL,
    last_activity_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mailbox_id) REFERENCES mailboxes(id) ON DELETE SET NULL,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_email_conversations_mailbox (mailbox_id),
    INDEX idx_email_conversations_team (team_id, last_activity_at),
    INDEX idx_email_conversations_user (user_id, last_activity_at),
    INDEX idx_email_conversations_status (is_closed, last_activity_at),
    UNIQUE KEY uniq_email_conversation_team (team_id, subject_normalized, participant_key, is_closed),
    UNIQUE KEY uniq_email_conversation_user (user_id, subject_normalized, participant_key, is_closed)
);

CREATE TABLE email_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mailbox_id INT NOT NULL,
    team_id INT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    conversation_id INT DEFAULT NULL,
    folder ENUM('inbox', 'drafts', 'sent', 'trash') NOT NULL DEFAULT 'inbox',
    subject VARCHAR(255) DEFAULT NULL,
    body TEXT DEFAULT NULL,
    body_html TEXT DEFAULT NULL,
    from_name VARCHAR(255) DEFAULT NULL,
    from_email VARCHAR(255) DEFAULT NULL,
    to_emails TEXT DEFAULT NULL,
    cc_emails TEXT DEFAULT NULL,
    bcc_emails TEXT DEFAULT NULL,
    message_id VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    received_at DATETIME DEFAULT NULL,
    sent_at DATETIME DEFAULT NULL,
    scheduled_at DATETIME DEFAULT NULL,
    start_new_conversation TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mailbox_id) REFERENCES mailboxes(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (conversation_id) REFERENCES email_conversations(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_email_messages_mailbox_folder (mailbox_id, folder),
    INDEX idx_email_messages_team (team_id),
    INDEX idx_email_messages_user (user_id),
    INDEX idx_email_messages_received (received_at),
    INDEX idx_email_messages_sent (sent_at),
    INDEX idx_email_messages_conversation (conversation_id, created_at)
);

CREATE TABLE email_attachments (
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

CREATE TABLE object_links (
    id INT PRIMARY KEY AUTO_INCREMENT,
    left_type VARCHAR(50) NOT NULL,
    left_id INT NOT NULL,
    right_type VARCHAR(50) NOT NULL,
    right_id INT NOT NULL,
    team_id INT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_object_link (left_type, left_id, right_type, right_id, team_id, user_id),
    INDEX idx_object_links_left (left_type, left_id),
    INDEX idx_object_links_right (right_type, right_id),
    INDEX idx_object_links_team (team_id),
    INDEX idx_object_links_user (user_id),
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_templates (
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

CREATE TABLE team_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    team_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    priority ENUM('A', 'B', 'C') NOT NULL DEFAULT 'B',
    due_date DATE DEFAULT NULL,
    is_template TINYINT(1) NOT NULL DEFAULT 0,
    trigger_day TINYINT(2) DEFAULT NULL,
    trigger_month TINYINT(2) DEFAULT NULL,
    trigger_last_year INT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_team_tasks_team (team_id),
    INDEX idx_team_tasks_priority (team_id, priority),
    INDEX idx_team_tasks_due (team_id, due_date),
    INDEX idx_team_tasks_trigger (team_id, is_template, trigger_month, trigger_day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE team_shows (
    id INT PRIMARY KEY AUTO_INCREMENT,
    team_id INT NOT NULL,
    name VARCHAR(255) DEFAULT NULL,
    show_date DATE NOT NULL,
    show_time TIME DEFAULT NULL,
    venue_text VARCHAR(255) DEFAULT NULL,
    artist_fee DECIMAL(10,2) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_team_shows_team (team_id),
    INDEX idx_team_shows_team_date (team_id, show_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE venue_ratings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    venue_id INT NOT NULL,
    team_id INT NOT NULL,
    rating ENUM('A', 'B', 'C') NOT NULL,
    rated_by INT DEFAULT NULL,
    rated_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_team_venue_rating (team_id, venue_id),
    INDEX idx_venue_ratings_team (team_id),
    INDEX idx_venue_ratings_venue (venue_id),
    FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (rated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    identifier VARCHAR(255) NOT NULL COMMENT 'IP address or user identifier',
    action VARCHAR(50) NOT NULL COMMENT 'Action being rate limited (e.g., login)',
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier_action (identifier, action),
    INDEX idx_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_otps (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    last_sent_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_email_otp_email (email),
    INDEX idx_email_otps_user (user_id),
    INDEX idx_email_otps_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contacts (
  id INT PRIMARY KEY AUTO_INCREMENT,
  team_id INT NOT NULL,
  firstname VARCHAR(120) NOT NULL,
  surname VARCHAR(120) DEFAULT NULL,
  email VARCHAR(255) DEFAULT NULL,
  phone VARCHAR(50) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  postal_code VARCHAR(20) DEFAULT NULL,
  city VARCHAR(100) DEFAULT NULL,
  country ENUM('DE','CH','AT','IT','FR') DEFAULT NULL,
  website VARCHAR(255) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
  INDEX idx_contacts_team (team_id),
  INDEX idx_contacts_name (team_id, firstname(100), surname(100)),
  INDEX idx_contacts_email (team_id, email(100)),
  INDEX idx_contacts_city (team_id, city(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
