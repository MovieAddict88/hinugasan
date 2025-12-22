-- Migration script to add room system to existing installation
ALTER TABLE `rooms` ADD COLUMN IF NOT EXISTS `current_video_id` VARCHAR(50) DEFAULT NULL AFTER `current_song_id`;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_rooms_public ON `rooms`(`is_public`, `last_active`);
CREATE INDEX IF NOT EXISTS idx_room_users_online ON `room_users`(`room_id`, `is_online`);
CREATE INDEX IF NOT EXISTS idx_room_queue_room_status ON `room_queue`(`room_id`, `status`, `added_at`);

-- Add song duration if not exists
ALTER TABLE `songs` ADD COLUMN IF NOT EXISTS `duration` INT(11) DEFAULT NULL AFTER `video_source`;