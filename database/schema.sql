-- Kitchen Keep — MySQL Schema
-- Run this against the dlvippt8_kk database to create all required tables.
-- All tables use the InnoDB engine and utf8mb4 charset for full Unicode support.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- -----------------------------------------------------------------------
-- Users
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            VARCHAR(36)  NOT NULL,
  `username`      VARCHAR(255) NOT NULL,
  `real_name`     VARCHAR(255) NOT NULL DEFAULT '',
  `email`         VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL DEFAULT '',
  `role`          VARCHAR(50)  NOT NULL DEFAULT 'user',
  `verified`      TINYINT(1)   NOT NULL DEFAULT 0,
  `verify_token`  VARCHAR(255)          DEFAULT NULL,
  `reset_token`   VARCHAR(255)          DEFAULT NULL,
  `reset_expires` INT                   DEFAULT NULL,
  `display_name`  VARCHAR(255) NOT NULL DEFAULT '',
  `bio`           TEXT         NOT NULL,
  `avatar`        VARCHAR(500)          DEFAULT NULL,
  `theme`         VARCHAR(50)  NOT NULL DEFAULT 'light',
  `created_at`    INT          NOT NULL DEFAULT 0,
  `updated_at`    INT          NOT NULL DEFAULT 0,
  `deleted_at`    INT                   DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  UNIQUE KEY `uq_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Recipes
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `recipes` (
  `id`           VARCHAR(36)   NOT NULL,
  `author_id`    VARCHAR(36)   NOT NULL,
  `title`        VARCHAR(500)  NOT NULL DEFAULT '',
  `slug`         VARCHAR(500)  NOT NULL,
  `description`  TEXT          NOT NULL,
  `servings`     INT           NOT NULL DEFAULT 4,
  `yield_amount` DECIMAL(10,3)          DEFAULT NULL,
  `yield_unit`   VARCHAR(100)           DEFAULT NULL,
  `ingredients`  JSON          NOT NULL,
  `steps`        JSON          NOT NULL,
  `tags`         JSON          NOT NULL,
  `categories`   JSON          NOT NULL,
  `images`       JSON          NOT NULL,
  `status`       VARCHAR(50)   NOT NULL DEFAULT 'draft',
  `deleted_at`   INT                    DEFAULT NULL,
  `created_at`   INT           NOT NULL DEFAULT 0,
  `updated_at`   INT           NOT NULL DEFAULT 0,
  `rating_avg`   DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  `rating_count` INT           NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug` (`slug`),
  KEY `idx_author` (`author_id`),
  KEY `idx_status` (`status`),
  KEY `idx_deleted` (`deleted_at`),
  KEY `idx_created` (`created_at`),
  FULLTEXT KEY `ft_recipes` (`title`, `description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Cookbooks
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cookbooks` (
  `id`          VARCHAR(36)  NOT NULL,
  `owner_id`    VARCHAR(36)  NOT NULL,
  `title`       VARCHAR(500) NOT NULL DEFAULT '',
  `description` TEXT         NOT NULL,
  `share_token` VARCHAR(255)          DEFAULT NULL,
  `sections`    JSON         NOT NULL,
  `created_at`  INT          NOT NULL DEFAULT 0,
  `updated_at`  INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_share_token` (`share_token`),
  KEY `idx_owner` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Ratings (one row per user per recipe)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ratings` (
  `recipe_id` VARCHAR(36) NOT NULL,
  `user_id`   VARCHAR(36) NOT NULL,
  `score`     TINYINT     NOT NULL,
  `rated_at`  INT         NOT NULL DEFAULT 0,
  PRIMARY KEY (`recipe_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Ingredient dictionary
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ingredients` (
  `id`              VARCHAR(36)   NOT NULL,
  `name`            VARCHAR(255)  NOT NULL,
  `aliases`         JSON          NOT NULL,
  `category`        VARCHAR(100)  NOT NULL DEFAULT 'general',
  `density_g_per_ml` DECIMAL(10,4)         DEFAULT NULL,
  `created_by`      VARCHAR(36)            DEFAULT NULL,
  `created_at`      INT           NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  FULLTEXT KEY `ft_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Moderation flags
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `moderation_flags` (
  `id`          VARCHAR(16)  NOT NULL,
  `recipe_id`   VARCHAR(36)  NOT NULL,
  `user_id`     VARCHAR(36)  NOT NULL,
  `reason`      VARCHAR(100) NOT NULL DEFAULT '',
  `details`     TEXT         NOT NULL,
  `active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  INT          NOT NULL DEFAULT 0,
  `resolved_at` INT                   DEFAULT NULL,
  `resolution`  VARCHAR(100)          DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_recipe` (`recipe_id`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Audit log
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`         VARCHAR(16)  NOT NULL,
  `action`     VARCHAR(255) NOT NULL,
  `context`    JSON         NOT NULL,
  `created_at` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Site configuration (key–value store)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `site_config` (
  `config_key`   VARCHAR(100) NOT NULL,
  `config_value` TEXT         NOT NULL,
  PRIMARY KEY (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default site configuration
INSERT IGNORE INTO `site_config` (`config_key`, `config_value`) VALUES
  ('site_name',    'Kitchen Keep'),
  ('tagline',      'Discover, create, and share recipes'),
  ('default_theme','light'),
  ('items_per_page','12'),
  ('from_email',   'noreply@kitchenkeep.local'),
  ('from_name',    'Kitchen Keep');
