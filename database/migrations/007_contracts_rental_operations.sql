-- Phase 5B rental contracts and rental-operation foundation.
-- MariaDB DDL is non-transactional: every additive operation is retry-safe and
-- the migration runner is the only component allowed to record this migration.

SET @p5b_schema = DATABASE();

SET @p5b_sql = IF(
    EXISTS(SELECT 1 FROM schema_migrations WHERE version='006_finance_core'),
    'DO 0',
    'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B requires migration 006_finance_core'''
);
EXECUTE IMMEDIATE @p5b_sql;

-- Existing same-named objects are never silently modified.  These checks run
-- before any ALTER/normalization so an interrupted or hand-created partial
-- definition fails closed rather than being repaired by coercion.
CREATE TEMPORARY TABLE _p5b_expected_columns (
    table_name VARCHAR(64) NOT NULL,
    column_name VARCHAR(64) NOT NULL,
    column_type VARCHAR(100) NOT NULL,
    nullable_mode VARCHAR(8) NOT NULL DEFAULT 'ANY',
    default_mode VARCHAR(64) NOT NULL DEFAULT 'ANY',
    PRIMARY KEY(table_name,column_name)
) ENGINE=InnoDB;
INSERT INTO _p5b_expected_columns VALUES
 ('rental_contracts','agency_id','bigint(20) unsigned','ANY','ANY'),
 ('rental_contracts','issued_at','datetime(6)','YES','NULL'),
 ('rental_contracts','current_version_id','bigint(20) unsigned','ANY','ANY'),
 ('rental_contracts','cancelled_by','bigint(20) unsigned','ANY','ANY'),
 ('contract_versions','agency_id','bigint(20) unsigned','ANY','ANY'),
 ('contract_versions','predecessor_version_id','bigint(20) unsigned','ANY','ANY'),
 ('contract_versions','snapshot_sha256','char(64)','ANY','ANY'),
 ('rental_operation_idempotency_keys','origin_agency_id','bigint(20) unsigned','NO','ANY'),
 ('rental_operation_idempotency_keys','performing_agency_id','bigint(20) unsigned','YES','ANY'),
 ('rental_operation_idempotency_keys','key_hash','char(64)','NO','ANY'),
 ('rental_operation_idempotency_keys','payload_hash','char(64)','NO','ANY'),
 ('rental_operation_idempotency_keys','status','varchar(20)','NO',"'in_progress'"),
 ('rental_operation_idempotency_keys','created_by','bigint(20) unsigned','NO','ANY');
SET @p5b_bad = (
    SELECT COUNT(*) FROM information_schema.COLUMNS c
 JOIN _p5b_expected_columns e ON e.table_name=c.TABLE_NAME AND e.column_name=c.COLUMN_NAME
 WHERE c.TABLE_SCHEMA=@p5b_schema
   AND (LOWER(c.COLUMN_TYPE)<>LOWER(e.column_type)
        OR (e.nullable_mode<>'ANY' AND c.IS_NULLABLE<>e.nullable_mode)
        OR (e.default_mode='NULL' AND COALESCE(UPPER(c.COLUMN_DEFAULT),'NULL')<>'NULL')
        OR (e.default_mode<> 'ANY' AND e.default_mode<>'NULL' AND COALESCE(c.COLUMN_DEFAULT,'<NULL>')<>e.default_mode))
);
SET @p5b_sql=IF(@p5b_bad=0,'DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B schema mismatch: incompatible existing column definition''');
EXECUTE IMMEDIATE @p5b_sql;

SET @p5b_bad = (
 SELECT COUNT(*) FROM information_schema.COLUMNS c
 WHERE c.TABLE_SCHEMA=@p5b_schema
   AND ((c.TABLE_NAME='rental_contracts' AND c.COLUMN_NAME='live_reservation_id'
         AND LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(c.GENERATION_EXPRESSION,''),'`',''),' ',''),CHAR(10),''),CHAR(13),''),CHAR(9),''))
             <> LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE('CASE WHEN status IN(''draft'',''issued'',''signed'',''active'') THEN reservation_id ELSE NULL END','`',''),' ',''),CHAR(10),''),CHAR(13),''),CHAR(9),'')))
        OR (c.TABLE_NAME='inspection_photos' AND c.COLUMN_NAME='active_photo_slot'
         AND LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(c.GENERATION_EXPRESSION,''),'`',''),' ',''),CHAR(10),''),CHAR(13),''),CHAR(9),''))
             <> LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE('CASE WHEN archived_at IS NULL THEN CONCAT(inspection_id,''|'',photo_slot) ELSE NULL END','`',''),' ',''),CHAR(10),''),CHAR(13),''),CHAR(9),'')))
       )
);
SET @p5b_sql=IF(@p5b_bad=0,'DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B schema mismatch: incompatible generated expression''');
EXECUTE IMMEDIATE @p5b_sql;

SET @p5b_bad = (
 SELECT COUNT(*) FROM information_schema.COLUMNS c
 JOIN _p5b_expected_columns e ON e.table_name=c.TABLE_NAME AND e.column_name=c.COLUMN_NAME
 WHERE c.TABLE_SCHEMA=@p5b_schema AND c.EXTRA LIKE '%GENERATED%'
);
SET @p5b_sql=IF(@p5b_bad=0,'DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B schema mismatch: incompatible generated column definition''');
EXECUTE IMMEDIATE @p5b_sql;

CREATE TEMPORARY TABLE _p5b_expected_existing_indexes (
    table_name VARCHAR(64) NOT NULL,index_name VARCHAR(64) NOT NULL,
    ordered_columns VARCHAR(500) NOT NULL,non_unique TINYINT NOT NULL,
    PRIMARY KEY(table_name,index_name)
) ENGINE=InnoDB;
INSERT INTO _p5b_expected_existing_indexes VALUES
 ('rental_contracts','uq_contracts_live_reservation','live_reservation_id',0),
 ('rental_contracts','idx_contracts_agency_status_created','agency_id,status,created_at,id',1),
 ('rental_contracts','idx_contracts_agency_reservation','agency_id,reservation_id,id',1),
 ('contract_versions','uq_contract_versions_scoped_identity','id,agency_id,contract_id',0),
 ('contract_versions','idx_contract_versions_contract_language','agency_id,contract_id,language_code,version_number,id',1),
 ('contract_status_history','uq_contract_history_baseline','baseline_key',0),
 ('contract_status_history','idx_contract_history_contract','agency_id,contract_id,occurred_at,id',1),
 ('contract_acknowledgements','uq_contract_ack_version_party','contract_version_id,acknowledgement_type',0),
 ('contract_acknowledgements','idx_contract_ack_contract','agency_id,contract_id,acknowledged_at,id',1),
 ('rental_operation_idempotency_keys','uq_rental_idempotency','origin_agency_id,operation_type,key_hash',0),
 ('rental_operation_idempotency_keys','idx_rental_idem_performing','performing_agency_id,operation_type,created_at,id',1),
 ('inspection_photos','uq_inspection_active_photo_slot','active_photo_slot',0),
 ('inspection_photos','idx_inspection_photos_evidence','agency_id,inspection_id,photo_slot,archived_at,id',1);
SET @p5b_bad = (
 SELECT COUNT(*) FROM _p5b_expected_existing_indexes e
 JOIN information_schema.TABLES t ON t.TABLE_SCHEMA=@p5b_schema AND t.TABLE_NAME=e.table_name
 JOIN (
   SELECT TABLE_NAME,INDEX_NAME,MAX(NON_UNIQUE) non_unique,
          GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') ordered_columns
   FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@p5b_schema
   GROUP BY TABLE_NAME,INDEX_NAME
 ) a ON a.TABLE_NAME=e.table_name AND a.INDEX_NAME=e.index_name
 WHERE a.non_unique<>e.non_unique OR a.ordered_columns<>e.ordered_columns
);
SET @p5b_sql=IF(@p5b_bad=0,'DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B schema mismatch: incompatible existing index definition''');
EXECUTE IMMEDIATE @p5b_sql;

