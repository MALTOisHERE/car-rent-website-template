-- Phase 4: customer/reservation workspaces, lifecycle history, tax-rate concurrency support.
-- MariaDB DDL is not assumed to be transactional. Every operation inspects the live
-- definition, skips an exact match, adds a missing element, and fails on conflicts.

SET @p4_schema = DATABASE();

-- Parent timestamp precision required by optimistic concurrency.
SET @p4_sql = (
    SELECT CASE
        WHEN COUNT(*) = 0 THEN 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 4 schema mismatch: customers.updated_at missing'''
        WHEN MAX(COLUMN_TYPE = 'datetime(6)' AND IS_NULLABLE = 'NO') = 1 THEN 'DO 0'
        ELSE 'ALTER TABLE customers MODIFY updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)'
    END
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='customers' AND COLUMN_NAME='updated_at'
);
EXECUTE IMMEDIATE @p4_sql;

SET @p4_sql = (
    SELECT CASE
        WHEN COUNT(*) = 0 THEN 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 4 schema mismatch: reservations.updated_at missing'''
        WHEN MAX(COLUMN_TYPE = 'datetime(6)' AND IS_NULLABLE = 'NO') = 1 THEN 'DO 0'
        ELSE 'ALTER TABLE reservations MODIFY updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)'
    END
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='reservations' AND COLUMN_NAME='updated_at'
);
EXECUTE IMMEDIATE @p4_sql;

-- Explicit tax rate. NULL is retained only for legacy rows whose rate cannot be proven.
SET @p4_sql = (
    SELECT CASE
        WHEN COUNT(*) = 0 THEN 'ALTER TABLE reservations ADD COLUMN tax_rate DECIMAL(5,2) NULL AFTER discount_reason'
        WHEN MAX(COLUMN_TYPE = 'decimal(5,2)' AND IS_NULLABLE = 'YES') = 1 THEN 'DO 0'
        ELSE 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 4 schema mismatch: reservations.tax_rate'''
    END
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='reservations' AND COLUMN_NAME='tax_rate'
);
EXECUTE IMMEDIATE @p4_sql;

-- Composite customer/agency key used by the history foreign key.
SET @p4_index_columns = (
    SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='customers' AND INDEX_NAME='uq_customers_id_agency'
);
SET @p4_index_non_unique = (
    SELECT MIN(NON_UNIQUE) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='customers' AND INDEX_NAME='uq_customers_id_agency'
);
SET @p4_sql = CASE
    WHEN @p4_index_columns IS NULL THEN 'ALTER TABLE customers ADD UNIQUE KEY uq_customers_id_agency (id,agency_id)'
    WHEN @p4_index_columns='id,agency_id' AND @p4_index_non_unique=0 THEN 'DO 0'
    ELSE 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 4 schema mismatch: uq_customers_id_agency'''
END;
EXECUTE IMMEDIATE @p4_sql;

CREATE TABLE IF NOT EXISTS customer_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    agency_id BIGINT UNSIGNED NOT NULL,
    previous_status VARCHAR(30) NULL,
    new_status VARCHAR(30) NOT NULL,
    action_type VARCHAR(30) NOT NULL,
    reason VARCHAR(255) NULL,
    changed_by BIGINT UNSIGNED NULL,
    changed_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    baseline_customer_id BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE WHEN action_type='migration_baseline' THEN customer_id ELSE NULL END
    ) PERSISTENT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recover the identifier first. AUTO_INCREMENT and its required primary key are
