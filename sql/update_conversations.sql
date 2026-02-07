CREATE TABLE email_conversations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mailbox_id INT NOT NULL,
    team_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    subject_normalized VARCHAR(255) NOT NULL,
    participant_key VARCHAR(255) NOT NULL,
    is_closed TINYINT(1) NOT NULL DEFAULT 0,
    closed_at DATETIME DEFAULT NULL,
    last_activity_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mailbox_id) REFERENCES mailboxes(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    INDEX idx_email_conversations_mailbox (mailbox_id, last_activity_at),
    INDEX idx_email_conversations_status (is_closed, last_activity_at),
    UNIQUE KEY uniq_email_conversation (mailbox_id, subject_normalized, participant_key, is_closed)
);

ALTER TABLE email_messages
    ADD COLUMN conversation_id INT DEFAULT NULL AFTER team_id,
    ADD INDEX idx_email_messages_conversation (conversation_id, created_at);

ALTER TABLE email_messages
    ADD CONSTRAINT fk_email_messages_conversation
        FOREIGN KEY (conversation_id) REFERENCES email_conversations(id)
        ON DELETE SET NULL;