CREATE TEMPORARY TABLE _p5b_expected_existing_fks (
    table_name VARCHAR(64) NOT NULL,constraint_name VARCHAR(64) NOT NULL,
    local_columns VARCHAR(500) NOT NULL,referenced_table VARCHAR(64) NOT NULL,referenced_columns VARCHAR(500) NOT NULL,
    PRIMARY KEY(table_name,constraint_name)
) ENGINE=InnoDB;
INSERT INTO _p5b_expected_existing_fks VALUES
 ('rental_contracts','fk_contracts_reservation_agency','reservation_id,agency_id','reservations','id,agency_id'),
 ('rental_contracts','fk_contracts_current_version_agency','current_version_id,agency_id,id','contract_versions','id,agency_id,contract_id'),
 ('contract_versions','fk_contract_versions_predecessor_agency','predecessor_version_id,agency_id,contract_id','contract_versions','id,agency_id,contract_id'),
 ('contract_status_history','fk_contract_history_reservation','reservation_id,agency_id','reservations','id,agency_id'),
 ('contract_acknowledgements','fk_contract_ack_version','contract_version_id,agency_id,contract_id','contract_versions','id,agency_id,contract_id'),
 ('rental_operation_idempotency_keys','fk_rental_idem_performing_agency','performing_agency_id','agencies','id'),
 ('vehicle_inspections','fk_inspections_contract_agency','contract_id,agency_id','rental_contracts','id,agency_id'),
 ('vehicle_inspections','fk_inspections_reservation_agency','reservation_id,agency_id','reservations','id,agency_id'),
 ('inspection_photos','fk_inspection_photos_inspection_agency','inspection_id,agency_id','vehicle_inspections','id,agency_id');
SET @p5b_bad = (
 SELECT COUNT(*) FROM _p5b_expected_existing_fks e
 JOIN information_schema.TABLES t ON t.TABLE_SCHEMA=@p5b_schema AND t.TABLE_NAME=e.table_name
 JOIN (
   SELECT k.TABLE_NAME,k.CONSTRAINT_NAME,
          GROUP_CONCAT(k.COLUMN_NAME ORDER BY k.ORDINAL_POSITION SEPARATOR ',') local_columns,
          MAX(k.REFERENCED_TABLE_NAME) referenced_table,
          GROUP_CONCAT(k.REFERENCED_COLUMN_NAME ORDER BY k.ORDINAL_POSITION SEPARATOR ',') referenced_columns,
          MAX(r.UPDATE_RULE) update_rule,MAX(r.DELETE_RULE) delete_rule
   FROM information_schema.KEY_COLUMN_USAGE k
   JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME
   WHERE k.CONSTRAINT_SCHEMA=@p5b_schema AND k.REFERENCED_TABLE_NAME IS NOT NULL
   GROUP BY k.TABLE_NAME,k.CONSTRAINT_NAME
 ) a ON a.TABLE_NAME=e.table_name AND a.CONSTRAINT_NAME=e.constraint_name
 WHERE a.local_columns<>e.local_columns OR a.referenced_table<>e.referenced_table OR a.referenced_columns<>e.referenced_columns
    OR UPPER(a.update_rule) NOT IN('RESTRICT','NO ACTION') OR UPPER(a.delete_rule) NOT IN('RESTRICT','NO ACTION')
);
SET @p5b_sql=IF(@p5b_bad=0,'DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B schema mismatch: incompatible existing foreign-key definition''');
EXECUTE IMMEDIATE @p5b_sql;

CREATE TEMPORARY TABLE _p5b_expected_existing_checks (
    table_name VARCHAR(64) NOT NULL,constraint_name VARCHAR(64) NOT NULL,approved_clause TEXT NOT NULL,
    PRIMARY KEY(table_name,constraint_name)
) ENGINE=InnoDB;
INSERT INTO _p5b_expected_existing_checks VALUES
 ('rental_contracts','chk_contract_status','status IN(''draft'',''issued'',''signed'',''active'',''completed'',''cancelled'')');
SET @p5b_bad = (
 SELECT COUNT(*) FROM _p5b_expected_existing_checks e
 JOIN information_schema.TABLES t ON t.TABLE_SCHEMA=@p5b_schema AND t.TABLE_NAME=e.table_name
 JOIN information_schema.TABLE_CONSTRAINTS tc ON tc.CONSTRAINT_SCHEMA=@p5b_schema AND tc.TABLE_NAME=e.table_name AND tc.CONSTRAINT_NAME=e.constraint_name AND tc.CONSTRAINT_TYPE='CHECK'
 JOIN information_schema.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME
 WHERE LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(cc.CHECK_CLAUSE),'`',''),' ',''),CHAR(9),''),CHAR(10),''),CHAR(13),''),'(',''),')',''))
       NOT LIKE CONCAT('%',LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(e.approved_clause),'`',''),' ',''),CHAR(9),''),CHAR(10),''),CHAR(13),''),'(',''),')','')),'%')
);
SET @p5b_sql=IF(@p5b_bad=0,'DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B schema mismatch: incompatible existing CHECK definition''');
EXECUTE IMMEDIATE @p5b_sql;

SET @p5b_bad = (
 SELECT COUNT(*) FROM information_schema.TABLES
 WHERE TABLE_SCHEMA=@p5b_schema AND TABLE_NAME IN('rental_contracts','contract_versions','contract_status_history','contract_acknowledgements','rental_operation_idempotency_keys','vehicle_inspections','inspection_photos')
   AND (UPPER(COALESCE(ENGINE,''))<>'INNODB' OR LOWER(COALESCE(TABLE_COLLATION,''))<>'utf8mb4_unicode_ci')
);
SET @p5b_sql=IF(@p5b_bad=0,'DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B schema mismatch: incompatible existing table engine or collation''');
EXECUTE IMMEDIATE @p5b_sql;

SET @p5b_sql = IF(
    (SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=@p5b_schema
       AND TABLE_NAME IN('agencies','users','customers','vehicles','reservations','rental_contracts','contract_versions','vehicle_inspections','inspection_photos'))=9,
    'DO 0',
    'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B preflight: required authoritative tables are missing'''
);
EXECUTE IMMEDIATE @p5b_sql;

-- Fail closed before changing any legacy data.
SET @p5b_sql = (SELECT CASE WHEN EXISTS(
    SELECT 1 FROM rental_contracts
    WHERE status NOT IN('draft','generated','issued','signed','active','completed','cancelled','amended')
) THEN 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B preflight: invalid contract status''' ELSE 'DO 0' END);
EXECUTE IMMEDIATE @p5b_sql;

SET @p5b_sql = (SELECT CASE WHEN EXISTS(
    SELECT 1 FROM rental_contracts
    WHERE status='cancelled'
      AND (cancelled_at IS NULL OR COALESCE(updated_by,created_by) IS NULL
           OR CHAR_LENGTH(TRIM(COALESCE(cancellation_reason,''))) NOT BETWEEN 1 AND 255)
) THEN 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B preflight: incomplete legacy contract cancellation''' ELSE 'DO 0' END);
EXECUTE IMMEDIATE @p5b_sql;

SET @p5b_sql = (SELECT CASE WHEN EXISTS(
    SELECT 1 FROM rental_contracts
    WHERE status IN('draft','generated','issued','signed','active','amended')
    GROUP BY reservation_id HAVING COUNT(*)>1
) THEN 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B preflight: duplicate live contracts for one reservation''' ELSE 'DO 0' END);
EXECUTE IMMEDIATE @p5b_sql;

SET @p5b_sql = (SELECT CASE WHEN EXISTS(
    SELECT 1 FROM rental_contracts rc
    LEFT JOIN reservations r ON r.id=rc.reservation_id
    WHERE r.id IS NULL
) THEN 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B preflight: invalid contract reservation link''' ELSE 'DO 0' END);
EXECUTE IMMEDIATE @p5b_sql;

SET @p5b_sql = (SELECT CASE WHEN EXISTS(
    SELECT 1 FROM contract_versions cv
    LEFT JOIN rental_contracts rc ON rc.id=cv.contract_id
    WHERE rc.id IS NULL OR cv.language_code NOT IN('en','fr','ar')
) THEN 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B preflight: invalid contract version or language''' ELSE 'DO 0' END);
EXECUTE IMMEDIATE @p5b_sql;

SET @p5b_sql = (SELECT CASE WHEN EXISTS(
    SELECT 1 FROM vehicle_inspections
    WHERE inspection_type NOT IN('checkout','return')
       OR status NOT IN('draft','validated','completed','archived')
       OR fuel_level<0 OR fuel_level>100
) THEN 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B preflight: invalid inspection type, status or fuel level''' ELSE 'DO 0' END);
EXECUTE IMMEDIATE @p5b_sql;