-- created in the same ALTER when both are absent. Existing incompatible identifiers
-- or primary keys fail before any other partial-table repair is attempted.
SET @p4_id_count=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='customer_status_history' AND COLUMN_NAME='id');
SET @p4_id_type=(SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='customer_status_history' AND COLUMN_NAME='id');
SET @p4_id_nullable=(SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='customer_status_history' AND COLUMN_NAME='id');
SET @p4_id_extra=(SELECT EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='customer_status_history' AND COLUMN_NAME='id');
SET @p4_primary_columns=(SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='customer_status_history' AND INDEX_NAME='PRIMARY');
SET @p4_sql=CASE
    WHEN @p4_id_count=0 AND @p4_primary_columns IS NULL THEN 'ALTER TABLE customer_status_history ADD COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (id)'
    WHEN @p4_id_count=0 THEN 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 4 schema mismatch: history primary key exists without id'''
    WHEN @p4_id_type<>'bigint(20) unsigned' OR @p4_id_nullable<>'NO' THEN 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 4 schema mismatch: history id definition'''
    WHEN @p4_primary_columns IS NOT NULL AND @p4_primary_columns<>'id' THEN 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 4 schema mismatch: history primary key'''
    WHEN @p4_primary_columns IS NULL AND COALESCE(@p4_id_extra,'') NOT LIKE '%auto_increment%' THEN 'ALTER TABLE customer_status_history MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (id)'
    WHEN @p4_primary_columns IS NULL THEN 'ALTER TABLE customer_status_history ADD PRIMARY KEY (id)'
    WHEN COALESCE(@p4_id_extra,'') NOT LIKE '%auto_increment%' THEN 'ALTER TABLE customer_status_history MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST'
    ELSE 'DO 0'
END;
EXECUTE IMMEDIATE @p4_sql;

-- Recover a table left with only a partial set of non-identifier columns.
SET @p4_sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE customer_status_history ADD COLUMN customer_id BIGINT UNSIGNED NOT NULL AFTER id','DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='customer_status_history' AND COLUMN_NAME='customer_id'); EXECUTE IMMEDIATE @p4_sql;
SET @p4_sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE customer_status_history ADD COLUMN agency_id BIGINT UNSIGNED NOT NULL AFTER customer_id','DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='customer_status_history' AND COLUMN_NAME='agency_id'); EXECUTE IMMEDIATE @p4_sql;
SET @p4_sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE customer_status_history ADD COLUMN previous_status VARCHAR(30) NULL AFTER agency_id','DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='customer_status_history' AND COLUMN_NAME='previous_status'); EXECUTE IMMEDIATE @p4_sql;
SET @p4_sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE customer_status_history ADD COLUMN new_status VARCHAR(30) NOT NULL AFTER previous_status','DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='customer_status_history' AND COLUMN_NAME='new_status'); EXECUTE IMMEDIATE @p4_sql;
SET @p4_sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE customer_status_history ADD COLUMN action_type VARCHAR(30) NOT NULL AFTER new_status','DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='customer_status_history' AND COLUMN_NAME='action_type'); EXECUTE IMMEDIATE @p4_sql;
SET @p4_sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE customer_status_history ADD COLUMN reason VARCHAR(255) NULL AFTER action_type','DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='customer_status_history' AND COLUMN_NAME='reason'); EXECUTE IMMEDIATE @p4_sql;
SET @p4_sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE customer_status_history ADD COLUMN changed_by BIGINT UNSIGNED NULL AFTER reason','DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='customer_status_history' AND COLUMN_NAME='changed_by'); EXECUTE IMMEDIATE @p4_sql;
SET @p4_sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE customer_status_history ADD COLUMN changed_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) AFTER changed_by','DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='customer_status_history' AND COLUMN_NAME='changed_at'); EXECUTE IMMEDIATE @p4_sql;
SET @p4_sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE customer_status_history ADD COLUMN baseline_customer_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN action_type=''migration_baseline'' THEN customer_id ELSE NULL END) PERSISTENT AFTER changed_at','DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='customer_status_history' AND COLUMN_NAME='baseline_customer_id'); EXECUTE IMMEDIATE @p4_sql;

-- Validate all column definitions, including the generated baseline expression.
SET @p4_bad_columns = (
    SELECT COUNT(*) FROM (
        SELECT 'id' n,'bigint(20) unsigned' t,'NO' z UNION ALL
        SELECT 'customer_id','bigint(20) unsigned','NO' UNION ALL
        SELECT 'agency_id','bigint(20) unsigned','NO' UNION ALL
        SELECT 'previous_status','varchar(30)','YES' UNION ALL
        SELECT 'new_status','varchar(30)','NO' UNION ALL
        SELECT 'action_type','varchar(30)','NO' UNION ALL
        SELECT 'reason','varchar(255)','YES' UNION ALL
        SELECT 'changed_by','bigint(20) unsigned','YES' UNION ALL
        SELECT 'changed_at','datetime(6)','NO' UNION ALL
        SELECT 'baseline_customer_id','bigint(20) unsigned','YES'
    ) expected
    LEFT JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=@p4_schema AND c.TABLE_NAME='customer_status_history' AND c.COLUMN_NAME=expected.n
    WHERE c.COLUMN_NAME IS NULL OR c.COLUMN_TYPE<>expected.t OR c.IS_NULLABLE<>expected.z
);
SET @p4_sql=IF(@p4_bad_columns=0,'DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 4 schema mismatch: customer_status_history columns'''); EXECUTE IMMEDIATE @p4_sql;
SET @p4_generation=(SELECT LOWER(REPLACE(REPLACE(GENERATION_EXPRESSION,'`',''),' ','')) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='customer_status_history' AND COLUMN_NAME='baseline_customer_id');
SET @p4_sql=IF(@p4_generation LIKE '%action_type%migration_baseline%customer_id%','DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 4 schema mismatch: baseline generated expression'''); EXECUTE IMMEDIATE @p4_sql;
SET @p4_id_extra=(SELECT EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='customer_status_history' AND COLUMN_NAME='id');
SET @p4_sql=IF(COALESCE(@p4_id_extra,'') LIKE '%auto_increment%','DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 4 schema mismatch: history id auto increment'''); EXECUTE IMMEDIATE @p4_sql;

