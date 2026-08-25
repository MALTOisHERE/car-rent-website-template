-- Migration 008: create the legacy car/reservation/user tables the procedural public
-- marketing site (en/, fr/, ar/) queries directly with raw SQL.
--
-- Migration 002 only imports data FROM these tables into the new authoritative schema when
-- they already exist (an upgrade path for a pre-existing legacy install) -- it never creates
-- them. On a fresh install, that leaves the legacy tables entirely missing, so every public
-- page that queries `car`/`reservation` directly (cars.php, selection.php, in all three
-- language folders) fails with a "Base table or view not found" SQLSTATE[42S02] error.
--
-- Schema recreated from the original en/rental_car.sql dump (identical copy also present in
-- fr/ and ar/) that these pages were written against, using utf8mb4 for consistency with the
-- rest of the schema (the dump used plain utf8). Two columns -- car.image and car.gear -- are
-- added beyond the dump because later edits to en/cars.php, en/selection.php, and
-- fr/cars.php started reading them.

CREATE TABLE IF NOT EXISTS car (
    idcar INT(11) NOT NULL AUTO_INCREMENT,
    name VARCHAR(45) DEFAULT NULL,
    door INT(11) DEFAULT NULL,
    bag INT(11) DEFAULT NULL,
    seat INT(11) DEFAULT NULL,
    price INT(11) DEFAULT NULL,
    type INT(11) DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL,
    gear VARCHAR(45) DEFAULT NULL,
    PRIMARY KEY (idcar),
    UNIQUE KEY idcar_UNIQUE (idcar)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user (
    iduser INT(11) NOT NULL AUTO_INCREMENT,
    name VARCHAR(45) DEFAULT NULL,
    fullname VARCHAR(45) DEFAULT NULL,
    email VARCHAR(45) DEFAULT NULL,
    phone VARCHAR(45) DEFAULT NULL,
    password VARCHAR(45) DEFAULT NULL,
    reset_token VARCHAR(255) DEFAULT NULL,
    reset_token_expiry DATETIME DEFAULT NULL,
    PRIMARY KEY (iduser),
    UNIQUE KEY iduser_UNIQUE (iduser)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservation (
    idres INT(11) NOT NULL AUTO_INCREMENT,
    depart VARCHAR(45) DEFAULT NULL,
    arrive VARCHAR(45) DEFAULT NULL,
    heureDebut TIME DEFAULT NULL,
    heureFin TIME DEFAULT NULL,
    Date_debut DATE DEFAULT NULL,
    Date_fin DATE DEFAULT NULL,
    idcar INT(11) DEFAULT NULL,
    iduser INT(11) DEFAULT NULL,
    confirm INT(11) DEFAULT NULL,
    PRIMARY KEY (idres),
    UNIQUE KEY idres_UNIQUE (idres),
    KEY f_car_idx (idcar),
    KEY f_user_idx (iduser),
    CONSTRAINT f_car FOREIGN KEY (idcar) REFERENCES car (idcar) ON DELETE NO ACTION ON UPDATE NO ACTION,
    CONSTRAINT f_user FOREIGN KEY (iduser) REFERENCES user (iduser) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Demo seed data (only meaningful on a fresh install where these tables were just created above).
INSERT IGNORE INTO car (idcar, name, door, bag, seat, price, type, image, gear) VALUES
(1, 'Dacia', 4, 3, 5, 200, 0, NULL, NULL),
(2, 'Honda', 4, 3, 5, 300, 1, NULL, NULL),
(3, 'Mercedes', 2, 3, 5, 500, 1, NULL, NULL);

INSERT IGNORE INTO user (iduser, name, fullname, email, phone, password, reset_token, reset_token_expiry) VALUES
(1, 'Abdo', 'Abdo Demo', 'abdo.demo@example.test', '0600000001', 'demo123', NULL, NULL),
(4, 'Mohamed', 'Demo Customer', 'mohamed.demo@example.test', '0600000004', 'demo123', NULL, NULL),
(5, 'Sara', 'Demo Customer', 'sara.demo@example.test', '0600000005', 'demo123', NULL, NULL),
(6, 'Youssef', 'Demo Customer', 'youssef.demo@example.test', '0600000006', 'demo123', NULL, NULL),
(7, 'Nadia', 'Demo Customer', 'nadia.demo@example.test', '0600000007', 'demo123', NULL, NULL);

INSERT IGNORE INTO reservation (idres, depart, arrive, heureDebut, heureFin, Date_debut, Date_fin, idcar, iduser, confirm) VALUES
(2, 'Agadir', 'Marrakech', '10:13:56', '01:49:00', '2025-01-01', '2025-01-27', 1, 1, 0),
(3, 'Casa', 'Rabat', '17:17:04', '01:57:00', '2025-01-09', '2025-01-27', 2, 1, 0),
(6, 'Kenitra', 'Agadir', '00:00:13', '00:00:14', '2025-01-24', '2025-01-31', 2, 4, NULL),
(7, 'Agadir', 'Marrakech', '00:00:13', '00:00:17', '2025-01-23', '2025-01-09', 2, 5, 0),
(8, 'Agadir', '', '00:00:14', '00:00:17', '2025-01-28', '2025-01-28', 1, 6, 0),
(9, 'Agadir', '', '14:00:00', '20:00:00', '2025-01-28', '2025-01-28', 1, 7, NULL);