SET @p5b_sql = (SELECT CASE WHEN EXISTS(
    SELECT 1 FROM inspection_photos
    WHERE photo_type NOT IN('front','rear','left','right','interior','dashboard')
) OR EXISTS(
    SELECT 1 FROM inspection_photos
    GROUP BY inspection_id,photo_type HAVING COUNT(*)>1
) THEN 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B preflight: duplicate active inspection photo slot''' ELSE 'DO 0' END);
EXECUTE IMMEDIATE @p5b_sql;

-- If an interrupted run left a same-named new table, its core shape must be
-- compatible. Missing secondary objects are repaired below.
SET @p5b_bad_partial = (
    SELECT COUNT(*) FROM information_schema.TABLES t
    WHERE t.TABLE_SCHEMA=@p5b_schema
      AND t.TABLE_NAME IN('contract_status_history','contract_acknowledgements','rental_operation_idempotency_keys')
      AND (
        (t.TABLE_NAME='contract_status_history' AND
          (SELECT COUNT(*) FROM information_schema.COLUMNS c WHERE c.TABLE_SCHEMA=@p5b_schema AND c.TABLE_NAME=t.TABLE_NAME AND c.COLUMN_NAME IN('id','agency_id','contract_id','from_status','to_status','occurred_at','baseline_key'))<7)
        OR
        (t.TABLE_NAME='contract_acknowledgements' AND
          (SELECT COUNT(*) FROM information_schema.COLUMNS c WHERE c.TABLE_SCHEMA=@p5b_schema AND c.TABLE_NAME=t.TABLE_NAME AND c.COLUMN_NAME IN('id','agency_id','contract_id','contract_version_id','acknowledgement_type','language_code','acknowledged_at'))<7)
        OR
        (t.TABLE_NAME='rental_operation_idempotency_keys' AND
          (SELECT COUNT(*) FROM information_schema.COLUMNS c WHERE c.TABLE_SCHEMA=@p5b_schema AND c.TABLE_NAME=t.TABLE_NAME AND c.COLUMN_NAME IN('id','origin_agency_id','operation_type','key_hash','payload_hash','created_by','status'))<7)
      )
);
SET @p5b_sql=IF(@p5b_bad_partial=0,'DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B preflight: incompatible partial rental-operation table''');
EXECUTE IMMEDIATE @p5b_sql;

CREATE TEMPORARY TABLE _p5b_legacy_contract_state (
    contract_id BIGINT UNSIGNED PRIMARY KEY,
    original_status VARCHAR(30) NOT NULL
) ENGINE=InnoDB;
INSERT INTO _p5b_legacy_contract_state(contract_id,original_status)
SELECT id,status FROM rental_contracts;

ALTER TABLE rental_contracts
    ADD COLUMN IF NOT EXISTS agency_id BIGINT UNSIGNED NULL AFTER id,
    ADD COLUMN IF NOT EXISTS issued_at DATETIME(6) NULL AFTER current_version,
    ADD COLUMN IF NOT EXISTS current_version_id BIGINT UNSIGNED NULL AFTER issued_at,
    ADD COLUMN IF NOT EXISTS cancelled_by BIGINT UNSIGNED NULL AFTER cancelled_at,
    ADD COLUMN IF NOT EXISTS live_reservation_id BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE WHEN status IN('draft','issued','signed','active') THEN reservation_id ELSE NULL END
    ) PERSISTENT,
    MODIFY signed_at DATETIME(6) NULL,
    MODIFY activated_at DATETIME(6) NULL,
    MODIFY completed_at DATETIME(6) NULL,
    MODIFY cancelled_at DATETIME(6) NULL,
    MODIFY created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    MODIFY updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6);

UPDATE rental_contracts rc
JOIN reservations r ON r.id=rc.reservation_id
SET rc.agency_id=r.agency_id,
    rc.cancelled_by=CASE WHEN rc.status='cancelled' THEN COALESCE(rc.cancelled_by,rc.updated_by,rc.created_by) ELSE rc.cancelled_by END
WHERE rc.agency_id IS NULL OR (rc.status='cancelled' AND rc.cancelled_by IS NULL);

SET @p5b_sql = (SELECT CASE WHEN EXISTS(
    SELECT 1 FROM rental_contracts rc JOIN reservations r ON r.id=rc.reservation_id
    WHERE rc.agency_id IS NULL OR rc.agency_id<>r.agency_id
) THEN 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B preflight: cross-agency contract reservation link''' ELSE 'DO 0' END);
EXECUTE IMMEDIATE @p5b_sql;

-- generated was the legacy name for an issued contract. amended had no stable
-- canonical state, so only authoritative timestamps may advance it.
UPDATE rental_contracts
SET status=CASE
        WHEN status='generated' THEN 'issued'
        WHEN status='amended' AND completed_at IS NOT NULL THEN 'completed'
        WHEN status='amended' AND activated_at IS NOT NULL THEN 'active'
        WHEN status='amended' AND signed_at IS NOT NULL THEN 'signed'
        WHEN status='amended' THEN 'issued'
        ELSE status
    END,
    issued_at=CASE
        WHEN status IN('generated','amended','issued','signed','active','completed') THEN COALESCE(issued_at,created_at)
        ELSE issued_at
    END;

SET @p5b_sql = (SELECT CASE WHEN EXISTS(
    SELECT 1 FROM rental_contracts WHERE
       (status='draft' AND (issued_at IS NOT NULL OR signed_at IS NOT NULL OR activated_at IS NOT NULL OR completed_at IS NOT NULL OR cancelled_at IS NOT NULL))
    OR (status='issued' AND (issued_at IS NULL OR signed_at IS NOT NULL OR activated_at IS NOT NULL OR completed_at IS NOT NULL OR cancelled_at IS NOT NULL))
    OR (status='signed' AND (issued_at IS NULL OR signed_at IS NULL OR activated_at IS NOT NULL OR completed_at IS NOT NULL OR cancelled_at IS NOT NULL))
    OR (status='active' AND (issued_at IS NULL OR signed_at IS NULL OR activated_at IS NULL OR completed_at IS NOT NULL OR cancelled_at IS NOT NULL))
    OR (status='completed' AND (issued_at IS NULL OR signed_at IS NULL OR activated_at IS NULL OR completed_at IS NULL OR cancelled_at IS NOT NULL))
    OR (status='cancelled' AND (cancelled_at IS NULL OR cancelled_by IS NULL OR activated_at IS NOT NULL OR completed_at IS NOT NULL))
) THEN 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B preflight: inconsistent contract lifecycle timestamps''' ELSE 'DO 0' END);
EXECUTE IMMEDIATE @p5b_sql;

ALTER TABLE rental_contracts
    MODIFY agency_id BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY IF NOT EXISTS uq_contracts_id_agency (id,agency_id),
    ADD UNIQUE KEY IF NOT EXISTS uq_contracts_live_reservation (live_reservation_id),
    ADD KEY IF NOT EXISTS idx_contracts_agency_status_created (agency_id,status,created_at,id),
    ADD KEY IF NOT EXISTS idx_contracts_agency_reservation (agency_id,reservation_id,id);

ALTER TABLE contract_versions
    ADD COLUMN IF NOT EXISTS agency_id BIGINT UNSIGNED NULL AFTER id,
    ADD COLUMN IF NOT EXISTS predecessor_version_id BIGINT UNSIGNED NULL AFTER language_code,
    ADD COLUMN IF NOT EXISTS snapshot_sha256 CHAR(64) NULL AFTER snapshot_json,
    MODIFY created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6);