-- Primary key and required indexes.
SET @p4_index_columns=(SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='customer_status_history' AND INDEX_NAME='PRIMARY');
SET @p4_sql=CASE WHEN @p4_index_columns='id' THEN 'DO 0' ELSE 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 4 schema mismatch: history primary key''' END; EXECUTE IMMEDIATE @p4_sql;

SET @p4_index_columns=(SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='customer_status_history' AND INDEX_NAME='uq_customer_status_history_baseline');
SET @p4_index_non_unique=(SELECT MIN(NON_UNIQUE) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='customer_status_history' AND INDEX_NAME='uq_customer_status_history_baseline');
SET @p4_sql=CASE WHEN @p4_index_columns IS NULL THEN 'ALTER TABLE customer_status_history ADD UNIQUE KEY uq_customer_status_history_baseline (baseline_customer_id)' WHEN @p4_index_columns='baseline_customer_id' AND @p4_index_non_unique=0 THEN 'DO 0' ELSE 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 4 schema mismatch: history baseline index''' END; EXECUTE IMMEDIATE @p4_sql;

SET @p4_index_columns=(SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='customer_status_history' AND INDEX_NAME='idx_customer_status_history_timeline');
SET @p4_sql=CASE WHEN @p4_index_columns IS NULL THEN 'ALTER TABLE customer_status_history ADD KEY idx_customer_status_history_timeline (agency_id,customer_id,changed_at,id)' WHEN @p4_index_columns='agency_id,customer_id,changed_at,id' THEN 'DO 0' ELSE 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 4 schema mismatch: history timeline index''' END; EXECUTE IMMEDIATE @p4_sql;

SET @p4_index_columns=(SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='customer_status_history' AND INDEX_NAME='idx_customer_status_history_status');
SET @p4_sql=CASE WHEN @p4_index_columns IS NULL THEN 'ALTER TABLE customer_status_history ADD KEY idx_customer_status_history_status (agency_id,new_status,changed_at)' WHEN @p4_index_columns='agency_id,new_status,changed_at' THEN 'DO 0' ELSE 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 4 schema mismatch: history status index''' END; EXECUTE IMMEDIATE @p4_sql;

