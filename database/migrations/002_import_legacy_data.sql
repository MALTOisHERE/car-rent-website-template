-- Import path from the repository's current legacy tables: user, car, reservation.
-- This migration is additive and idempotent through legacy_id unique keys.
-- Verify a current backup before applying it to an existing database.

START TRANSACTION;

INSERT INTO agencies (name, code, city, country_code, currency, timezone)
SELECT 'Main Agency', 'MAIN', 'Agadir', 'MA', 'MAD', 'Africa/Casablanca'
WHERE NOT EXISTS (SELECT 1 FROM agencies WHERE code = 'MAIN');

SET @legacy_agency_id = (SELECT id FROM agencies WHERE code = 'MAIN' LIMIT 1);

INSERT INTO vehicle_categories (agency_id, name, code, base_daily_price, recommended_deposit)
SELECT @legacy_agency_id, 'Standard', 'STANDARD', 0.00, 0.00
WHERE NOT EXISTS (
    SELECT 1 FROM vehicle_categories WHERE agency_id = @legacy_agency_id AND code = 'STANDARD'
);

SET @legacy_category_id = (
    SELECT id FROM vehicle_categories WHERE agency_id = @legacy_agency_id AND code = 'STANDARD' LIMIT 1
);

INSERT IGNORE INTO users (
    legacy_id, fullname, email, email_normalized, phone, password_hash, role, status, password_changed_at
)
SELECT
    u.iduser,
    COALESCE(NULLIF(TRIM(u.fullname), ''), u.email),
    LOWER(TRIM(u.email)),
    LOWER(TRIM(u.email)),
    u.phone,
    u.password,
    CASE
        WHEN LOWER(CAST(u.role AS CHAR)) IN ('admin', '1', 'owner') THEN 'OWNER'
        ELSE 'CUSTOMER'
    END,
    'active',
    NOW()
FROM user u
WHERE u.email IS NOT NULL AND TRIM(u.email) <> '';

INSERT IGNORE INTO user_agencies (user_id, agency_id, is_primary)
SELECT id, @legacy_agency_id, 1
FROM users
WHERE role <> 'CUSTOMER';

INSERT IGNORE INTO customers (
    legacy_id, agency_id, user_id, customer_type, first_name, last_name,
    phone, phone_normalized, email, email_normalized, status
)
SELECT
    u.iduser,
    @legacy_agency_id,
    nu.id,
    'individual',
    COALESCE(NULLIF(SUBSTRING_INDEX(TRIM(u.fullname), ' ', 1), ''), 'Legacy'),
    CASE
        WHEN LOCATE(' ', TRIM(u.fullname)) > 0
            THEN TRIM(SUBSTRING(TRIM(u.fullname), LOCATE(' ', TRIM(u.fullname)) + 1))
        ELSE 'Customer'
    END,
    u.phone,
    REPLACE(REPLACE(REPLACE(TRIM(u.phone), ' ', ''), '-', ''), '+', ''),
    LOWER(TRIM(u.email)),
    LOWER(TRIM(u.email)),
    'regular'
FROM user u
JOIN users nu ON nu.legacy_id = u.iduser OR nu.email_normalized = LOWER(TRIM(u.email));

INSERT IGNORE INTO vehicles (
    legacy_id, agency_id, category_id, registration_number, brand, model,
    transmission, seats, doors, luggage_capacity, base_daily_price, status, primary_image_path
)
SELECT
    c.idcar,
    @legacy_agency_id,
    @legacy_category_id,
    CONCAT('LEGACY-', c.idcar),
    COALESCE(NULLIF(TRIM(c.name), ''), 'Unknown'),
    COALESCE(NULLIF(TRIM(c.name), ''), 'Unknown'),
    CASE WHEN c.type = 1 THEN 'automatic' ELSE 'manual' END,
    c.seat,
    c.door,
    c.bag,
    CAST(COALESCE(c.price, 0) AS DECIMAL(12,2)),
    'available',
    c.image
FROM car c;

INSERT IGNORE INTO reservations (
    legacy_id, reference, agency_id, return_agency_id, customer_id, vehicle_id,
    category_id, status, source, pickup_at, return_at, pickup_location,
    return_location, currency, daily_price, rental_days, total_amount,
    remaining_amount, pricing_snapshot_json, created_at
)
SELECT
    r.idres,
    CONCAT('LEG-', LPAD(r.idres, 8, '0')),
    @legacy_agency_id,
    @legacy_agency_id,
    nc.id,
    nv.id,
    nv.category_id,
    CASE
        WHEN CONCAT(r.Date_fin, ' ', COALESCE(r.heureFin, '23:59:59')) < NOW() THEN 'expired'
        WHEN r.confirm = 1 THEN 'confirmed'
        ELSE 'pending'
    END,
    'website',
    CONCAT(r.Date_debut, ' ', COALESCE(r.heureDebut, '00:00:00')),
    CONCAT(r.Date_fin, ' ', COALESCE(r.heureFin, '23:59:59')),
    r.depart,
    r.arrive,
    'MAD',
    nv.base_daily_price,
    GREATEST(1, DATEDIFF(r.Date_fin, r.Date_debut)),
    nv.base_daily_price * GREATEST(1, DATEDIFF(r.Date_fin, r.Date_debut)),
    nv.base_daily_price * GREATEST(1, DATEDIFF(r.Date_fin, r.Date_debut)),
    JSON_OBJECT(
        'source', 'legacy_import',
        'daily_price', nv.base_daily_price,
        'days', GREATEST(1, DATEDIFF(r.Date_fin, r.Date_debut))
    ),
    NOW()
FROM reservation r
JOIN customers nc ON nc.legacy_id = r.iduser
JOIN vehicles nv ON nv.legacy_id = r.idcar;

INSERT INTO reservation_status_history (reservation_id, from_status, to_status, reason)
SELECT nr.id, NULL, nr.status, 'Imported from legacy reservation'
FROM reservations nr
WHERE nr.legacy_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM reservation_status_history h
      WHERE h.reservation_id = nr.id AND h.reason = 'Imported from legacy reservation'
  );

INSERT IGNORE INTO schema_migrations (version) VALUES ('002_import_legacy_data');

COMMIT;