SET @p5b_sql = (SELECT CASE WHEN EXISTS(
    SELECT 1 FROM contract_versions cv
    JOIN rental_contracts rc ON rc.id=cv.contract_id
    WHERE cv.agency_id IS NOT NULL AND cv.agency_id<>rc.agency_id
) THEN 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B preflight: cross-agency contract-version link''' ELSE 'DO 0' END);
EXECUTE IMMEDIATE @p5b_sql;

UPDATE contract_versions cv
JOIN rental_contracts rc ON rc.id=cv.contract_id
SET cv.agency_id=rc.agency_id,
    cv.snapshot_sha256=COALESCE(cv.snapshot_sha256,SHA2(cv.snapshot_json,256))
WHERE cv.agency_id IS NULL OR cv.snapshot_sha256 IS NULL;

SET @p5b_sql = (SELECT CASE WHEN EXISTS(
    SELECT 1 FROM contract_versions cv
    JOIN rental_contracts rc ON rc.id=cv.contract_id
    WHERE cv.agency_id IS NULL OR cv.agency_id<>rc.agency_id
) THEN 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B preflight: cross-agency contract-version link''' ELSE 'DO 0' END);
EXECUTE IMMEDIATE @p5b_sql;

ALTER TABLE contract_versions
    MODIFY agency_id BIGINT UNSIGNED NOT NULL,
    MODIFY snapshot_sha256 CHAR(64) NOT NULL,
    ADD UNIQUE KEY IF NOT EXISTS uq_contract_versions_id_agency (id,agency_id),
    ADD UNIQUE KEY IF NOT EXISTS uq_contract_versions_scoped_identity (id,agency_id,contract_id),
    ADD KEY IF NOT EXISTS idx_contract_versions_contract_language (agency_id,contract_id,language_code,version_number,id),
    ADD KEY IF NOT EXISTS idx_contract_versions_predecessor (agency_id,predecessor_version_id,id);

UPDATE rental_contracts rc
SET current_version_id=(
    SELECT cv.id FROM contract_versions cv
    WHERE cv.contract_id=rc.id AND cv.version_number=rc.current_version
    ORDER BY (cv.language_code='en') DESC,cv.id ASC LIMIT 1
)
WHERE current_version_id IS NULL
  AND EXISTS(SELECT 1 FROM contract_versions cv2 WHERE cv2.contract_id=rc.id);