-- Foreign keys are validated by full metadata. MariaDB may report NO ACTION as the
-- non-cascading equivalent of RESTRICT, so only those two rules normalize to RESTRICT.
SET @p4_fk=(SELECT IF(COUNT(*)=0,NULL,CONCAT(COALESCE(MAX(tc.CONSTRAINT_TYPE),''),'|',COALESCE(MAX(tc.TABLE_NAME),''),'|',COALESCE(GROUP_CONCAT(k.COLUMN_NAME ORDER BY k.ORDINAL_POSITION SEPARATOR ','),''),'|',COALESCE(MAX(k.REFERENCED_TABLE_SCHEMA),''),'|',COALESCE(MAX(k.REFERENCED_TABLE_NAME),''),'|',COALESCE(GROUP_CONCAT(k.REFERENCED_COLUMN_NAME ORDER BY k.ORDINAL_POSITION SEPARATOR ','),''),'|',COALESCE(MAX(CASE WHEN rc.UPDATE_RULE IN ('RESTRICT','NO ACTION') THEN 'RESTRICT' ELSE rc.UPDATE_RULE END),''),'|',COALESCE(MAX(CASE WHEN rc.DELETE_RULE IN ('RESTRICT','NO ACTION') THEN 'RESTRICT' ELSE rc.DELETE_RULE END),''))) FROM information_schema.TABLE_CONSTRAINTS tc LEFT JOIN information_schema.KEY_COLUMN_USAGE k ON k.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND k.TABLE_NAME=tc.TABLE_NAME AND k.CONSTRAINT_NAME=tc.CONSTRAINT_NAME LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS rc ON rc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND rc.TABLE_NAME=tc.TABLE_NAME AND rc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME WHERE tc.CONSTRAINT_SCHEMA=@p4_schema AND tc.CONSTRAINT_NAME='fk_customer_status_history_customer_agency');
SET @p4_fk_expected=CONCAT('FOREIGN KEY|customer_status_history|customer_id,agency_id|',@p4_schema,'|customers|id,agency_id|RESTRICT|RESTRICT');
SET @p4_sql=CASE WHEN @p4_fk IS NULL THEN 'ALTER TABLE customer_status_history ADD CONSTRAINT fk_customer_status_history_customer_agency FOREIGN KEY (customer_id,agency_id) REFERENCES customers(id,agency_id) ON UPDATE RESTRICT ON DELETE RESTRICT' WHEN BINARY @p4_fk=BINARY @p4_fk_expected THEN 'DO 0' ELSE 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 4 schema mismatch: history customer foreign key''' END; EXECUTE IMMEDIATE @p4_sql;

SET @p4_fk=(SELECT IF(COUNT(*)=0,NULL,CONCAT(COALESCE(MAX(tc.CONSTRAINT_TYPE),''),'|',COALESCE(MAX(tc.TABLE_NAME),''),'|',COALESCE(GROUP_CONCAT(k.COLUMN_NAME ORDER BY k.ORDINAL_POSITION SEPARATOR ','),''),'|',COALESCE(MAX(k.REFERENCED_TABLE_SCHEMA),''),'|',COALESCE(MAX(k.REFERENCED_TABLE_NAME),''),'|',COALESCE(GROUP_CONCAT(k.REFERENCED_COLUMN_NAME ORDER BY k.ORDINAL_POSITION SEPARATOR ','),''),'|',COALESCE(MAX(CASE WHEN rc.UPDATE_RULE IN ('RESTRICT','NO ACTION') THEN 'RESTRICT' ELSE rc.UPDATE_RULE END),''),'|',COALESCE(MAX(CASE WHEN rc.DELETE_RULE IN ('RESTRICT','NO ACTION') THEN 'RESTRICT' ELSE rc.DELETE_RULE END),''))) FROM information_schema.TABLE_CONSTRAINTS tc LEFT JOIN information_schema.KEY_COLUMN_USAGE k ON k.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND k.TABLE_NAME=tc.TABLE_NAME AND k.CONSTRAINT_NAME=tc.CONSTRAINT_NAME LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS rc ON rc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND rc.TABLE_NAME=tc.TABLE_NAME AND rc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME WHERE tc.CONSTRAINT_SCHEMA=@p4_schema AND tc.CONSTRAINT_NAME='fk_customer_status_history_agency');
SET @p4_fk_expected=CONCAT('FOREIGN KEY|customer_status_history|agency_id|',@p4_schema,'|agencies|id|RESTRICT|RESTRICT');
SET @p4_sql=CASE WHEN @p4_fk IS NULL THEN 'ALTER TABLE customer_status_history ADD CONSTRAINT fk_customer_status_history_agency FOREIGN KEY (agency_id) REFERENCES agencies(id) ON UPDATE RESTRICT ON DELETE RESTRICT' WHEN BINARY @p4_fk=BINARY @p4_fk_expected THEN 'DO 0' ELSE 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 4 schema mismatch: history agency foreign key''' END; EXECUTE IMMEDIATE @p4_sql;

