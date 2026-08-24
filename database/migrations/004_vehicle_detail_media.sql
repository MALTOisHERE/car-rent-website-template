-- Phase 3: vehicle workspace, protected gallery metadata, and concurrency support.
ALTER TABLE vehicles
    ADD UNIQUE KEY IF NOT EXISTS uq_vehicles_id_agency (id, agency_id),
    MODIFY updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6);

CREATE TABLE IF NOT EXISTS vehicle_media (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agency_id BIGINT UNSIGNED NOT NULL,
    vehicle_id BIGINT UNSIGNED NOT NULL,
    media_type VARCHAR(20) NOT NULL DEFAULT 'image',
    caption VARCHAR(255) NULL,
    alt_text VARCHAR(255) NULL,
    storage_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    width INT UNSIGNED NULL,
    height INT UNSIGNED NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 10,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    archived_at DATETIME(6) NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    archived_by BIGINT UNSIGNED NULL,
    primary_slot BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE WHEN archived_at IS NULL AND is_primary = 1 THEN vehicle_id ELSE NULL END
    ) PERSISTENT,
    CONSTRAINT chk_vehicle_media_type CHECK (media_type = 'image'),
    CONSTRAINT chk_vehicle_media_primary CHECK (is_primary IN (0, 1)),
    CONSTRAINT fk_vehicle_media_agency FOREIGN KEY (agency_id) REFERENCES agencies(id),
    CONSTRAINT fk_vehicle_media_vehicle_agency FOREIGN KEY (vehicle_id, agency_id) REFERENCES vehicles(id, agency_id),
    CONSTRAINT fk_vehicle_media_creator FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT fk_vehicle_media_updater FOREIGN KEY (updated_by) REFERENCES users(id),
    CONSTRAINT fk_vehicle_media_archiver FOREIGN KEY (archived_by) REFERENCES users(id),
    UNIQUE KEY uq_vehicle_media_active_primary (primary_slot),
    INDEX idx_vehicle_media_path (agency_id, storage_path),
    INDEX idx_vehicle_media_gallery (agency_id, vehicle_id, archived_at, sort_order, id),
    INDEX idx_vehicle_media_archive (agency_id, archived_at, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO vehicle_media (
    agency_id, vehicle_id, media_type, storage_path, original_name, mime_type,
    file_size, sort_order, is_primary, created_at, updated_at, created_by, updated_by
)
SELECT
    v.agency_id, v.id, 'image', v.primary_image_path,
    SUBSTRING_INDEX(v.primary_image_path, '/', -1),
    CASE LOWER(SUBSTRING_INDEX(v.primary_image_path, '.', -1))
        WHEN 'png' THEN 'image/png'
        WHEN 'webp' THEN 'image/webp'
        ELSE 'image/jpeg'
    END,
    0, 10, 1, v.created_at, v.updated_at, v.created_by, v.updated_by
FROM vehicles v
WHERE v.primary_image_path IS NOT NULL
  AND v.primary_image_path <> '';

INSERT IGNORE INTO schema_migrations(version) VALUES('004_vehicle_detail_media');