CREATE TABLE IF NOT EXISTS contract_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agency_id BIGINT UNSIGNED NOT NULL,
    contract_id BIGINT UNSIGNED NOT NULL,
    reservation_id BIGINT UNSIGNED NOT NULL,
    from_status VARCHAR(30) NULL,
    to_status VARCHAR(30) NOT NULL,
    reason VARCHAR(255) NULL,
    changed_by BIGINT UNSIGNED NULL,
    occurred_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    metadata_json LONGTEXT NULL,
    baseline_key VARCHAR(190) NULL,
    CONSTRAINT fk_contract_history_agency FOREIGN KEY(agency_id) REFERENCES agencies(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_contract_history_contract FOREIGN KEY(contract_id,agency_id) REFERENCES rental_contracts(id,agency_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_contract_history_reservation FOREIGN KEY(reservation_id,agency_id) REFERENCES reservations(id,agency_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_contract_history_actor FOREIGN KEY(changed_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_contract_history_from CHECK(from_status IS NULL OR from_status IN('draft','issued','signed','active','completed','cancelled')),
    CONSTRAINT chk_contract_history_to CHECK(to_status IN('draft','issued','signed','active','completed','cancelled')),
    UNIQUE KEY uq_contract_history_baseline(baseline_key),
    KEY idx_contract_history_contract(agency_id,contract_id,occurred_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO contract_status_history(
    agency_id,contract_id,reservation_id,from_status,to_status,reason,changed_by,occurred_at,metadata_json,baseline_key
)
SELECT rc.agency_id,rc.id,rc.reservation_id,NULL,rc.status,NULL,rc.created_by,rc.created_at,
       JSON_OBJECT('source','migration_007','legacy_status',ls.original_status),
       CONCAT('migration_007_contract_',rc.id)
FROM rental_contracts rc
JOIN _p5b_legacy_contract_state ls ON ls.contract_id=rc.id;

CREATE TABLE IF NOT EXISTS contract_acknowledgements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agency_id BIGINT UNSIGNED NOT NULL,
    contract_id BIGINT UNSIGNED NOT NULL,
    contract_version_id BIGINT UNSIGNED NOT NULL,
    acknowledgement_type VARCHAR(30) NOT NULL,
    language_code CHAR(2) NOT NULL,
    party_name VARCHAR(190) NOT NULL,
    acknowledgement_method VARCHAR(30) NOT NULL DEFAULT 'in_person',
    acknowledged_at DATETIME(6) NOT NULL,
    recorded_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_contract_ack_agency FOREIGN KEY(agency_id) REFERENCES agencies(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_contract_ack_contract FOREIGN KEY(contract_id,agency_id) REFERENCES rental_contracts(id,agency_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_contract_ack_version FOREIGN KEY(contract_version_id,agency_id,contract_id) REFERENCES contract_versions(id,agency_id,contract_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_contract_ack_actor FOREIGN KEY(recorded_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_contract_ack_type CHECK(acknowledgement_type IN('customer','agency_representative')),
    CONSTRAINT chk_contract_ack_language CHECK(language_code IN('en','fr','ar')),
    CONSTRAINT chk_contract_ack_method CHECK(acknowledgement_method='in_person'),
    UNIQUE KEY uq_contract_ack_version_party(contract_version_id,acknowledgement_type),
    KEY idx_contract_ack_contract(agency_id,contract_id,acknowledged_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rental_operation_idempotency_keys (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    origin_agency_id BIGINT UNSIGNED NOT NULL,
    performing_agency_id BIGINT UNSIGNED NULL,
    operation_type VARCHAR(50) NOT NULL,
    key_hash CHAR(64) NOT NULL,
    payload_hash CHAR(64) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'in_progress',
    result_entity_type VARCHAR(40) NULL,
    result_entity_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    completed_at DATETIME(6) NULL,
    CONSTRAINT fk_rental_idem_origin_agency FOREIGN KEY(origin_agency_id) REFERENCES agencies(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_rental_idem_performing_agency FOREIGN KEY(performing_agency_id) REFERENCES agencies(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_rental_idem_actor FOREIGN KEY(created_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_rental_idem_status CHECK(status IN('in_progress','completed')),
    CONSTRAINT chk_rental_idem_completion CHECK((status='in_progress' AND completed_at IS NULL) OR (status='completed' AND completed_at IS NOT NULL AND result_entity_type IS NOT NULL AND result_entity_id IS NOT NULL)),
    UNIQUE KEY uq_rental_idempotency(origin_agency_id,operation_type,key_hash),
    KEY idx_rental_idem_actor(created_by,created_at,id),
    KEY idx_rental_idem_performing(performing_agency_id,operation_type,created_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Later Phase 5B slices use these columns, but no workflow is exposed in 5B.1.
ALTER TABLE vehicle_inspections
    ADD COLUMN IF NOT EXISTS agency_id BIGINT UNSIGNED NULL AFTER id,
    ADD COLUMN IF NOT EXISTS origin_agency_id BIGINT UNSIGNED NULL AFTER agency_id,
    ADD COLUMN IF NOT EXISTS performing_agency_id BIGINT UNSIGNED NULL AFTER origin_agency_id,
    ADD COLUMN IF NOT EXISTS reservation_id BIGINT UNSIGNED NULL AFTER performing_agency_id,
    ADD COLUMN IF NOT EXISTS post_return_vehicle_state VARCHAR(30) NULL AFTER status,
    ADD COLUMN IF NOT EXISTS damage_notes TEXT NULL AFTER post_return_vehicle_state,
    ADD COLUMN IF NOT EXISTS completed_at DATETIME(6) NULL AFTER validated_at,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) AFTER created_at,
    ADD COLUMN IF NOT EXISTS archived_at DATETIME(6) NULL AFTER updated_at,
    ADD COLUMN IF NOT EXISTS archived_by BIGINT UNSIGNED NULL AFTER archived_at,
    ADD COLUMN IF NOT EXISTS archive_reason VARCHAR(255) NULL AFTER archived_by,
    MODIFY inspected_at DATETIME(6) NOT NULL,
    MODIFY validated_at DATETIME(6) NULL,
    MODIFY created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6);

UPDATE vehicle_inspections vi
JOIN rental_contracts rc ON rc.id=vi.contract_id
SET vi.agency_id=rc.agency_id,
    vi.origin_agency_id=rc.agency_id,
    vi.performing_agency_id=rc.agency_id,
    vi.reservation_id=rc.reservation_id,
    vi.completed_at=CASE WHEN vi.status='validated' THEN COALESCE(vi.validated_at,vi.inspected_at) ELSE vi.completed_at END,
    vi.status=CASE WHEN vi.status='validated' THEN 'completed' ELSE vi.status END
WHERE vi.agency_id IS NULL OR vi.origin_agency_id IS NULL OR vi.performing_agency_id IS NULL
   OR vi.reservation_id IS NULL OR vi.status='validated';

ALTER TABLE vehicle_inspections
    MODIFY agency_id BIGINT UNSIGNED NOT NULL,
    MODIFY origin_agency_id BIGINT UNSIGNED NOT NULL,
    MODIFY performing_agency_id BIGINT UNSIGNED NOT NULL,
    MODIFY reservation_id BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY IF NOT EXISTS uq_inspections_id_agency (id,agency_id),
    ADD KEY IF NOT EXISTS idx_inspections_origin_status (origin_agency_id,status,inspection_type,inspected_at,id),
    ADD KEY IF NOT EXISTS idx_inspections_performing (performing_agency_id,inspection_type,inspected_at,id);

ALTER TABLE inspection_photos
    ADD COLUMN IF NOT EXISTS agency_id BIGINT UNSIGNED NULL AFTER id,
    ADD COLUMN IF NOT EXISTS photo_slot VARCHAR(30) NULL AFTER photo_type,
    ADD COLUMN IF NOT EXISTS original_name VARCHAR(255) NULL AFTER storage_path,
    ADD COLUMN IF NOT EXISTS sha256 CHAR(64) NULL AFTER file_size,
    ADD COLUMN IF NOT EXISTS protected_file TINYINT(1) NOT NULL DEFAULT 1 AFTER sha256,
    ADD COLUMN IF NOT EXISTS archived_at DATETIME(6) NULL AFTER created_at,
    ADD COLUMN IF NOT EXISTS archived_by BIGINT UNSIGNED NULL AFTER archived_at,
    ADD COLUMN IF NOT EXISTS archive_reason VARCHAR(255) NULL AFTER archived_by,
    ADD COLUMN IF NOT EXISTS active_photo_slot VARCHAR(190) GENERATED ALWAYS AS (
        CASE WHEN archived_at IS NULL THEN CONCAT(inspection_id,'|',photo_slot) ELSE NULL END
    ) PERSISTENT,
    MODIFY captured_at DATETIME(6) NOT NULL,
    MODIFY created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6);

UPDATE inspection_photos ip
JOIN vehicle_inspections vi ON vi.id=ip.inspection_id
SET ip.agency_id=vi.agency_id,
    ip.photo_slot=COALESCE(ip.photo_slot,ip.photo_type),
    ip.sha256=COALESCE(ip.sha256,SHA2(CONCAT(ip.storage_path,'|',ip.file_size,'|',ip.captured_at),256))
WHERE ip.agency_id IS NULL OR ip.photo_slot IS NULL OR ip.sha256 IS NULL;

ALTER TABLE inspection_photos
    MODIFY agency_id BIGINT UNSIGNED NOT NULL,
    MODIFY photo_slot VARCHAR(30) NOT NULL,
    MODIFY sha256 CHAR(64) NOT NULL,
    ADD UNIQUE KEY IF NOT EXISTS uq_inspection_photos_id_agency (id,agency_id),
    ADD UNIQUE KEY IF NOT EXISTS uq_inspection_active_photo_slot (active_photo_slot),
    ADD KEY IF NOT EXISTS idx_inspection_photos_evidence (agency_id,inspection_id,photo_slot,archived_at,id);

-- Add scoped/restrictive relationships only when absent. Existing same-named
-- constraints were checked after all DDL and are never dropped or rewritten.
SET @p5b_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5b_schema AND TABLE_NAME='rental_contracts' AND CONSTRAINT_NAME='fk_contracts_agency'),
    'DO 0','ALTER TABLE rental_contracts ADD CONSTRAINT fk_contracts_agency FOREIGN KEY(agency_id) REFERENCES agencies(id) ON UPDATE RESTRICT ON DELETE RESTRICT'); EXECUTE IMMEDIATE @p5b_sql;
SET @p5b_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5b_schema AND TABLE_NAME='rental_contracts' AND CONSTRAINT_NAME='fk_contracts_reservation_agency'),
    'DO 0','ALTER TABLE rental_contracts ADD CONSTRAINT fk_contracts_reservation_agency FOREIGN KEY(reservation_id,agency_id) REFERENCES reservations(id,agency_id) ON UPDATE RESTRICT ON DELETE RESTRICT'); EXECUTE IMMEDIATE @p5b_sql;
SET @p5b_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5b_schema AND TABLE_NAME='rental_contracts' AND CONSTRAINT_NAME='fk_contracts_canceller'),
    'DO 0','ALTER TABLE rental_contracts ADD CONSTRAINT fk_contracts_canceller FOREIGN KEY(cancelled_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT'); EXECUTE IMMEDIATE @p5b_sql;
SET @p5b_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5b_schema AND TABLE_NAME='contract_versions' AND CONSTRAINT_NAME='fk_contract_versions_contract_agency'),
    'DO 0','ALTER TABLE contract_versions ADD CONSTRAINT fk_contract_versions_contract_agency FOREIGN KEY(contract_id,agency_id) REFERENCES rental_contracts(id,agency_id) ON UPDATE RESTRICT ON DELETE RESTRICT'); EXECUTE IMMEDIATE @p5b_sql;
SET @p5b_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5b_schema AND TABLE_NAME='contract_versions' AND CONSTRAINT_NAME='fk_contract_versions_predecessor_agency'),
    'DO 0','ALTER TABLE contract_versions ADD CONSTRAINT fk_contract_versions_predecessor_agency FOREIGN KEY(predecessor_version_id,agency_id,contract_id) REFERENCES contract_versions(id,agency_id,contract_id) ON UPDATE RESTRICT ON DELETE RESTRICT'); EXECUTE IMMEDIATE @p5b_sql;
SET @p5b_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5b_schema AND TABLE_NAME='rental_contracts' AND CONSTRAINT_NAME='fk_contracts_current_version_agency'),
    'DO 0','ALTER TABLE rental_contracts ADD CONSTRAINT fk_contracts_current_version_agency FOREIGN KEY(current_version_id,agency_id,id) REFERENCES contract_versions(id,agency_id,contract_id) ON UPDATE RESTRICT ON DELETE RESTRICT'); EXECUTE IMMEDIATE @p5b_sql;
SET @p5b_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5b_schema AND TABLE_NAME='vehicle_inspections' AND CONSTRAINT_NAME='fk_inspections_contract_agency'),
    'DO 0','ALTER TABLE vehicle_inspections ADD CONSTRAINT fk_inspections_contract_agency FOREIGN KEY(contract_id,agency_id) REFERENCES rental_contracts(id,agency_id) ON UPDATE RESTRICT ON DELETE RESTRICT'); EXECUTE IMMEDIATE @p5b_sql;
SET @p5b_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5b_schema AND TABLE_NAME='vehicle_inspections' AND CONSTRAINT_NAME='fk_inspections_reservation_agency'),
    'DO 0','ALTER TABLE vehicle_inspections ADD CONSTRAINT fk_inspections_reservation_agency FOREIGN KEY(reservation_id,agency_id) REFERENCES reservations(id,agency_id) ON UPDATE RESTRICT ON DELETE RESTRICT'); EXECUTE IMMEDIATE @p5b_sql;
SET @p5b_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5b_schema AND TABLE_NAME='vehicle_inspections' AND CONSTRAINT_NAME='fk_inspections_origin_agency'),
    'DO 0','ALTER TABLE vehicle_inspections ADD CONSTRAINT fk_inspections_origin_agency FOREIGN KEY(origin_agency_id) REFERENCES agencies(id) ON UPDATE RESTRICT ON DELETE RESTRICT'); EXECUTE IMMEDIATE @p5b_sql;