SET @p4_fk=(SELECT IF(COUNT(*)=0,NULL,CONCAT(COALESCE(MAX(tc.CONSTRAINT_TYPE),''),'|',COALESCE(MAX(tc.TABLE_NAME),''),'|',COALESCE(GROUP_CONCAT(k.COLUMN_NAME ORDER BY k.ORDINAL_POSITION SEPARATOR ','),''),'|',COALESCE(MAX(k.REFERENCED_TABLE_SCHEMA),''),'|',COALESCE(MAX(k.REFERENCED_TABLE_NAME),''),'|',COALESCE(GROUP_CONCAT(k.REFERENCED_COLUMN_NAME ORDER BY k.ORDINAL_POSITION SEPARATOR ','),''),'|',COALESCE(MAX(CASE WHEN rc.UPDATE_RULE IN ('RESTRICT','NO ACTION') THEN 'RESTRICT' ELSE rc.UPDATE_RULE END),''),'|',COALESCE(MAX(CASE WHEN rc.DELETE_RULE IN ('RESTRICT','NO ACTION') THEN 'RESTRICT' ELSE rc.DELETE_RULE END),''))) FROM information_schema.TABLE_CONSTRAINTS tc LEFT JOIN information_schema.KEY_COLUMN_USAGE k ON k.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND k.TABLE_NAME=tc.TABLE_NAME AND k.CONSTRAINT_NAME=tc.CONSTRAINT_NAME LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS rc ON rc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND rc.TABLE_NAME=tc.TABLE_NAME AND rc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME WHERE tc.CONSTRAINT_SCHEMA=@p4_schema AND tc.CONSTRAINT_NAME='fk_customer_status_history_user');
SET @p4_fk_expected=CONCAT('FOREIGN KEY|customer_status_history|changed_by|',@p4_schema,'|users|id|RESTRICT|RESTRICT');
SET @p4_sql=CASE WHEN @p4_fk IS NULL THEN 'ALTER TABLE customer_status_history ADD CONSTRAINT fk_customer_status_history_user FOREIGN KEY (changed_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT' WHEN BINARY @p4_fk=BINARY @p4_fk_expected THEN 'DO 0' ELSE 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 4 schema mismatch: history user foreign key''' END; EXECUTE IMMEDIATE @p4_sql;

-- CHECK expressions are compared conservatively after removing only whitespace and
-- identifier quoting. Same-named unrelated or permissive expressions fail closed.
SET @p4_check=(SELECT IF(COUNT(*)=0,NULL,CONCAT(COALESCE(MAX(tc.CONSTRAINT_TYPE),''),'|',COALESCE(MAX(tc.TABLE_NAME),''),'|',COALESCE(MAX(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(cc.CHECK_CLAUSE,'`',''),' ',''),CHAR(9),''),CHAR(10),''),CHAR(13),'')),''))) FROM information_schema.TABLE_CONSTRAINTS tc LEFT JOIN information_schema.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND cc.TABLE_NAME=tc.TABLE_NAME AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME WHERE tc.CONSTRAINT_SCHEMA=@p4_schema AND tc.CONSTRAINT_NAME='chk_customer_history_previous_status');
SET @p4_check_expected='CHECK|customer_status_history|previous_statusisnullorprevious_statusin(''new'',''regular'',''VIP'',''watchlist'',''blocked'',''archived'')';
SET @p4_sql=CASE WHEN @p4_check IS NULL THEN 'ALTER TABLE customer_status_history ADD CONSTRAINT chk_customer_history_previous_status CHECK (previous_status IS NULL OR previous_status IN (''new'',''regular'',''VIP'',''watchlist'',''blocked'',''archived''))' WHEN BINARY @p4_check=BINARY @p4_check_expected THEN 'DO 0' ELSE 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 4 schema mismatch: previous status check''' END; EXECUTE IMMEDIATE @p4_sql;

