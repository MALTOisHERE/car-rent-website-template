-- Additive operational extensions for portal requests, cash control, templates, and media.
CREATE TABLE IF NOT EXISTS customer_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    reservation_id BIGINT UNSIGNED NULL,
    request_type VARCHAR(30) NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    resolution_notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    resolved_by BIGINT UNSIGNED NULL,
    CONSTRAINT fk_customer_requests_customer FOREIGN KEY(customer_id) REFERENCES customers(id),
    CONSTRAINT fk_customer_requests_reservation FOREIGN KEY(reservation_id) REFERENCES reservations(id),
    CONSTRAINT fk_customer_requests_resolver FOREIGN KEY(resolved_by) REFERENCES users(id),
    INDEX idx_customer_requests_queue(status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cash_registers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agency_id BIGINT UNSIGNED NOT NULL,
    business_date DATE NOT NULL,
    opening_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    expected_balance DECIMAL(12,2) NULL,
    actual_balance DECIMAL(12,2) NULL,
    difference_amount DECIMAL(12,2) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    opened_by BIGINT UNSIGNED NOT NULL,
    closed_at DATETIME NULL,
    closed_by BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    CONSTRAINT fk_cash_register_agency FOREIGN KEY(agency_id) REFERENCES agencies(id),
    CONSTRAINT fk_cash_register_opener FOREIGN KEY(opened_by) REFERENCES users(id),
    CONSTRAINT fk_cash_register_closer FOREIGN KEY(closed_by) REFERENCES users(id),
    UNIQUE KEY uq_cash_register_day(agency_id,business_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agency_id BIGINT UNSIGNED NULL,
    notification_type VARCHAR(50) NOT NULL,
    language_code CHAR(2) NOT NULL,
    subject_template VARCHAR(255) NULL,
    message_template TEXT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by BIGINT UNSIGNED NULL,
    CONSTRAINT fk_notification_template_agency FOREIGN KEY(agency_id) REFERENCES agencies(id),
    CONSTRAINT fk_notification_template_user FOREIGN KEY(updated_by) REFERENCES users(id),
    UNIQUE KEY uq_notification_template(agency_id,notification_type,language_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO notification_templates(agency_id,notification_type,language_code,subject_template,message_template) VALUES
(NULL,'reservation_confirmation','en','Reservation {{reference}} confirmed','Your reservation {{reference}} is confirmed for {{pickup_at}}.'),
(NULL,'reservation_confirmation','fr','Réservation {{reference}} confirmée','Votre réservation {{reference}} est confirmée pour le {{pickup_at}}.'),
(NULL,'reservation_confirmation','ar','تأكيد الحجز {{reference}}','تم تأكيد حجزكم {{reference}} بتاريخ {{pickup_at}}.'),
(NULL,'return_reminder','en','Vehicle return reminder','Reminder: vehicle {{vehicle}} is due on {{return_at}}.'),
(NULL,'return_reminder','fr','Rappel de retour du véhicule','Rappel : le véhicule {{vehicle}} doit être retourné le {{return_at}}.'),
(NULL,'return_reminder','ar','تذكير بإرجاع السيارة','تذكير: يجب إرجاع السيارة {{vehicle}} في {{return_at}}.');

INSERT IGNORE INTO schema_migrations(version) VALUES('003_operational_extensions');