SET @p5b_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5b_schema AND TABLE_NAME='vehicle_inspections' AND CONSTRAINT_NAME='fk_inspections_performing_agency'),
    'DO 0','ALTER TABLE vehicle_inspections ADD CONSTRAINT fk_inspections_performing_agency FOREIGN KEY(performing_agency_id) REFERENCES agencies(id) ON UPDATE RESTRICT ON DELETE RESTRICT'); EXECUTE IMMEDIATE @p5b_sql;
SET @p5b_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5b_schema AND TABLE_NAME='vehicle_inspections' AND CONSTRAINT_NAME='fk_inspections_vehicle_agency'),
    'DO 0','ALTER TABLE vehicle_inspections ADD CONSTRAINT fk_inspections_vehicle_agency FOREIGN KEY(vehicle_id,agency_id) REFERENCES vehicles(id,agency_id) ON UPDATE RESTRICT ON DELETE RESTRICT'); EXECUTE IMMEDIATE @p5b_sql;
SET @p5b_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5b_schema AND TABLE_NAME='vehicle_inspections' AND CONSTRAINT_NAME='fk_inspections_customer_agency'),
    'DO 0','ALTER TABLE vehicle_inspections ADD CONSTRAINT fk_inspections_customer_agency FOREIGN KEY(customer_id,agency_id) REFERENCES customers(id,agency_id) ON UPDATE RESTRICT ON DELETE RESTRICT'); EXECUTE IMMEDIATE @p5b_sql;
SET @p5b_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5b_schema AND TABLE_NAME='inspection_photos' AND CONSTRAINT_NAME='fk_inspection_photos_inspection_agency'),
    'DO 0','ALTER TABLE inspection_photos ADD CONSTRAINT fk_inspection_photos_inspection_agency FOREIGN KEY(inspection_id,agency_id) REFERENCES vehicle_inspections(id,agency_id) ON UPDATE RESTRICT ON DELETE RESTRICT'); EXECUTE IMMEDIATE @p5b_sql;

-- Canonical lifecycle and evidence constraints.
SET @p5b_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5b_schema AND TABLE_NAME='rental_contracts' AND CONSTRAINT_NAME='chk_contract_status'),
    'DO 0','ALTER TABLE rental_contracts ADD CONSTRAINT chk_contract_status CHECK(status IN(''draft'',''issued'',''signed'',''active'',''completed'',''cancelled''))'); EXECUTE IMMEDIATE @p5b_sql;
SET @p5b_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5b_schema AND TABLE_NAME='rental_contracts' AND CONSTRAINT_NAME='chk_contract_cancellation'),
    'DO 0','ALTER TABLE rental_contracts ADD CONSTRAINT chk_contract_cancellation CHECK((status=''cancelled'' AND cancelled_at IS NOT NULL AND cancelled_by IS NOT NULL AND CHAR_LENGTH(TRIM(cancellation_reason)) BETWEEN 1 AND 255) OR (status<>''cancelled'' AND cancelled_at IS NULL AND cancelled_by IS NULL AND cancellation_reason IS NULL))'); EXECUTE IMMEDIATE @p5b_sql;
SET @p5b_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5b_schema AND TABLE_NAME='rental_contracts' AND CONSTRAINT_NAME='chk_contract_lifecycle_moments'),
    'DO 0','ALTER TABLE rental_contracts ADD CONSTRAINT chk_contract_lifecycle_moments CHECK((status=''draft'' AND issued_at IS NULL AND signed_at IS NULL AND activated_at IS NULL AND completed_at IS NULL) OR (status=''issued'' AND issued_at IS NOT NULL AND signed_at IS NULL AND activated_at IS NULL AND completed_at IS NULL) OR (status=''signed'' AND issued_at IS NOT NULL AND signed_at IS NOT NULL AND activated_at IS NULL AND completed_at IS NULL) OR (status=''active'' AND issued_at IS NOT NULL AND signed_at IS NOT NULL AND activated_at IS NOT NULL AND completed_at IS NULL) OR (status=''completed'' AND issued_at IS NOT NULL AND signed_at IS NOT NULL AND activated_at IS NOT NULL AND completed_at IS NOT NULL) OR status=''cancelled'')'); EXECUTE IMMEDIATE @p5b_sql;
SET @p5b_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5b_schema AND TABLE_NAME='contract_versions' AND CONSTRAINT_NAME='chk_contract_version_language'),
    'DO 0','ALTER TABLE contract_versions ADD CONSTRAINT chk_contract_version_language CHECK(language_code IN(''en'',''fr'',''ar''))'); EXECUTE IMMEDIATE @p5b_sql;
SET @p5b_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5b_schema AND TABLE_NAME='contract_versions' AND CONSTRAINT_NAME='chk_contract_version_digest'),
    'DO 0','ALTER TABLE contract_versions ADD CONSTRAINT chk_contract_version_digest CHECK(snapshot_sha256 REGEXP ''^[0-9a-f]{64}$'')'); EXECUTE IMMEDIATE @p5b_sql;
SET @p5b_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5b_schema AND TABLE_NAME='vehicle_inspections' AND CONSTRAINT_NAME='chk_inspection_type'),
    'DO 0','ALTER TABLE vehicle_inspections ADD CONSTRAINT chk_inspection_type CHECK(inspection_type IN(''checkout'',''return''))'); EXECUTE IMMEDIATE @p5b_sql;
SET @p5b_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5b_schema AND TABLE_NAME='vehicle_inspections' AND CONSTRAINT_NAME='chk_inspection_status'),
    'DO 0','ALTER TABLE vehicle_inspections ADD CONSTRAINT chk_inspection_status CHECK(status IN(''draft'',''completed'',''archived''))'); EXECUTE IMMEDIATE @p5b_sql;
SET @p5b_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5b_schema AND TABLE_NAME='vehicle_inspections' AND CONSTRAINT_NAME='chk_inspection_fuel'),
    'DO 0','ALTER TABLE vehicle_inspections ADD CONSTRAINT chk_inspection_fuel CHECK(fuel_level BETWEEN 0 AND 100)'); EXECUTE IMMEDIATE @p5b_sql;