SET @p4_check=(SELECT IF(COUNT(*)=0,NULL,CONCAT(COALESCE(MAX(tc.CONSTRAINT_TYPE),''),'|',COALESCE(MAX(tc.TABLE_NAME),''),'|',COALESCE(MAX(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(cc.CHECK_CLAUSE,'`',''),' ',''),CHAR(9),''),CHAR(10),''),CHAR(13),'')),''))) FROM information_schema.TABLE_CONSTRAINTS tc LEFT JOIN information_schema.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND cc.TABLE_NAME=tc.TABLE_NAME AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME WHERE tc.CONSTRAINT_SCHEMA=@p4_schema AND tc.CONSTRAINT_NAME='chk_customer_history_new_status');
SET @p4_check_expected='CHECK|customer_status_history|new_statusin(''new'',''regular'',''VIP'',''watchlist'',''blocked'',''archived'')';
SET @p4_sql=CASE WHEN @p4_check IS NULL THEN 'ALTER TABLE customer_status_history ADD CONSTRAINT chk_customer_history_new_status CHECK (new_status IN (''new'',''regular'',''VIP'',''watchlist'',''blocked'',''archived''))' WHEN BINARY @p4_check=BINARY @p4_check_expected THEN 'DO 0' ELSE 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 4 schema mismatch: new status check''' END; EXECUTE IMMEDIATE @p4_sql;

SET @p4_check=(SELECT IF(COUNT(*)=0,NULL,CONCAT(COALESCE(MAX(tc.CONSTRAINT_TYPE),''),'|',COALESCE(MAX(tc.TABLE_NAME),''),'|',COALESCE(MAX(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(cc.CHECK_CLAUSE,'`',''),' ',''),CHAR(9),''),CHAR(10),''),CHAR(13),'')),''))) FROM information_schema.TABLE_CONSTRAINTS tc LEFT JOIN information_schema.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND cc.TABLE_NAME=tc.TABLE_NAME AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME WHERE tc.CONSTRAINT_SCHEMA=@p4_schema AND tc.CONSTRAINT_NAME='chk_customer_history_action');
SET @p4_check_expected='CHECK|customer_status_history|action_typein(''migration_baseline'',''created'',''status_changed'',''blocked'',''unblocked'',''archived'')';
SET @p4_sql=CASE WHEN @p4_check IS NULL THEN 'ALTER TABLE customer_status_history ADD CONSTRAINT chk_customer_history_action CHECK (action_type IN (''migration_baseline'',''created'',''status_changed'',''blocked'',''unblocked'',''archived''))' WHEN BINARY @p4_check=BINARY @p4_check_expected THEN 'DO 0' ELSE 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 4 schema mismatch: action type check''' END; EXECUTE IMMEDIATE @p4_sql;

