CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `songs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `song_number` varchar(6) NOT NULL,
  `title` varchar(255) NOT NULL,
  `artist` varchar(255) NOT NULL,
  `source_type` varchar(50) NOT NULL,
  `video_source` varchar(1024) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `song_number` (`song_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- New tables for room system
CREATE TABLE IF NOT EXISTS `rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_code` varchar(8) NOT NULL,
  `room_name` varchar(100) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `creator_name` varchar(50) NOT NULL,
  `creator_device` varchar(20) DEFAULT 'mobile',
  `current_song_id` int(11) DEFAULT NULL,
  `current_song_time` int(11) DEFAULT 0,
  `is_playing` tinyint(1) DEFAULT 0,
  `max_users` int(11) DEFAULT 10,
  `is_public` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_active` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_code` (`room_code`),
  KEY `last_active` (`last_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `room_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `user_name` varchar(50) NOT NULL,
  `device_type` varchar(20) DEFAULT 'mobile',
  `joined_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_online` tinyint(1) DEFAULT 1,
  `last_seen` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `room_user` (`room_id`, `user_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `room_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `song_id` int(11) NOT NULL,
  `user_name` varchar(50) NOT NULL,
  `status` enum('pending','playing','played') DEFAULT 'pending',
  `added_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `played_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`song_id`) REFERENCES `songs` (`id`) ON DELETE CASCADE,
  KEY `room_status` (`room_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;