SET @p5b_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5b_schema AND TABLE_NAME='vehicle_inspections' AND CONSTRAINT_NAME='chk_inspection_post_return'),
    'DO 0','ALTER TABLE vehicle_inspections ADD CONSTRAINT chk_inspection_post_return CHECK((inspection_type=''checkout'' AND post_return_vehicle_state IS NULL) OR (inspection_type=''return'' AND status=''draft'' AND post_return_vehicle_state IS NULL) OR (inspection_type=''return'' AND status IN(''completed'',''archived'') AND post_return_vehicle_state IN(''cleaning'',''available'',''maintenance'',''damaged'') AND (post_return_vehicle_state<>''damaged'' OR CHAR_LENGTH(TRIM(COALESCE(damage_notes,'''')))>0)))'); EXECUTE IMMEDIATE @p5b_sql;
SET @p5b_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5b_schema AND TABLE_NAME='inspection_photos' AND CONSTRAINT_NAME='chk_inspection_photo_slot'),
    'DO 0','ALTER TABLE inspection_photos ADD CONSTRAINT chk_inspection_photo_slot CHECK(photo_slot IN(''front'',''rear'',''left'',''right'',''interior'',''dashboard''))'); EXECUTE IMMEDIATE @p5b_sql;

CREATE TEMPORARY TABLE _p5b_expected_fks (
    table_name VARCHAR(64) NOT NULL,constraint_name VARCHAR(64) NOT NULL,
    local_columns VARCHAR(500) NOT NULL,referenced_table VARCHAR(64) NOT NULL,referenced_columns VARCHAR(500) NOT NULL,
    PRIMARY KEY(table_name,constraint_name)
) ENGINE=InnoDB;
INSERT INTO _p5b_expected_fks VALUES
 ('rental_contracts','fk_contracts_agency','agency_id','agencies','id'),
 ('rental_contracts','fk_contracts_reservation_agency','reservation_id,agency_id','reservations','id,agency_id'),
 ('rental_contracts','fk_contracts_canceller','cancelled_by','users','id'),
 ('rental_contracts','fk_contracts_current_version_agency','current_version_id,agency_id,id','contract_versions','id,agency_id,contract_id'),
 ('contract_versions','fk_contract_versions_contract_agency','contract_id,agency_id','rental_contracts','id,agency_id'),
 ('contract_versions','fk_contract_versions_predecessor_agency','predecessor_version_id,agency_id,contract_id','contract_versions','id,agency_id,contract_id'),
 ('contract_status_history','fk_contract_history_agency','agency_id','agencies','id'),
 ('contract_status_history','fk_contract_history_contract','contract_id,agency_id','rental_contracts','id,agency_id'),
 ('contract_status_history','fk_contract_history_reservation','reservation_id,agency_id','reservations','id,agency_id'),
 ('contract_status_history','fk_contract_history_actor','changed_by','users','id'),
 ('contract_acknowledgements','fk_contract_ack_agency','agency_id','agencies','id'),
 ('contract_acknowledgements','fk_contract_ack_contract','contract_id,agency_id','rental_contracts','id,agency_id'),
 ('contract_acknowledgements','fk_contract_ack_version','contract_version_id,agency_id,contract_id','contract_versions','id,agency_id,contract_id'),
 ('contract_acknowledgements','fk_contract_ack_actor','recorded_by','users','id'),
 ('rental_operation_idempotency_keys','fk_rental_idem_origin_agency','origin_agency_id','agencies','id'),
 ('rental_operation_idempotency_keys','fk_rental_idem_performing_agency','performing_agency_id','agencies','id'),
 ('rental_operation_idempotency_keys','fk_rental_idem_actor','created_by','users','id'),
 ('vehicle_inspections','fk_inspections_contract_agency','contract_id,agency_id','rental_contracts','id,agency_id'),
 ('vehicle_inspections','fk_inspections_reservation_agency','reservation_id,agency_id','reservations','id,agency_id'),
 ('vehicle_inspections','fk_inspections_origin_agency','origin_agency_id','agencies','id'),
 ('vehicle_inspections','fk_inspections_performing_agency','performing_agency_id','agencies','id'),
 ('vehicle_inspections','fk_inspections_vehicle_agency','vehicle_id,agency_id','vehicles','id,agency_id'),
 ('vehicle_inspections','fk_inspections_customer_agency','customer_id,agency_id','customers','id,agency_id'),
 ('inspection_photos','fk_inspection_photos_inspection_agency','inspection_id,agency_id','vehicle_inspections','id,agency_id');

SET @p5b_bad = (
 SELECT COUNT(*) FROM _p5b_expected_fks e
 LEFT JOIN (
   SELECT k.TABLE_NAME,k.CONSTRAINT_NAME,MAX(tc.CONSTRAINT_TYPE) constraint_type,
          GROUP_CONCAT(k.COLUMN_NAME ORDER BY k.ORDINAL_POSITION SEPARATOR ',') local_columns,
          MAX(k.REFERENCED_TABLE_NAME) referenced_table,
          GROUP_CONCAT(k.REFERENCED_COLUMN_NAME ORDER BY k.ORDINAL_POSITION SEPARATOR ',') referenced_columns,
          MAX(r.UPDATE_RULE) update_rule,MAX(r.DELETE_RULE) delete_rule
   FROM information_schema.KEY_COLUMN_USAGE k
   JOIN information_schema.TABLE_CONSTRAINTS tc ON tc.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND tc.TABLE_NAME=k.TABLE_NAME AND tc.CONSTRAINT_NAME=k.CONSTRAINT_NAME
   JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME
   WHERE k.CONSTRAINT_SCHEMA=@p5b_schema AND k.REFERENCED_TABLE_NAME IS NOT NULL
   GROUP BY k.TABLE_NAME,k.CONSTRAINT_NAME
 ) a ON a.TABLE_NAME=e.table_name AND a.CONSTRAINT_NAME=e.constraint_name
 WHERE a.CONSTRAINT_NAME IS NULL OR a.constraint_type<>'FOREIGN KEY'
    OR a.local_columns<>e.local_columns OR a.referenced_table<>e.referenced_table OR a.referenced_columns<>e.referenced_columns
    OR UPPER(a.update_rule) NOT IN('RESTRICT','NO ACTION') OR UPPER(a.delete_rule) NOT IN('RESTRICT','NO ACTION')
);
SET @p5b_sql=IF(@p5b_bad=0,'DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B schema mismatch: incompatible foreign-key definition''');
EXECUTE IMMEDIATE @p5b_sql;

CREATE TEMPORARY TABLE _p5b_expected_checks (
    table_name VARCHAR(64) NOT NULL,constraint_name VARCHAR(64) NOT NULL,approved_clause TEXT NOT NULL,
    PRIMARY KEY(table_name,constraint_name)
) ENGINE=InnoDB;
INSERT INTO _p5b_expected_checks VALUES
 ('rental_contracts','chk_contract_status','status IN(''draft'',''issued'',''signed'',''active'',''completed'',''cancelled'')'),
 ('rental_contracts','chk_contract_cancellation','(status=''cancelled'' AND cancelled_at IS NOT NULL AND cancelled_by IS NOT NULL AND CHAR_LENGTH(TRIM(cancellation_reason)) BETWEEN 1 AND 255) OR (status<>''cancelled'' AND cancelled_at IS NULL AND cancelled_by IS NULL AND cancellation_reason IS NULL)'),
 ('rental_contracts','chk_contract_lifecycle_moments','(status=''draft'' AND issued_at IS NULL AND signed_at IS NULL AND activated_at IS NULL AND completed_at IS NULL) OR (status=''issued'' AND issued_at IS NOT NULL AND signed_at IS NULL AND activated_at IS NULL AND completed_at IS NULL) OR (status=''signed'' AND issued_at IS NOT NULL AND signed_at IS NOT NULL AND activated_at IS NULL AND completed_at IS NULL) OR (status=''active'' AND issued_at IS NOT NULL AND signed_at IS NOT NULL AND activated_at IS NOT NULL AND completed_at IS NULL) OR (status=''completed'' AND issued_at IS NOT NULL AND signed_at IS NOT NULL AND activated_at IS NOT NULL AND completed_at IS NOT NULL) OR status=''cancelled'''),
 ('contract_versions','chk_contract_version_language','language_code IN(''en'',''fr'',''ar'')'),
 ('contract_versions','chk_contract_version_digest','snapshot_sha256 REGEXP ''^[0-9a-f]{64}$'''),
 ('contract_status_history','chk_contract_history_from','from_status IS NULL OR from_status IN(''draft'',''issued'',''signed'',''active'',''completed'',''cancelled'')'),
 ('contract_status_history','chk_contract_history_to','to_status IN(''draft'',''issued'',''signed'',''active'',''completed'',''cancelled'')'),
 ('contract_acknowledgements','chk_contract_ack_type','acknowledgement_type IN(''customer'',''agency_representative'')'),
 ('contract_acknowledgements','chk_contract_ack_language','language_code IN(''en'',''fr'',''ar'')'),
 ('contract_acknowledgements','chk_contract_ack_method','acknowledgement_method=''in_person'''),
 ('rental_operation_idempotency_keys','chk_rental_idem_status','status IN(''in_progress'',''completed'')'),
 ('rental_operation_idempotency_keys','chk_rental_idem_completion','(status=''in_progress'' AND completed_at IS NULL) OR (status=''completed'' AND completed_at IS NOT NULL AND result_entity_type IS NOT NULL AND result_entity_id IS NOT NULL)'),
 ('vehicle_inspections','chk_inspection_type','inspection_type IN(''checkout'',''return'')'),
 ('vehicle_inspections','chk_inspection_status','status IN(''draft'',''completed'',''archived'')'),
 ('vehicle_inspections','chk_inspection_fuel','fuel_level BETWEEN 0 AND 100'),
 ('vehicle_inspections','chk_inspection_post_return','(inspection_type=''checkout'' AND post_return_vehicle_state IS NULL) OR (inspection_type=''return'' AND status=''draft'' AND post_return_vehicle_state IS NULL) OR (inspection_type=''return'' AND status IN(''completed'',''archived'') AND post_return_vehicle_state IN(''cleaning'',''available'',''maintenance'',''damaged'') AND (post_return_vehicle_state<>''damaged'' OR CHAR_LENGTH(TRIM(COALESCE(damage_notes,'''')))>0))'),
 ('inspection_photos','chk_inspection_photo_slot','photo_slot IN(''front'',''rear'',''left'',''right'',''interior'',''dashboard'')');

SET @p5b_bad = (
 SELECT COUNT(*) FROM _p5b_expected_checks e
 LEFT JOIN information_schema.TABLE_CONSTRAINTS tc ON tc.CONSTRAINT_SCHEMA=@p5b_schema AND tc.TABLE_NAME=e.table_name AND tc.CONSTRAINT_NAME=e.constraint_name AND tc.CONSTRAINT_TYPE='CHECK'
 LEFT JOIN information_schema.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME
 WHERE cc.CONSTRAINT_NAME IS NULL OR LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(cc.CHECK_CLAUSE),'`',''),' ',''),CHAR(9),''),CHAR(10),''),CHAR(13),''),'(',''),')',''))
       NOT IN(
          LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(e.approved_clause),'`',''),' ',''),CHAR(9),''),CHAR(10),''),CHAR(13),''),'(',''),')','')),
          LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(CONCAT('(',e.approved_clause,')')),'`',''),' ',''),CHAR(9),''),CHAR(10),''),CHAR(13),''),'(',''),')',''))
       )
);
SET @p5b_sql=IF(@p5b_bad=0,'DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B schema mismatch: incompatible CHECK expression''');
EXECUTE IMMEDIATE @p5b_sql;

