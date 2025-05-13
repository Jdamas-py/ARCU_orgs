-- Add status field to club_members table
ALTER TABLE club_members ADD COLUMN status ENUM('pending', 'accepted', 'declined') DEFAULT 'pending';

-- Update existing records to have 'pending' status
UPDATE club_members SET status = 'pending' WHERE status IS NULL; 