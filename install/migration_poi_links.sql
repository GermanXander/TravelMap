-- Migration: Create poi_links table
-- Description: Adds typed external links to points of interest (website, social media, maps, etc.)
-- Date: 2026-03-30

CREATE TABLE IF NOT EXISTS poi_links (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    poi_id      INT UNSIGNED NOT NULL,
    link_type   ENUM(
                    'website',
                    'google_maps',
                    'instagram',
                    'facebook',
                    'twitter',
                    'tripadvisor',
                    'booking',
                    'airbnb',
                    'youtube',
                    'wikipedia',
                    'other'
                ) NOT NULL DEFAULT 'website',
    url         VARCHAR(500) NOT NULL,
    label       VARCHAR(100) DEFAULT NULL,
    sort_order  TINYINT UNSIGNED DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poi_id) REFERENCES points_of_interest(id) ON DELETE CASCADE,
    INDEX idx_poi_id (poi_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