-- Exact post-DDL checks: incompatible same-named columns, generated columns,
-- tables, indexes, FKs and CHECKs fail before the runner records migration 007.
SET @p5b_bad = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=@p5b_schema AND (
        (TABLE_NAME='rental_contracts' AND COLUMN_NAME='agency_id' AND (COLUMN_TYPE<>'bigint(20) unsigned' OR IS_NULLABLE<>'NO')) OR
        (TABLE_NAME='rental_contracts' AND COLUMN_NAME IN('issued_at','signed_at','activated_at','completed_at','cancelled_at','created_at','updated_at') AND (DATA_TYPE<>'datetime' OR DATETIME_PRECISION<>6)) OR
        (TABLE_NAME='rental_contracts' AND COLUMN_NAME IN('current_version_id','cancelled_by') AND COLUMN_TYPE<>'bigint(20) unsigned') OR
        (TABLE_NAME='contract_versions' AND COLUMN_NAME='agency_id' AND (COLUMN_TYPE<>'bigint(20) unsigned' OR IS_NULLABLE<>'NO')) OR
        (TABLE_NAME='contract_versions' AND COLUMN_NAME='predecessor_version_id' AND COLUMN_TYPE<>'bigint(20) unsigned') OR
        (TABLE_NAME='contract_versions' AND COLUMN_NAME='snapshot_sha256' AND (COLUMN_TYPE<>'char(64)' OR IS_NULLABLE<>'NO')) OR
        (TABLE_NAME='contract_status_history' AND COLUMN_NAME='occurred_at' AND (DATA_TYPE<>'datetime' OR DATETIME_PRECISION<>6 OR IS_NULLABLE<>'NO')) OR
        (TABLE_NAME='contract_acknowledgements' AND COLUMN_NAME IN('acknowledged_at','created_at') AND (DATA_TYPE<>'datetime' OR DATETIME_PRECISION<>6 OR IS_NULLABLE<>'NO')) OR
        (TABLE_NAME='rental_operation_idempotency_keys' AND COLUMN_NAME IN('key_hash','payload_hash') AND COLUMN_TYPE<>'char(64)') OR
        (TABLE_NAME='rental_operation_idempotency_keys' AND COLUMN_NAME IN('created_at','completed_at') AND (DATA_TYPE<>'datetime' OR DATETIME_PRECISION<>6)) OR
        (TABLE_NAME='vehicle_inspections' AND COLUMN_NAME IN('inspected_at','completed_at','created_at','updated_at','archived_at') AND (DATA_TYPE<>'datetime' OR DATETIME_PRECISION<>6)) OR
        (TABLE_NAME='inspection_photos' AND COLUMN_NAME IN('captured_at','created_at','archived_at') AND (DATA_TYPE<>'datetime' OR DATETIME_PRECISION<>6)) OR
        (TABLE_NAME='inspection_photos' AND COLUMN_NAME='sha256' AND (COLUMN_TYPE<>'char(64)' OR IS_NULLABLE<>'NO'))
    )
);
SET @p5b_sql=IF(@p5b_bad=0,'DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B schema mismatch: incompatible column definition''');
EXECUTE IMMEDIATE @p5b_sql;

SET @p5b_bad = (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA=@p5b_schema
      AND TABLE_NAME IN('rental_contracts','contract_versions','contract_status_history','contract_acknowledgements','rental_operation_idempotency_keys','vehicle_inspections','inspection_photos')
      AND (UPPER(COALESCE(ENGINE,''))<>'INNODB' OR LOWER(COALESCE(TABLE_COLLATION,''))<>'utf8mb4_unicode_ci')
);
SET @p5b_sql=IF(@p5b_bad=0,'DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B schema mismatch: incompatible engine or collation''');
EXECUTE IMMEDIATE @p5b_sql;

SET @p5b_bad = (
    SELECT COUNT(*) FROM (
        SELECT 'rental_contracts' table_name,'uq_contracts_live_reservation' index_name,'live_reservation_id' cols,0 non_unique
        UNION ALL SELECT 'contract_status_history','uq_contract_history_baseline','baseline_key',0
        UNION ALL SELECT 'contract_acknowledgements','uq_contract_ack_version_party','contract_version_id,acknowledgement_type',0
        UNION ALL SELECT 'rental_operation_idempotency_keys','uq_rental_idempotency','origin_agency_id,operation_type,key_hash',0
        UNION ALL SELECT 'inspection_photos','uq_inspection_active_photo_slot','active_photo_slot',0
    ) e
    LEFT JOIN (
        SELECT TABLE_NAME,INDEX_NAME,MAX(NON_UNIQUE) non_unique,
               GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') cols
        FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@p5b_schema
        GROUP BY TABLE_NAME,INDEX_NAME
    ) a ON a.TABLE_NAME=e.table_name AND a.INDEX_NAME=e.index_name
    WHERE a.INDEX_NAME IS NULL OR a.non_unique<>e.non_unique OR a.cols<>e.cols
);
SET @p5b_sql=IF(@p5b_bad=0,'DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B schema mismatch: incompatible index definition''');
EXECUTE IMMEDIATE @p5b_sql;

SET @p5b_bad = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=@p5b_schema AND (
        (TABLE_NAME='rental_contracts' AND COLUMN_NAME='live_reservation_id' AND
          LOWER(REPLACE(REPLACE(REPLACE(COALESCE(GENERATION_EXPRESSION,''),'`',''),' ',''),CHAR(10),''))
          <>LOWER(REPLACE(REPLACE(REPLACE('case when status in (''draft'',''issued'',''signed'',''active'') then reservation_id else NULL end','`',''),' ',''),CHAR(10),'')))
        OR
        (TABLE_NAME='inspection_photos' AND COLUMN_NAME='active_photo_slot' AND EXTRA NOT LIKE '%STORED GENERATED%')
    )
);
SET @p5b_sql=IF(@p5b_bad=0,'DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B schema mismatch: incompatible generated expression''');
EXECUTE IMMEDIATE @p5b_sql;

SET @p5b_sql = (SELECT CASE WHEN EXISTS(
    SELECT 1 FROM rental_contracts
    WHERE status IN('draft','issued','signed','active')
    GROUP BY reservation_id HAVING COUNT(*)>1
) THEN 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5B postflight: duplicate live contract''' ELSE 'DO 0' END);
EXECUTE IMMEDIATE @p5b_sql;

DROP TEMPORARY TABLE _p5b_legacy_contract_state;
DROP TEMPORARY TABLE _p5b_expected_fks;
DROP TEMPORARY TABLE _p5b_expected_checks;
DROP TEMPORARY TABLE _p5b_expected_columns;
DROP TEMPORARY TABLE _p5b_expected_existing_indexes;
DROP TEMPORARY TABLE _p5b_expected_existing_fks;
DROP TEMPORARY TABLE _p5b_expected_existing_checks;
