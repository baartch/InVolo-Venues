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
