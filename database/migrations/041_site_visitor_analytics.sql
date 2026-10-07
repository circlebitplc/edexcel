-- Migration 041: Site Visitor Analytics
-- Creates tables for privacy-first, secure visitor tracking and analytics.
-- Safe for existing installs with IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS site_visitor_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    visitor_id VARCHAR(64) NOT NULL,
    session_id VARCHAR(64) NOT NULL,
    ip_hash VARCHAR(64) NULL,
    country_code VARCHAR(8) NULL,
    country_name VARCHAR(64) NULL,
    city VARCHAR(64) NULL,
    device_type VARCHAR(20) NOT NULL DEFAULT 'desktop',
    os VARCHAR(50) NULL,
    browser VARCHAR(50) NULL,
    referrer_url VARCHAR(1000) NULL,
    referrer_host VARCHAR(255) NULL,
    traffic_source VARCHAR(50) NOT NULL DEFAULT 'direct',
    entry_page VARCHAR(500) NOT NULL,
    exit_page VARCHAR(500) NULL,
    pageviews_count INT UNSIGNED NOT NULL DEFAULT 1,
    duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    is_bounce TINYINT(1) NOT NULL DEFAULT 1,
    first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_site_visitor_session (session_id),
    KEY idx_sv_visitor (visitor_id),
    KEY idx_sv_first_seen (first_seen_at),
    KEY idx_sv_last_seen (last_seen_at),
    KEY idx_sv_source (traffic_source),
    KEY idx_sv_device (device_type),
    KEY idx_sv_country (country_code),
    KEY idx_sv_entry_page (entry_page(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_visitor_pageviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id VARCHAR(64) NOT NULL,
    visitor_id VARCHAR(64) NOT NULL,
    page_url VARCHAR(500) NOT NULL,
    page_path VARCHAR(255) NOT NULL,
    page_title VARCHAR(255) NULL,
    referrer_url VARCHAR(1000) NULL,
    duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sp_session (session_id),
    KEY idx_sp_visitor (visitor_id),
    KEY idx_sp_page_path (page_path),
    KEY idx_sp_viewed_at (viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
