-- Migration 010: per-agency branding (logo, color palette) and per-agency,
-- per-page, per-language landing content overrides.
--
-- Content is stored as one JSON blob per (agency, page, language) rather than
-- one column/table per field: the public pages define dozens of independently
-- editable text fields per page (headings, feature blurbs, team members,
-- testimonials...), and a fixed relational schema for all of them would mean
-- a very wide table or dozens of small tables for no real benefit -- nothing
-- ever queries into individual fields, they are always read back as a whole
-- per page render. See app/agency_content.php.

ALTER TABLE agencies
    ADD COLUMN IF NOT EXISTS logo_path VARCHAR(255) NULL AFTER custom_domain_verified_at,
    ADD COLUMN IF NOT EXISTS primary_color VARCHAR(7) NULL AFTER logo_path,
    ADD COLUMN IF NOT EXISTS secondary_color VARCHAR(7) NULL AFTER primary_color,
    ADD COLUMN IF NOT EXISTS accent_dark_color VARCHAR(7) NULL AFTER secondary_color;

CREATE TABLE IF NOT EXISTS agency_page_content (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agency_id BIGINT UNSIGNED NOT NULL,
    page VARCHAR(30) NOT NULL,
    language_code CHAR(2) NOT NULL,
    content_json JSON NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by BIGINT UNSIGNED NULL,
    UNIQUE KEY uq_agency_page_content (agency_id, page, language_code),
    CONSTRAINT fk_agency_page_content_agency FOREIGN KEY (agency_id) REFERENCES agencies(id),
    CONSTRAINT fk_agency_page_content_user FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