SET @p4_check=(SELECT IF(COUNT(*)=0,NULL,CONCAT(COALESCE(MAX(tc.CONSTRAINT_TYPE),''),'|',COALESCE(MAX(tc.TABLE_NAME),''),'|',COALESCE(MAX(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(cc.CHECK_CLAUSE,'`',''),' ',''),CHAR(9),''),CHAR(10),''),CHAR(13),'')),''))) FROM information_schema.TABLE_CONSTRAINTS tc LEFT JOIN information_schema.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND cc.TABLE_NAME=tc.TABLE_NAME AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME WHERE tc.CONSTRAINT_SCHEMA=@p4_schema AND tc.CONSTRAINT_NAME='chk_customer_history_reason');
SET @p4_check_expected='CHECK|customer_status_history|action_typenotin(''blocked'',''unblocked'',''archived'')orchar_length(trim(coalesce(reason,'''')))between1and255';
SET @p4_sql=CASE WHEN @p4_check IS NULL THEN 'ALTER TABLE customer_status_history ADD CONSTRAINT chk_customer_history_reason CHECK (action_type NOT IN (''blocked'',''unblocked'',''archived'') OR CHAR_LENGTH(TRIM(COALESCE(reason,''''))) BETWEEN 1 AND 255)' WHEN BINARY @p4_check=BINARY @p4_check_expected THEN 'DO 0' ELSE 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 4 schema mismatch: sensitive reason check''' END; EXECUTE IMMEDIATE @p4_sql;

-- Listing/planning indexes.
SET @p4_index_columns=(SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='reservations' AND INDEX_NAME='idx_reservation_agency_status_period');
SET @p4_sql=CASE WHEN @p4_index_columns IS NULL THEN 'ALTER TABLE reservations ADD KEY idx_reservation_agency_status_period (agency_id,status,pickup_at,return_at,id)' WHEN @p4_index_columns='agency_id,status,pickup_at,return_at,id' THEN 'DO 0' ELSE 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 4 schema mismatch: reservation planning index''' END; EXECUTE IMMEDIATE @p4_sql;
SET @p4_index_columns=(SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@p4_schema AND TABLE_NAME='reservations' AND INDEX_NAME='idx_reservation_customer_status_period');
SET @p4_sql=CASE WHEN @p4_index_columns IS NULL THEN 'ALTER TABLE reservations ADD KEY idx_reservation_customer_status_period (customer_id,status,return_at,id)' WHEN @p4_index_columns='customer_id,status,return_at,id' THEN 'DO 0' ELSE 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 4 schema mismatch: reservation customer index''' END; EXECUTE IMMEDIATE @p4_sql;

-- Deterministic tax-rate backfill. Invalid/missing legacy snapshots remain NULL unless
-- the stored tax amount proves the rate is zero.
UPDATE reservations
SET tax_rate = CASE
    WHEN tax_amount=0 THEN 0.00
    WHEN JSON_VALID(pricing_snapshot_json)
         AND JSON_UNQUOTE(JSON_EXTRACT(pricing_snapshot_json,'$.tax_rate')) REGEXP '^[0-9]{1,3}(\\.[0-9]{1,2})?$'
         AND CAST(JSON_UNQUOTE(JSON_EXTRACT(pricing_snapshot_json,'$.tax_rate')) AS DECIMAL(5,2)) BETWEEN 0 AND 100
    THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(pricing_snapshot_json,'$.tax_rate')) AS DECIMAL(5,2))
    ELSE NULL
END
WHERE tax_rate IS NULL;

-- Baseline insertion occurs only after the generated unique slot exists.
INSERT INTO customer_status_history(customer_id,agency_id,previous_status,new_status,action_type,reason,changed_by,changed_at)
SELECT c.id,c.agency_id,NULL,c.status,'migration_baseline',NULL,NULL,COALESCE(c.created_at,CURRENT_TIMESTAMP(6))
FROM customers c
WHERE NOT EXISTS (
    SELECT 1 FROM customer_status_history h
    WHERE h.customer_id=c.id AND h.action_type='migration_baseline'
);

-- Final invariants. Dynamic SIGNAL keeps errors generic and prevents version recording.
SET @p4_duplicate_baselines=(SELECT COUNT(*) FROM (SELECT customer_id FROM customer_status_history WHERE action_type='migration_baseline' GROUP BY customer_id HAVING COUNT(*)>1) d);
SET @p4_missing_baselines=(SELECT COUNT(*) FROM customers c LEFT JOIN customer_status_history h ON h.customer_id=c.id AND h.action_type='migration_baseline' WHERE h.id IS NULL);
SET @p4_invalid_history=(SELECT COUNT(*) FROM customer_status_history WHERE new_status NOT IN ('new','regular','VIP','watchlist','blocked','archived') OR action_type NOT IN ('migration_baseline','created','status_changed','blocked','unblocked','archived'));
SET @p4_sql=IF(@p4_duplicate_baselines=0 AND @p4_missing_baselines=0 AND @p4_invalid_history=0,'DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 4 history invariant failed'''); EXECUTE IMMEDIATE @p4_sql;

-- Do not insert schema_migrations here. bin/migrate.php records version 005 only after
-- every statement above returns successfully.
