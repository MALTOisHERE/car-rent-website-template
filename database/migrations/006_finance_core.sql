-- Phase 5A: append-only finance core.
-- MariaDB DDL is not transactional. This migration therefore validates legacy
-- data first, uses additive/repeatable DDL, and fails closed on ambiguity.

SET @p5a_schema = DATABASE();

-- Structural preflight for retry/partial-DDL safety. A table created by this
-- migration is atomic; therefore an existing table missing any core column is
-- conflicting external/partial DDL and must fail before cutover alterations.
SET @p5a_bad_partial = (
    SELECT COUNT(*) FROM (
        SELECT 'financial_number_allocations' table_name,12 required_count UNION ALL
        SELECT 'finance_idempotency_keys',10 UNION ALL
        SELECT 'payment_adjustments',16 UNION ALL
        SELECT 'deposit_events',15 UNION ALL
        SELECT 'cash_movements',16
    ) expected
    JOIN information_schema.TABLES t ON t.TABLE_SCHEMA=@p5a_schema AND t.TABLE_NAME=expected.table_name
    LEFT JOIN (
        SELECT TABLE_NAME,COUNT(*) actual_count FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=@p5a_schema AND TABLE_NAME IN('financial_number_allocations','finance_idempotency_keys','payment_adjustments','deposit_events','cash_movements')
        GROUP BY TABLE_NAME
    ) actual ON actual.TABLE_NAME=expected.table_name
    WHERE COALESCE(actual.actual_count,0)<>expected.required_count
);
SET @p5a_sql=IF(@p5a_bad_partial=0,'DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5A preflight: incompatible partial finance table''');
EXECUTE IMMEDIATE @p5a_sql;

SET @p5a_bad_column = (
    SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@p5a_schema AND (
        (TABLE_NAME='reservations' AND COLUMN_NAME='legacy_finance_paid_amount' AND COLUMN_TYPE<>'decimal(12,2)') OR
        (TABLE_NAME='payments' AND COLUMN_NAME='is_legacy_opening' AND COLUMN_TYPE<>'tinyint(1)') OR
        (TABLE_NAME='deposits' AND COLUMN_NAME='agency_id' AND COLUMN_TYPE<>'bigint(20) unsigned') OR
        (TABLE_NAME='cash_registers' AND COLUMN_NAME='currency' AND COLUMN_TYPE<>'char(3)')
    )
);
SET @p5a_sql=IF(@p5a_bad_column=0,'DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5A preflight: incompatible partial finance column''');
EXECUTE IMMEDIATE @p5a_sql;

-- Exact structural preflight.  ADD/CREATE ... IF NOT EXISTS is deliberately
-- not treated as proof of compatibility: an object with the right name but a
-- different definition is an unsafe partial migration and must fail closed.
-- Missing additive objects remain eligible for the DDL below; existing
-- objects are compared field-by-field through information_schema.
CREATE TEMPORARY TABLE _p5a_expected_tables (
    table_name VARCHAR(64) PRIMARY KEY,
    expected_engine VARCHAR(64) NOT NULL,
    expected_charset VARCHAR(64) NOT NULL,
    expected_collation VARCHAR(64) NOT NULL
) ENGINE=InnoDB;
INSERT INTO _p5a_expected_tables VALUES
 ('financial_number_allocations','InnoDB','utf8mb4','utf8mb4_unicode_ci'),
 ('finance_idempotency_keys','InnoDB','utf8mb4','utf8mb4_unicode_ci'),
 ('payment_adjustments','InnoDB','utf8mb4','utf8mb4_unicode_ci'),
 ('deposit_events','InnoDB','utf8mb4','utf8mb4_unicode_ci'),
 ('cash_movements','InnoDB','utf8mb4','utf8mb4_unicode_ci');

CREATE TEMPORARY TABLE _p5a_expected_columns (
    table_name VARCHAR(64) NOT NULL,
    column_name VARCHAR(64) NOT NULL,
    expected_type VARCHAR(255) NOT NULL,
    expected_nullable CHAR(3) NOT NULL,
    expected_default VARCHAR(255) NULL,
    expected_extra VARCHAR(255) NOT NULL,
    expected_generation TEXT NULL,
    expected_datetime_precision INT NULL,
    required_existing TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY(table_name,column_name)
) ENGINE=InnoDB;
INSERT INTO _p5a_expected_columns
 (table_name,column_name,expected_type,expected_nullable,expected_default,expected_extra,expected_generation,expected_datetime_precision,required_existing) VALUES
 ('reservations','legacy_finance_paid_amount','decimal(12,2)','NO','0.00','',NULL,NULL,0),
 ('reservations','finance_tracking_started_at','datetime(6)','YES',NULL,'',NULL,6,0),
 ('payments','is_legacy_opening','tinyint(1)','NO','0','',NULL,NULL,0),
 ('payments','proof_original_name','varchar(255)','YES',NULL,'',NULL,NULL,0),
 ('payments','proof_mime_type','varchar(100)','YES',NULL,'',NULL,NULL,0),
 ('payments','proof_file_size','bigint(20) unsigned','YES',NULL,'',NULL,NULL,0),
 ('payments','updated_at','datetime(6)','NO','current_timestamp(6)','on update current_timestamp(6)',NULL,6,0),
 ('invoices','invoice_number','varchar(50)','YES',NULL,'',NULL,NULL,1),
 ('invoices','issued_at','datetime(6)','YES',NULL,'',NULL,6,1),
 ('invoices','created_at','datetime(6)','NO','current_timestamp(6)','',NULL,6,1),
 ('invoices','updated_at','datetime(6)','NO','current_timestamp(6)','on update current_timestamp(6)',NULL,6,1),
 ('invoices','language_code','char(2)','NO','en','',NULL,NULL,0),
 ('invoices','opening_paid_amount','decimal(12,2)','NO','0.00','',NULL,NULL,0),
 ('invoices','original_invoice_id','bigint(20) unsigned','YES',NULL,'',NULL,NULL,0),
 ('invoices','credit_reason','varchar(255)','YES',NULL,'',NULL,NULL,0),
 ('invoices','cancelled_by','bigint(20) unsigned','YES',NULL,'',NULL,NULL,0),
 ('invoices','active_reservation_id','bigint(20) unsigned','YES',NULL,'stored generated','casewheninvoice_type=''invoice''andreservation_idisnotnullandstatusnotin(''cancelled'',''credited'')thenreservation_idelsenullend',NULL,0),
 ('deposits','agency_id','bigint(20) unsigned','NO',NULL,'',NULL,NULL,0),
 ('deposits','received_amount','decimal(12,2)','YES',NULL,'',NULL,NULL,0),
 ('deposits','returned_amount','decimal(12,2)','YES',NULL,'',NULL,NULL,0),
 ('deposits','legacy_opening_received_amount','decimal(12,2)','YES',NULL,'',NULL,NULL,0),
 ('deposits','legacy_opening_retained_amount','decimal(12,2)','NO','0.00','',NULL,NULL,0),
 ('deposits','legacy_opening_returned_amount','decimal(12,2)','YES',NULL,'',NULL,NULL,0),
 ('deposits','legacy_opening_status','varchar(30)','YES',NULL,'',NULL,NULL,0),
 ('deposits','legacy_opening_resolved_at','datetime(6)','YES',NULL,'',NULL,6,0),
 ('deposits','legacy_opening_resolved_by','bigint(20) unsigned','YES',NULL,'',NULL,NULL,0),
 ('deposits','legacy_opening_resolution_reason','varchar(255)','YES',NULL,'',NULL,NULL,0),
 ('deposits','event_tracking_started_at','datetime(6)','YES',NULL,'',NULL,6,0),
 ('deposits','updated_at','datetime(6)','NO','current_timestamp(6)','on update current_timestamp(6)',NULL,6,1),
 ('expenses','method','varchar(30)','YES',NULL,'',NULL,NULL,0),
 ('expenses','expense_type','varchar(30)','NO','expense','',NULL,NULL,0),
 ('expenses','direction','varchar(20)','NO','outflow','',NULL,NULL,0),
 ('expenses','original_expense_id','bigint(20) unsigned','YES',NULL,'',NULL,NULL,0),
 ('expenses','receipt_original_name','varchar(255)','YES',NULL,'',NULL,NULL,0),
 ('expenses','receipt_mime_type','varchar(100)','YES',NULL,'',NULL,NULL,0),
 ('expenses','receipt_file_size','bigint(20) unsigned','YES',NULL,'',NULL,NULL,0),
 ('expenses','decided_at','datetime(6)','YES',NULL,'',NULL,6,0),
 ('expenses','decided_by','bigint(20) unsigned','YES',NULL,'',NULL,NULL,0),
 ('expenses','decision_reason','varchar(255)','YES',NULL,'',NULL,NULL,0),
 ('expenses','owner_exception_used','tinyint(1)','NO','0','',NULL,NULL,0),
 ('expenses','owner_exception_reason','varchar(255)','YES',NULL,'',NULL,NULL,0),
 ('cash_registers','currency','char(3)','NO',NULL,'',NULL,NULL,0),
 ('cash_registers','legacy_net_movement_amount','decimal(12,2)','NO','0.00','',NULL,NULL,0),
 ('cash_registers','movement_tracking_started_at','datetime(6)','YES',NULL,'',NULL,6,0),
 ('cash_registers','closing_boundary_at','datetime(6)','YES',NULL,'',NULL,6,0),
 ('cash_registers','difference_reason','varchar(255)','YES',NULL,'',NULL,NULL,0),
 ('cash_registers','updated_at','datetime(6)','NO','current_timestamp(6)','on update current_timestamp(6)',NULL,6,0),
 ('cash_registers','open_agency_id','bigint(20) unsigned','YES',NULL,'stored generated','casewhenstatus=''open''thenagency_idelsenullend',NULL,0),
 ('financial_number_allocations','id','bigint(20) unsigned','NO',NULL,'auto_increment',NULL,NULL,0),
 ('financial_number_allocations','agency_id','bigint(20) unsigned','NO',NULL,'',NULL,NULL,0),
 ('financial_number_allocations','number_type','varchar(30)','NO',NULL,'',NULL,NULL,0),
 ('financial_number_allocations','allocated_number','varchar(50)','YES',NULL,'',NULL,NULL,0),
 ('financial_number_allocations','status','varchar(20)','NO','reserved','',NULL,NULL,0),
 ('financial_number_allocations','entity_type','varchar(40)','YES',NULL,'',NULL,NULL,0),
 ('financial_number_allocations','entity_id','bigint(20) unsigned','YES',NULL,'',NULL,NULL,0),
 ('financial_number_allocations','allocated_by','bigint(20) unsigned','YES',NULL,'',NULL,NULL,0),
 ('financial_number_allocations','allocated_at','datetime(6)','NO','current_timestamp(6)','',NULL,6,0),
 ('financial_number_allocations','consumed_at','datetime(6)','YES',NULL,'',NULL,6,0),
 ('financial_number_allocations','voided_at','datetime(6)','YES',NULL,'',NULL,6,0),
 ('financial_number_allocations','void_reason','varchar(255)','YES',NULL,'',NULL,NULL,0),
 ('finance_idempotency_keys','id','bigint(20) unsigned','NO',NULL,'auto_increment',NULL,NULL,0),
 ('finance_idempotency_keys','agency_id','bigint(20) unsigned','NO',NULL,'',NULL,NULL,0),
 ('finance_idempotency_keys','operation_type','varchar(50)','NO',NULL,'',NULL,NULL,0),
 ('finance_idempotency_keys','key_hash','char(64)','NO',NULL,'',NULL,NULL,0),
 ('finance_idempotency_keys','status','varchar(20)','NO','in_progress','',NULL,NULL,0),
 ('finance_idempotency_keys','result_entity_type','varchar(40)','YES',NULL,'',NULL,NULL,0),
 ('finance_idempotency_keys','result_entity_id','bigint(20) unsigned','YES',NULL,'',NULL,NULL,0),
 ('finance_idempotency_keys','created_by','bigint(20) unsigned','YES',NULL,'',NULL,NULL,0),
 ('finance_idempotency_keys','created_at','datetime(6)','NO','current_timestamp(6)','',NULL,6,0),
 ('finance_idempotency_keys','completed_at','datetime(6)','YES',NULL,'',NULL,6,0),
 ('payment_adjustments','id','bigint(20) unsigned','NO',NULL,'auto_increment',NULL,NULL,0),
 ('payment_adjustments','agency_id','bigint(20) unsigned','NO',NULL,'',NULL,NULL,0),
 ('payment_adjustments','payment_id','bigint(20) unsigned','NO',NULL,'',NULL,NULL,0),
 ('payment_adjustments','destination_deposit_id','bigint(20) unsigned','YES',NULL,'',NULL,NULL,0),
 ('payment_adjustments','adjustment_number','varchar(50)','NO',NULL,'',NULL,NULL,0),
 ('payment_adjustments','adjustment_type','varchar(30)','NO',NULL,'',NULL,NULL,0),
 ('payment_adjustments','amount','decimal(12,2)','NO',NULL,'',NULL,NULL,0),
 ('payment_adjustments','currency','char(3)','NO',NULL,'',NULL,NULL,0),
 ('payment_adjustments','method','varchar(30)','YES',NULL,'',NULL,NULL,0),
 ('payment_adjustments','reference','varchar(100)','YES',NULL,'',NULL,NULL,0),
 ('payment_adjustments','reason','varchar(255)','NO',NULL,'',NULL,NULL,0),
 ('payment_adjustments','status','varchar(20)','NO','posted','',NULL,NULL,0),
 ('payment_adjustments','occurred_at','datetime(6)','NO','current_timestamp(6)','',NULL,6,0),
 ('payment_adjustments','created_by','bigint(20) unsigned','YES',NULL,'',NULL,NULL,0),
 ('payment_adjustments','created_at','datetime(6)','NO','current_timestamp(6)','',NULL,6,0),
 ('payment_adjustments','excess_payment_id','bigint(20) unsigned','YES',NULL,'stored generated','casewhenadjustment_type=''excess_reallocation''thenpayment_idelsenullend',NULL,0),
 ('deposit_events','id','bigint(20) unsigned','NO',NULL,'auto_increment',NULL,NULL,0),
 ('deposit_events','agency_id','bigint(20) unsigned','NO',NULL,'',NULL,NULL,0),
 ('deposit_events','deposit_id','bigint(20) unsigned','NO',NULL,'',NULL,NULL,0),
 ('deposit_events','event_number','varchar(50)','NO',NULL,'',NULL,NULL,0),
 ('deposit_events','event_type','varchar(30)','NO',NULL,'',NULL,NULL,0),
 ('deposit_events','amount','decimal(12,2)','NO','0.00','',NULL,NULL,0),
 ('deposit_events','currency','char(3)','NO',NULL,'',NULL,NULL,0),
 ('deposit_events','method','varchar(30)','YES',NULL,'',NULL,NULL,0),
 ('deposit_events','payment_id','bigint(20) unsigned','YES',NULL,'',NULL,NULL,0),
 ('deposit_events','reason','varchar(255)','YES',NULL,'',NULL,NULL,0),
 ('deposit_events','status','varchar(20)','NO','posted','',NULL,NULL,0),
 ('deposit_events','occurred_at','datetime(6)','NO','current_timestamp(6)','',NULL,6,0),
 ('deposit_events','created_by','bigint(20) unsigned','YES',NULL,'',NULL,NULL,0),
 ('deposit_events','created_at','datetime(6)','NO','current_timestamp(6)','',NULL,6,0),
 ('deposit_events','requested_deposit_id','bigint(20) unsigned','YES',NULL,'stored generated','casewhenevent_type=''requested''thendeposit_idelsenullend',NULL,0),
 ('cash_movements','id','bigint(20) unsigned','NO',NULL,'auto_increment',NULL,NULL,0),
 ('cash_movements','agency_id','bigint(20) unsigned','NO',NULL,'',NULL,NULL,0),
 ('cash_movements','cash_register_id','bigint(20) unsigned','NO',NULL,'',NULL,NULL,0),
 ('cash_movements','movement_number','varchar(50)','NO',NULL,'',NULL,NULL,0),
 ('cash_movements','movement_type','varchar(30)','NO',NULL,'',NULL,NULL,0),
 ('cash_movements','direction','varchar(10)','NO',NULL,'',NULL,NULL,0),
 ('cash_movements','amount','decimal(12,2)','NO',NULL,'',NULL,NULL,0),
 ('cash_movements','currency','char(3)','NO',NULL,'',NULL,NULL,0),
 ('cash_movements','source_entity_type','varchar(40)','NO',NULL,'',NULL,NULL,0),
 ('cash_movements','source_entity_id','bigint(20) unsigned','NO',NULL,'',NULL,NULL,0),
 ('cash_movements','reason','varchar(255)','YES',NULL,'',NULL,NULL,0),
 ('cash_movements','status','varchar(20)','NO','posted','',NULL,NULL,0),
 ('cash_movements','occurred_at','datetime(6)','NO','current_timestamp(6)','',NULL,6,0),
 ('cash_movements','created_by','bigint(20) unsigned','YES',NULL,'',NULL,NULL,0),
 ('cash_movements','created_at','datetime(6)','NO','current_timestamp(6)','',NULL,6,0),
 ('cash_movements','source_key','varchar(190)','YES',NULL,'stored generated','concat(movement_type,''|'',source_entity_type,''|'',source_entity_id)',NULL,0);

-- For existing invoice/deposit columns, accept only the known pre-006 legacy
-- form or the exact post-006 form.  Any other definition is incompatible.
SET @p5a_bad = (
 SELECT COUNT(*) FROM _p5a_expected_columns e
 LEFT JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=@p5a_schema AND c.TABLE_NAME=e.table_name AND c.COLUMN_NAME=e.column_name
 LEFT JOIN information_schema.TABLES t ON t.TABLE_SCHEMA=@p5a_schema AND t.TABLE_NAME=e.table_name
 WHERE (c.COLUMN_NAME IS NULL AND (e.required_existing=1 OR t.TABLE_NAME IS NOT NULL AND e.table_name IN ('financial_number_allocations','finance_idempotency_keys','payment_adjustments','deposit_events','cash_movements')))
    OR (c.COLUMN_NAME IS NOT NULL AND NOT (
        LOWER(c.COLUMN_TYPE)=LOWER(e.expected_type)
        AND c.IS_NULLABLE=e.expected_nullable
        AND LOWER(TRIM(COALESCE(c.COLUMN_DEFAULT,'<null>')))=LOWER(TRIM(COALESCE(e.expected_default,'<null>')))
        AND LOWER(TRIM(COALESCE(c.EXTRA,'')))=LOWER(TRIM(COALESCE(e.expected_extra,'')))
        AND (e.expected_generation IS NULL OR LOWER(REPLACE(REPLACE(TRIM(COALESCE(c.GENERATION_EXPRESSION,'')),'`',''),' ',''))=LOWER(e.expected_generation))
        AND (e.expected_datetime_precision IS NULL OR c.DATETIME_PRECISION=e.expected_datetime_precision)
    ) AND NOT (
        (e.table_name='invoices' AND e.column_name='invoice_number' AND LOWER(c.COLUMN_TYPE)='varchar(50)' AND c.IS_NULLABLE='NO' AND c.COLUMN_DEFAULT IS NULL)
        OR (e.table_name='invoices' AND e.column_name='issued_at' AND LOWER(c.COLUMN_TYPE)='datetime' AND c.IS_NULLABLE='NO' AND c.COLUMN_DEFAULT IS NULL)
        OR (e.table_name='invoices' AND e.column_name IN ('created_at','updated_at') AND LOWER(c.COLUMN_TYPE)='datetime' AND c.IS_NULLABLE='NO' AND LOWER(TRIM(COALESCE(c.COLUMN_DEFAULT,'')))='current_timestamp' AND (e.column_name='created_at' OR LOWER(TRIM(COALESCE(c.EXTRA,'')))='on update current_timestamp'))
        OR (e.table_name='deposits' AND e.column_name='updated_at' AND LOWER(c.COLUMN_TYPE)='datetime' AND c.IS_NULLABLE='NO' AND LOWER(TRIM(COALESCE(c.COLUMN_DEFAULT,'')))='current_timestamp' AND LOWER(TRIM(COALESCE(c.EXTRA,'')))='on update current_timestamp')
    ))
);
SET @p5a_sql=IF(@p5a_bad=0,'DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5A schema mismatch: incompatible column definition'''); EXECUTE IMMEDIATE @p5a_sql;

CREATE TEMPORARY TABLE _p5a_expected_indexes (
    table_name VARCHAR(64) NOT NULL,
    index_name VARCHAR(64) NOT NULL,
    non_unique TINYINT NOT NULL,
    index_type VARCHAR(32) NOT NULL,
    ordered_columns VARCHAR(500) NOT NULL,
    PRIMARY KEY(table_name,index_name)
) ENGINE=InnoDB;
INSERT INTO _p5a_expected_indexes VALUES
 ('reservations','uq_reservations_id_agency',0,'BTREE','id,agency_id'),
 ('payments','uq_payments_id_agency',0,'BTREE','id,agency_id'),
 ('payments','idx_payments_reservation_settlement',1,'BTREE','agency_id,reservation_id,is_legacy_opening,status,created_at,id'),
 ('payments','idx_payments_invoice_settlement',1,'BTREE','agency_id,invoice_id,status,created_at,id'),
 ('invoices','uq_invoices_id_agency',0,'BTREE','id,agency_id'),
 ('invoices','uq_invoices_active_reservation',0,'BTREE','active_reservation_id'),
 ('invoices','idx_invoices_original',1,'BTREE','agency_id,original_invoice_id,status,id'),
 ('invoices','idx_invoices_type_status_date',1,'BTREE','agency_id,invoice_type,status,issued_at,id'),
 ('deposits','uq_deposits_id_agency',0,'BTREE','id,agency_id'),
 ('deposits','idx_deposits_agency_reservation_status',1,'BTREE','agency_id,reservation_id,status,id'),
 ('expenses','uq_expenses_id_agency',0,'BTREE','id,agency_id'),
 ('expenses','idx_expenses_original',1,'BTREE','agency_id,original_expense_id,status,id'),
 ('cash_registers','uq_cash_registers_id_agency',0,'BTREE','id,agency_id'),
 ('cash_registers','uq_cash_register_open_agency',0,'BTREE','open_agency_id'),
 ('cash_registers','idx_cash_register_agency_status',1,'BTREE','agency_id,status,business_date,id'),
 ('financial_number_allocations','PRIMARY',0,'BTREE','id'),
 ('financial_number_allocations','uq_finance_number',0,'BTREE','allocated_number'),
 ('financial_number_allocations','idx_finance_number_state',1,'BTREE','agency_id,number_type,status,allocated_at,id'),
 ('finance_idempotency_keys','PRIMARY',0,'BTREE','id'),
 ('finance_idempotency_keys','uq_finance_idempotency',0,'BTREE','agency_id,key_hash'),
 ('finance_idempotency_keys','idx_finance_idempotency_actor',1,'BTREE','created_by,created_at,id'),
 ('payment_adjustments','PRIMARY',0,'BTREE','id'),
 ('payment_adjustments','uq_payment_adjustment_number',0,'BTREE','adjustment_number'),
 ('payment_adjustments','uq_payment_adjustment_excess',0,'BTREE','excess_payment_id'),
 ('payment_adjustments','idx_payment_adjustment_payment',1,'BTREE','agency_id,payment_id,occurred_at,id'),
 ('deposit_events','PRIMARY',0,'BTREE','id'),
 ('deposit_events','uq_deposit_event_number',0,'BTREE','event_number'),
 ('deposit_events','uq_deposit_requested',0,'BTREE','requested_deposit_id'),
 ('deposit_events','idx_deposit_event_deposit',1,'BTREE','agency_id,deposit_id,occurred_at,id'),
 ('cash_movements','PRIMARY',0,'BTREE','id'),
 ('cash_movements','uq_cash_movement_number',0,'BTREE','movement_number'),
 ('cash_movements','uq_cash_movement_source',0,'BTREE','agency_id,source_key'),
 ('cash_movements','idx_cash_movement_register',1,'BTREE','cash_register_id,occurred_at,id'),
 ('cash_movements','idx_cash_movement_agency_type',1,'BTREE','agency_id,movement_type,occurred_at,id');

SET @p5a_bad = (
 SELECT COUNT(*) FROM _p5a_expected_indexes e
 JOIN information_schema.TABLES t ON t.TABLE_SCHEMA=@p5a_schema AND t.TABLE_NAME=e.table_name
 LEFT JOIN (
   SELECT TABLE_NAME,INDEX_NAME,MAX(NON_UNIQUE) non_unique,MAX(INDEX_TYPE) index_type,
          GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') ordered_columns
   FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@p5a_schema GROUP BY TABLE_NAME,INDEX_NAME
 ) a ON a.TABLE_NAME=e.table_name AND a.INDEX_NAME=e.index_name
 WHERE (a.INDEX_NAME IS NULL AND e.index_name='PRIMARY')
    OR (a.INDEX_NAME IS NOT NULL AND (a.non_unique<>e.non_unique OR UPPER(a.index_type)<>UPPER(e.index_type) OR a.ordered_columns<>e.ordered_columns))
);
SET @p5a_sql=IF(@p5a_bad=0,'DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5A schema mismatch: incompatible index definition'''); EXECUTE IMMEDIATE @p5a_sql;

CREATE TEMPORARY TABLE _p5a_expected_fks (
    table_name VARCHAR(64) NOT NULL,
    constraint_name VARCHAR(64) NOT NULL,
    local_columns VARCHAR(500) NOT NULL,
    referenced_schema VARCHAR(64) NOT NULL,
    referenced_table VARCHAR(64) NOT NULL,
    referenced_columns VARCHAR(500) NOT NULL,
    update_rule VARCHAR(30) NOT NULL,
    delete_rule VARCHAR(30) NOT NULL,
    PRIMARY KEY(table_name,constraint_name)
) ENGINE=InnoDB;
INSERT INTO _p5a_expected_fks VALUES
 ('invoices','fk_invoices_customer_agency','customer_id,agency_id',@p5a_schema,'customers','id,agency_id','RESTRICT','RESTRICT'),
 ('invoices','fk_invoices_reservation_agency','reservation_id,agency_id',@p5a_schema,'reservations','id,agency_id','RESTRICT','RESTRICT'),
 ('payments','fk_payments_reservation_agency','reservation_id,agency_id',@p5a_schema,'reservations','id,agency_id','RESTRICT','RESTRICT'),
 ('payments','fk_payments_invoice_agency','invoice_id,agency_id',@p5a_schema,'invoices','id,agency_id','RESTRICT','RESTRICT'),
 ('expenses','fk_expenses_vehicle_agency','vehicle_id,agency_id',@p5a_schema,'vehicles','id,agency_id','RESTRICT','RESTRICT'),
 ('invoices','fk_invoices_original_agency','original_invoice_id,agency_id',@p5a_schema,'invoices','id,agency_id','RESTRICT','RESTRICT'),
 ('expenses','fk_expenses_original_agency','original_expense_id,agency_id',@p5a_schema,'expenses','id,agency_id','RESTRICT','RESTRICT'),
 ('deposits','fk_deposits_agency','agency_id',@p5a_schema,'agencies','id','RESTRICT','RESTRICT'),
 ('deposits','fk_deposits_reservation_agency','reservation_id,agency_id',@p5a_schema,'reservations','id,agency_id','RESTRICT','RESTRICT'),
 ('financial_number_allocations','fk_finance_number_agency','agency_id',@p5a_schema,'agencies','id','RESTRICT','RESTRICT'),
 ('financial_number_allocations','fk_finance_number_user','allocated_by',@p5a_schema,'users','id','RESTRICT','RESTRICT'),
 ('finance_idempotency_keys','fk_finance_idempotency_agency','agency_id',@p5a_schema,'agencies','id','RESTRICT','RESTRICT'),
 ('finance_idempotency_keys','fk_finance_idempotency_user','created_by',@p5a_schema,'users','id','RESTRICT','RESTRICT'),
 ('payment_adjustments','fk_payment_adjustment_agency','agency_id',@p5a_schema,'agencies','id','RESTRICT','RESTRICT'),
 ('payment_adjustments','fk_payment_adjustment_payment','payment_id,agency_id',@p5a_schema,'payments','id,agency_id','RESTRICT','RESTRICT'),
 ('payment_adjustments','fk_payment_adjustment_deposit','destination_deposit_id,agency_id',@p5a_schema,'deposits','id,agency_id','RESTRICT','RESTRICT'),
 ('payment_adjustments','fk_payment_adjustment_user','created_by',@p5a_schema,'users','id','RESTRICT','RESTRICT'),
 ('deposit_events','fk_deposit_event_agency','agency_id',@p5a_schema,'agencies','id','RESTRICT','RESTRICT'),
 ('deposit_events','fk_deposit_event_deposit','deposit_id,agency_id',@p5a_schema,'deposits','id,agency_id','RESTRICT','RESTRICT'),
 ('deposit_events','fk_deposit_event_payment','payment_id,agency_id',@p5a_schema,'payments','id,agency_id','RESTRICT','RESTRICT'),
 ('deposit_events','fk_deposit_event_user','created_by',@p5a_schema,'users','id','RESTRICT','RESTRICT'),
 ('cash_movements','fk_cash_movement_agency','agency_id',@p5a_schema,'agencies','id','RESTRICT','RESTRICT'),
 ('cash_movements','fk_cash_movement_register','cash_register_id,agency_id',@p5a_schema,'cash_registers','id,agency_id','RESTRICT','RESTRICT'),
 ('cash_movements','fk_cash_movement_user','created_by',@p5a_schema,'users','id','RESTRICT','RESTRICT');

SET @p5a_bad = (
 SELECT COUNT(*) FROM _p5a_expected_fks e
 JOIN information_schema.TABLES t ON t.TABLE_SCHEMA=@p5a_schema AND t.TABLE_NAME=e.table_name
 LEFT JOIN information_schema.TABLE_CONSTRAINTS tc ON tc.CONSTRAINT_SCHEMA=@p5a_schema
   AND tc.TABLE_NAME=e.table_name AND tc.CONSTRAINT_NAME=e.constraint_name
 LEFT JOIN (
   SELECT k.TABLE_NAME,k.CONSTRAINT_NAME,
          GROUP_CONCAT(k.COLUMN_NAME ORDER BY k.ORDINAL_POSITION SEPARATOR ',') local_columns,
          MAX(k.REFERENCED_TABLE_SCHEMA) referenced_schema,MAX(k.REFERENCED_TABLE_NAME) referenced_table,
          GROUP_CONCAT(k.REFERENCED_COLUMN_NAME ORDER BY k.ORDINAL_POSITION SEPARATOR ',') referenced_columns,
          MAX(r.UPDATE_RULE) update_rule,MAX(r.DELETE_RULE) delete_rule
   FROM information_schema.KEY_COLUMN_USAGE k
   JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
   WHERE k.CONSTRAINT_SCHEMA=@p5a_schema AND k.REFERENCED_TABLE_NAME IS NOT NULL
   GROUP BY k.TABLE_NAME,k.CONSTRAINT_NAME
 ) a ON a.TABLE_NAME=e.table_name AND a.CONSTRAINT_NAME=e.constraint_name
 WHERE tc.CONSTRAINT_NAME IS NOT NULL AND (
   tc.CONSTRAINT_TYPE<>'FOREIGN KEY' OR a.CONSTRAINT_NAME IS NULL
   OR a.local_columns<>e.local_columns OR a.referenced_schema<>e.referenced_schema OR a.referenced_table<>e.referenced_table OR a.referenced_columns<>e.referenced_columns
   OR UPPER(a.update_rule) NOT IN ('RESTRICT','NO ACTION') OR UPPER(a.delete_rule) NOT IN ('RESTRICT','NO ACTION')
 )
);
SET @p5a_sql=IF(@p5a_bad=0,'DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5A schema mismatch: incompatible foreign-key definition'''); EXECUTE IMMEDIATE @p5a_sql;

CREATE TEMPORARY TABLE _p5a_expected_checks (
    table_name VARCHAR(64) NOT NULL,
    constraint_name VARCHAR(64) NOT NULL,
    approved_clause TEXT NOT NULL,
    PRIMARY KEY(table_name,constraint_name)
) ENGINE=InnoDB;
INSERT INTO _p5a_expected_checks VALUES
 ('financial_number_allocations','chk_finance_number_type','number_type IN (''payment'',''payment_adjustment'',''invoice'',''credit_note'',''deposit_event'',''cash_movement'')'),
 ('financial_number_allocations','chk_finance_number_status','status IN (''reserved'',''consumed'',''voided'')'),
 ('finance_idempotency_keys','chk_finance_idempotency_status','status IN (''in_progress'',''completed'')'),
 ('payment_adjustments','chk_payment_adjustment_type','adjustment_type IN (''full_reversal'',''partial_refund'',''full_refund'',''excess_reallocation'')'),
 ('payment_adjustments','chk_payment_adjustment_amount','amount>0'),
 ('payment_adjustments','chk_payment_adjustment_method','(adjustment_type=''excess_reallocation'' AND method IS NULL AND destination_deposit_id IS NOT NULL) OR (adjustment_type<>''excess_reallocation'' AND method IN (''cash'',''card'',''bank_transfer'',''cheque'',''online'',''other''))'),
 ('payment_adjustments','chk_payment_adjustment_reason','CHAR_LENGTH(TRIM(reason)) BETWEEN 1 AND 255'),
 ('payment_adjustments','chk_payment_adjustment_status','status=''posted'''),
 ('deposit_events','chk_deposit_event_type','event_type IN (''requested'',''received'',''held'',''partially_retained'',''fully_retained'',''partially_returned'',''returned'')'),
 ('deposit_events','chk_deposit_event_amount','(event_type=''held'' AND amount=0) OR (event_type<>''held'' AND amount>0)'),
 ('deposit_events','chk_deposit_event_method','(event_type IN (''received'',''partially_returned'',''returned'') AND method IN (''cash'',''card'',''bank_transfer'',''cheque'',''online'',''other'')) OR (event_type NOT IN (''received'',''partially_returned'',''returned'') AND method IS NULL)'),
 ('deposit_events','chk_deposit_event_reason','event_type NOT IN (''partially_retained'',''fully_retained'') OR CHAR_LENGTH(TRIM(COALESCE(reason,''''))) BETWEEN 1 AND 255'),
 ('deposit_events','chk_deposit_event_status','status=''posted'''),
 ('cash_movements','chk_cash_movement_type','movement_type IN (''payment_in'',''refund_out'',''deposit_in'',''deposit_return_out'',''expense_out'',''manual_in'',''manual_out'',''closing_adjustment'')'),
 ('cash_movements','chk_cash_movement_direction','(movement_type IN (''payment_in'',''deposit_in'',''manual_in'') AND direction=''in'') OR (movement_type IN (''refund_out'',''deposit_return_out'',''expense_out'',''manual_out'') AND direction=''out'') OR (movement_type=''closing_adjustment'' AND direction IN (''in'',''out''))'),
 ('cash_movements','chk_cash_movement_amount','amount>0'),
 ('cash_movements','chk_cash_movement_reason','movement_type NOT IN (''manual_in'',''manual_out'',''closing_adjustment'') OR CHAR_LENGTH(TRIM(COALESCE(reason,''''))) BETWEEN 1 AND 255'),
 ('cash_movements','chk_cash_movement_status','status=''posted''');

SET @p5a_bad = (
 SELECT COUNT(*) FROM _p5a_expected_checks e
 JOIN information_schema.TABLES t ON t.TABLE_SCHEMA=@p5a_schema AND t.TABLE_NAME=e.table_name
 JOIN information_schema.TABLE_CONSTRAINTS tc ON tc.CONSTRAINT_SCHEMA=@p5a_schema AND tc.TABLE_NAME=e.table_name AND tc.CONSTRAINT_NAME=e.constraint_name AND tc.CONSTRAINT_TYPE='CHECK'
 JOIN information_schema.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME
 WHERE LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(cc.CHECK_CLAUSE),'`',''),' ',''),CHAR(9),''),CHAR(10),''),CHAR(13),'')) NOT IN (
   LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(e.approved_clause),'`',''),' ',''),CHAR(9),''),CHAR(10),''),CHAR(13),'')),
   LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(CONCAT('(',e.approved_clause,')')),'`',''),' ',''),CHAR(9),''),CHAR(10),''),CHAR(13),''))
 )
);
SET @p5a_sql=IF(@p5a_bad=0,'DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5A schema mismatch: incompatible CHECK expression'''); EXECUTE IMMEDIATE @p5a_sql;

SET @p5a_bad = (
 SELECT COUNT(*) FROM _p5a_expected_tables e
 JOIN information_schema.TABLES t ON t.TABLE_SCHEMA=@p5a_schema AND t.TABLE_NAME=e.table_name
 WHERE UPPER(COALESCE(t.ENGINE,''))<>UPPER(e.expected_engine)
    OR LOWER(COALESCE(t.TABLE_COLLATION,''))<>LOWER(e.expected_collation)
);
SET @p5a_sql=IF(@p5a_bad=0,'DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5A schema mismatch: incompatible table engine or collation'''); EXECUTE IMMEDIATE @p5a_sql;

-- Data preflight. Ambiguous finance history is never silently repaired.
SET @p5a_sql = (SELECT CASE WHEN EXISTS(
    SELECT 1 FROM cash_registers WHERE status='open' GROUP BY agency_id HAVING COUNT(*)>1
) THEN 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5A preflight: multiple open cash registers for one agency''' ELSE 'DO 0' END);
EXECUTE IMMEDIATE @p5a_sql;

SET @p5a_sql = (SELECT CASE WHEN EXISTS(
    SELECT 1 FROM invoices WHERE invoice_type='invoice' AND reservation_id IS NOT NULL AND status NOT IN('cancelled','credited') GROUP BY reservation_id HAVING COUNT(*)>1
) THEN 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5A preflight: duplicate active invoices for one reservation''' ELSE 'DO 0' END);
EXECUTE IMMEDIATE @p5a_sql;

SET @p5a_sql = (SELECT CASE WHEN EXISTS(
    SELECT 1 FROM invoices i LEFT JOIN reservations r ON r.id=i.reservation_id LEFT JOIN customers c ON c.id=i.customer_id
    WHERE (i.reservation_id IS NOT NULL AND (r.id IS NULL OR r.agency_id<>i.agency_id)) OR c.id IS NULL OR c.agency_id<>i.agency_id
) OR EXISTS(
    SELECT 1 FROM payments p LEFT JOIN reservations r ON r.id=p.reservation_id LEFT JOIN invoices i ON i.id=p.invoice_id
    WHERE (p.reservation_id IS NOT NULL AND (r.id IS NULL OR r.agency_id<>p.agency_id)) OR (p.invoice_id IS NOT NULL AND (i.id IS NULL OR i.agency_id<>p.agency_id))
) OR EXISTS(
    SELECT 1 FROM deposits d LEFT JOIN reservations r ON r.id=d.reservation_id WHERE r.id IS NULL
) OR EXISTS(
    SELECT 1 FROM expenses e LEFT JOIN vehicles v ON v.id=e.vehicle_id WHERE e.vehicle_id IS NOT NULL AND (v.id IS NULL OR v.agency_id<>e.agency_id)
) THEN 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5A preflight: invalid cross-agency finance link''' ELSE 'DO 0' END);
EXECUTE IMMEDIATE @p5a_sql;

SET @p5a_sql = (SELECT CASE WHEN EXISTS(
    SELECT 1 FROM payments WHERE amount<=0
) OR EXISTS(
    SELECT 1 FROM deposits WHERE amount<0 OR retained_amount<0 OR retained_amount>amount
) OR EXISTS(
    SELECT 1 FROM invoices WHERE subtotal<0 OR tax_amount<0 OR total_amount<0 OR paid_amount<0
) OR EXISTS(
    SELECT 1 FROM expenses WHERE amount<=0
) OR EXISTS(
    SELECT 1 FROM cash_registers WHERE opening_balance<0
) THEN 'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5A preflight: impossible monetary relationship''' ELSE 'DO 0' END);
EXECUTE IMMEDIATE @p5a_sql;

-- Existing aggregate cutover columns. ADD IF NOT EXISTS repairs interrupted DDL;
-- exact post-assertions below reject incompatible same-named definitions.
ALTER TABLE reservations
    ADD COLUMN IF NOT EXISTS legacy_finance_paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER advance_amount,
    ADD COLUMN IF NOT EXISTS finance_tracking_started_at DATETIME(6) NULL AFTER legacy_finance_paid_amount,
    ADD UNIQUE KEY IF NOT EXISTS uq_reservations_id_agency (id,agency_id);

ALTER TABLE payments
    ADD COLUMN IF NOT EXISTS is_legacy_opening TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN IF NOT EXISTS proof_original_name VARCHAR(255) NULL AFTER proof_path,
    ADD COLUMN IF NOT EXISTS proof_mime_type VARCHAR(100) NULL AFTER proof_original_name,
    ADD COLUMN IF NOT EXISTS proof_file_size BIGINT UNSIGNED NULL AFTER proof_mime_type,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) AFTER created_at,
    ADD UNIQUE KEY IF NOT EXISTS uq_payments_id_agency (id,agency_id),
    ADD KEY IF NOT EXISTS idx_payments_reservation_settlement (agency_id,reservation_id,is_legacy_opening,status,created_at,id),
    ADD KEY IF NOT EXISTS idx_payments_invoice_settlement (agency_id,invoice_id,status,created_at,id);

ALTER TABLE invoices
    MODIFY invoice_number VARCHAR(50) NULL,
    MODIFY issued_at DATETIME(6) NULL,
    MODIFY created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    MODIFY updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    ADD COLUMN IF NOT EXISTS language_code CHAR(2) NOT NULL DEFAULT 'en' AFTER invoice_type,
    ADD COLUMN IF NOT EXISTS opening_paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER paid_amount,
    ADD COLUMN IF NOT EXISTS original_invoice_id BIGINT UNSIGNED NULL AFTER contract_id,
    ADD COLUMN IF NOT EXISTS credit_reason VARCHAR(255) NULL AFTER cancellation_reason,
    ADD COLUMN IF NOT EXISTS cancelled_by BIGINT UNSIGNED NULL AFTER cancelled_at,
    ADD COLUMN IF NOT EXISTS active_reservation_id BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE WHEN invoice_type='invoice' AND reservation_id IS NOT NULL AND status NOT IN('cancelled','credited') THEN reservation_id ELSE NULL END
    ) PERSISTENT,
    ADD UNIQUE KEY IF NOT EXISTS uq_invoices_id_agency (id,agency_id),
    ADD UNIQUE KEY IF NOT EXISTS uq_invoices_active_reservation (active_reservation_id),
    ADD KEY IF NOT EXISTS idx_invoices_original (agency_id,original_invoice_id,status,id),
    ADD KEY IF NOT EXISTS idx_invoices_type_status_date (agency_id,invoice_type,status,issued_at,id);

ALTER TABLE deposits
    ADD COLUMN IF NOT EXISTS agency_id BIGINT UNSIGNED NULL AFTER id,
    ADD COLUMN IF NOT EXISTS received_amount DECIMAL(12,2) NULL AFTER amount,
    ADD COLUMN IF NOT EXISTS returned_amount DECIMAL(12,2) NULL AFTER retained_amount,
    ADD COLUMN IF NOT EXISTS legacy_opening_received_amount DECIMAL(12,2) NULL AFTER returned_amount,
    ADD COLUMN IF NOT EXISTS legacy_opening_retained_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER legacy_opening_received_amount,
    ADD COLUMN IF NOT EXISTS legacy_opening_returned_amount DECIMAL(12,2) NULL AFTER legacy_opening_retained_amount,
    ADD COLUMN IF NOT EXISTS legacy_opening_status VARCHAR(30) NULL AFTER legacy_opening_returned_amount,
    ADD COLUMN IF NOT EXISTS legacy_opening_resolved_at DATETIME(6) NULL AFTER legacy_opening_status,
    ADD COLUMN IF NOT EXISTS legacy_opening_resolved_by BIGINT UNSIGNED NULL AFTER legacy_opening_resolved_at,
    ADD COLUMN IF NOT EXISTS legacy_opening_resolution_reason VARCHAR(255) NULL AFTER legacy_opening_resolved_by,
    ADD COLUMN IF NOT EXISTS event_tracking_started_at DATETIME(6) NULL AFTER legacy_opening_resolution_reason;

ALTER TABLE expenses
    ADD COLUMN IF NOT EXISTS method VARCHAR(30) NULL AFTER currency,
    ADD COLUMN IF NOT EXISTS expense_type VARCHAR(30) NOT NULL DEFAULT 'expense' AFTER category,
    ADD COLUMN IF NOT EXISTS direction VARCHAR(20) NOT NULL DEFAULT 'outflow' AFTER expense_type,
    ADD COLUMN IF NOT EXISTS original_expense_id BIGINT UNSIGNED NULL AFTER contract_id,
    ADD COLUMN IF NOT EXISTS receipt_original_name VARCHAR(255) NULL AFTER receipt_path,
    ADD COLUMN IF NOT EXISTS receipt_mime_type VARCHAR(100) NULL AFTER receipt_original_name,
    ADD COLUMN IF NOT EXISTS receipt_file_size BIGINT UNSIGNED NULL AFTER receipt_mime_type,
    ADD COLUMN IF NOT EXISTS decided_at DATETIME(6) NULL AFTER approved_by,
    ADD COLUMN IF NOT EXISTS decided_by BIGINT UNSIGNED NULL AFTER decided_at,
    ADD COLUMN IF NOT EXISTS decision_reason VARCHAR(255) NULL AFTER decided_by,
    ADD COLUMN IF NOT EXISTS owner_exception_used TINYINT(1) NOT NULL DEFAULT 0 AFTER decision_reason,
    ADD COLUMN IF NOT EXISTS owner_exception_reason VARCHAR(255) NULL AFTER owner_exception_used,
    ADD UNIQUE KEY IF NOT EXISTS uq_expenses_id_agency (id,agency_id),
    ADD KEY IF NOT EXISTS idx_expenses_original (agency_id,original_expense_id,status,id);

ALTER TABLE cash_registers
    ADD COLUMN IF NOT EXISTS currency CHAR(3) NULL AFTER business_date,
    ADD COLUMN IF NOT EXISTS legacy_net_movement_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER opening_balance,
    ADD COLUMN IF NOT EXISTS movement_tracking_started_at DATETIME(6) NULL AFTER legacy_net_movement_amount,
    ADD COLUMN IF NOT EXISTS closing_boundary_at DATETIME(6) NULL AFTER closed_at,
    ADD COLUMN IF NOT EXISTS difference_reason VARCHAR(255) NULL AFTER difference_amount,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) AFTER notes,
    ADD COLUMN IF NOT EXISTS open_agency_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status='open' THEN agency_id ELSE NULL END) PERSISTENT,
    ADD UNIQUE KEY IF NOT EXISTS uq_cash_registers_id_agency (id,agency_id),
    ADD UNIQUE KEY IF NOT EXISTS uq_cash_register_open_agency (open_agency_id),
    ADD KEY IF NOT EXISTS idx_cash_register_agency_status (agency_id,status,business_date,id);

-- Every composite agency foreign key below must point at an exact UNIQUE
-- (id,agency_id) candidate key from the authoritative parent schema.  Do not
-- synthesize a fallback key here: a missing or incompatible parent key means
-- this migration is unsafe and must fail closed before deterministic finance
-- cutover updates or any FK is added.
CREATE TEMPORARY TABLE _p5a_expected_fk_parent_keys (
    table_name VARCHAR(64) NOT NULL,
    index_name VARCHAR(64) NOT NULL,
    ordered_columns VARCHAR(500) NOT NULL,
    index_type VARCHAR(32) NOT NULL,
    PRIMARY KEY(table_name,index_name)
) ENGINE=InnoDB;
INSERT INTO _p5a_expected_fk_parent_keys VALUES
 ('customers','uq_customers_id_agency','id,agency_id','BTREE'),
 ('vehicles','uq_vehicles_id_agency','id,agency_id','BTREE'),
 ('reservations','uq_reservations_id_agency','id,agency_id','BTREE'),
 ('invoices','uq_invoices_id_agency','id,agency_id','BTREE');
SET @p5a_bad = (
    SELECT COUNT(*)
    FROM _p5a_expected_fk_parent_keys e
    LEFT JOIN (
        SELECT TABLE_NAME,INDEX_NAME,MAX(NON_UNIQUE) non_unique,MAX(INDEX_TYPE) index_type,
               GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') ordered_columns
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=@p5a_schema
        GROUP BY TABLE_NAME,INDEX_NAME
    ) a ON a.TABLE_NAME=e.table_name AND a.INDEX_NAME=e.index_name
    WHERE a.INDEX_NAME IS NULL OR a.non_unique<>0 OR UPPER(a.index_type)<>UPPER(e.index_type) OR a.ordered_columns<>e.ordered_columns
);
SET @p5a_sql=IF(@p5a_bad=0,'DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5A preflight: required composite agency candidate key missing or incompatible'''); EXECUTE IMMEDIATE @p5a_sql;

-- Deterministic cutover snapshots. The migration must run under a finance-write freeze.
UPDATE reservations
SET legacy_finance_paid_amount=advance_amount,
    finance_tracking_started_at=COALESCE(finance_tracking_started_at,CURRENT_TIMESTAMP(6))
WHERE finance_tracking_started_at IS NULL;

UPDATE payments p JOIN reservations r ON r.id=p.reservation_id
SET p.is_legacy_opening=1
WHERE r.finance_tracking_started_at IS NOT NULL AND p.created_at<=r.finance_tracking_started_at;

UPDATE invoices SET opening_paid_amount=paid_amount WHERE opening_paid_amount=0.00 AND paid_amount<>0.00;

UPDATE deposits d JOIN reservations r ON r.id=d.reservation_id
SET d.agency_id=r.agency_id,
    d.legacy_opening_status=COALESCE(d.legacy_opening_status,d.status),
    d.legacy_opening_received_amount=CASE
        WHEN d.event_tracking_started_at IS NOT NULL THEN d.legacy_opening_received_amount
        WHEN d.status='requested' THEN 0.00
        WHEN d.status IN('received','held','partially_retained','returned') THEN d.amount
        ELSE NULL END,
    d.legacy_opening_retained_amount=CASE WHEN d.event_tracking_started_at IS NULL THEN d.retained_amount ELSE d.legacy_opening_retained_amount END,
    d.legacy_opening_returned_amount=CASE
        WHEN d.event_tracking_started_at IS NOT NULL THEN d.legacy_opening_returned_amount
        WHEN d.status IN('requested','received','held','partially_retained') THEN 0.00
        WHEN d.status='returned' THEN d.amount-d.retained_amount
        ELSE NULL END,
    d.received_amount=CASE
        WHEN d.event_tracking_started_at IS NOT NULL THEN d.received_amount
        WHEN d.status='requested' THEN 0.00
        WHEN d.status IN('received','held','partially_retained','returned') THEN d.amount
        ELSE NULL END,
    d.returned_amount=CASE
        WHEN d.event_tracking_started_at IS NOT NULL THEN d.returned_amount
        WHEN d.status IN('requested','received','held','partially_retained') THEN 0.00
        WHEN d.status='returned' THEN d.amount-d.retained_amount
        ELSE NULL END,
    d.event_tracking_started_at=COALESCE(d.event_tracking_started_at,CURRENT_TIMESTAMP(6));

ALTER TABLE deposits
    MODIFY agency_id BIGINT UNSIGNED NOT NULL,
    MODIFY updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    ADD UNIQUE KEY IF NOT EXISTS uq_deposits_id_agency (id,agency_id),
    ADD KEY IF NOT EXISTS idx_deposits_agency_reservation_status (agency_id,reservation_id,status,id);

UPDATE cash_registers cr JOIN agencies a ON a.id=cr.agency_id
SET cr.currency=a.currency,
    cr.legacy_net_movement_amount=CASE WHEN cr.status='open' THEN (
        SELECT COALESCE(SUM(p.amount),0.00) FROM payments p
        WHERE p.agency_id=cr.agency_id AND p.method='cash' AND p.status='paid' AND DATE(p.paid_at)=cr.business_date
    ) ELSE cr.legacy_net_movement_amount END,
    cr.movement_tracking_started_at=COALESCE(cr.movement_tracking_started_at,CURRENT_TIMESTAMP(6));
ALTER TABLE cash_registers MODIFY currency CHAR(3) NOT NULL;

CREATE TABLE IF NOT EXISTS financial_number_allocations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agency_id BIGINT UNSIGNED NOT NULL,
    number_type VARCHAR(30) NOT NULL,
    allocated_number VARCHAR(50) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'reserved',
    entity_type VARCHAR(40) NULL,
    entity_id BIGINT UNSIGNED NULL,
    allocated_by BIGINT UNSIGNED NULL,
    allocated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    consumed_at DATETIME(6) NULL,
    voided_at DATETIME(6) NULL,
    void_reason VARCHAR(255) NULL,
    CONSTRAINT fk_finance_number_agency FOREIGN KEY(agency_id) REFERENCES agencies(id),
    CONSTRAINT fk_finance_number_user FOREIGN KEY(allocated_by) REFERENCES users(id),
    CONSTRAINT chk_finance_number_type CHECK(number_type IN('payment','payment_adjustment','invoice','credit_note','deposit_event','cash_movement')),
    CONSTRAINT chk_finance_number_status CHECK(status IN('reserved','consumed','voided')),
    UNIQUE KEY uq_finance_number(allocated_number),
    KEY idx_finance_number_state(agency_id,number_type,status,allocated_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_idempotency_keys (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agency_id BIGINT UNSIGNED NOT NULL,
    operation_type VARCHAR(50) NOT NULL,
    key_hash CHAR(64) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'in_progress',
    result_entity_type VARCHAR(40) NULL,
    result_entity_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    completed_at DATETIME(6) NULL,
    CONSTRAINT fk_finance_idempotency_agency FOREIGN KEY(agency_id) REFERENCES agencies(id),
    CONSTRAINT fk_finance_idempotency_user FOREIGN KEY(created_by) REFERENCES users(id),
    CONSTRAINT chk_finance_idempotency_status CHECK(status IN('in_progress','completed')),
    UNIQUE KEY uq_finance_idempotency(agency_id,key_hash),
    KEY idx_finance_idempotency_actor(created_by,created_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_adjustments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agency_id BIGINT UNSIGNED NOT NULL,
    payment_id BIGINT UNSIGNED NOT NULL,
    destination_deposit_id BIGINT UNSIGNED NULL,
    adjustment_number VARCHAR(50) NOT NULL,
    adjustment_type VARCHAR(30) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL,
    method VARCHAR(30) NULL,
    reference VARCHAR(100) NULL,
    reason VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'posted',
    occurred_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    excess_payment_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN adjustment_type='excess_reallocation' THEN payment_id ELSE NULL END) PERSISTENT,
    CONSTRAINT fk_payment_adjustment_agency FOREIGN KEY(agency_id) REFERENCES agencies(id),
    CONSTRAINT fk_payment_adjustment_payment FOREIGN KEY(payment_id,agency_id) REFERENCES payments(id,agency_id),
    CONSTRAINT fk_payment_adjustment_deposit FOREIGN KEY(destination_deposit_id,agency_id) REFERENCES deposits(id,agency_id),
    CONSTRAINT fk_payment_adjustment_user FOREIGN KEY(created_by) REFERENCES users(id),
    CONSTRAINT chk_payment_adjustment_type CHECK(adjustment_type IN('full_reversal','partial_refund','full_refund','excess_reallocation')),
    CONSTRAINT chk_payment_adjustment_amount CHECK(amount>0),
    CONSTRAINT chk_payment_adjustment_method CHECK((adjustment_type='excess_reallocation' AND method IS NULL AND destination_deposit_id IS NOT NULL) OR (adjustment_type<>'excess_reallocation' AND method IN('cash','card','bank_transfer','cheque','online','other'))),
    CONSTRAINT chk_payment_adjustment_reason CHECK(CHAR_LENGTH(TRIM(reason)) BETWEEN 1 AND 255),
    CONSTRAINT chk_payment_adjustment_status CHECK(status='posted'),
    UNIQUE KEY uq_payment_adjustment_number(adjustment_number),
    UNIQUE KEY uq_payment_adjustment_excess(excess_payment_id),
    KEY idx_payment_adjustment_payment(agency_id,payment_id,occurred_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS deposit_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agency_id BIGINT UNSIGNED NOT NULL,
    deposit_id BIGINT UNSIGNED NOT NULL,
    event_number VARCHAR(50) NOT NULL,
    event_type VARCHAR(30) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL,
    method VARCHAR(30) NULL,
    payment_id BIGINT UNSIGNED NULL,
    reason VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'posted',
    occurred_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    requested_deposit_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN event_type='requested' THEN deposit_id ELSE NULL END) PERSISTENT,
    CONSTRAINT fk_deposit_event_agency FOREIGN KEY(agency_id) REFERENCES agencies(id),
    CONSTRAINT fk_deposit_event_deposit FOREIGN KEY(deposit_id,agency_id) REFERENCES deposits(id,agency_id),
    CONSTRAINT fk_deposit_event_payment FOREIGN KEY(payment_id,agency_id) REFERENCES payments(id,agency_id),
    CONSTRAINT fk_deposit_event_user FOREIGN KEY(created_by) REFERENCES users(id),
    CONSTRAINT chk_deposit_event_type CHECK(event_type IN('requested','received','held','partially_retained','fully_retained','partially_returned','returned')),
    CONSTRAINT chk_deposit_event_amount CHECK((event_type='held' AND amount=0) OR (event_type<>'held' AND amount>0)),
    CONSTRAINT chk_deposit_event_method CHECK((event_type IN('received','partially_returned','returned') AND method IN('cash','card','bank_transfer','cheque','online','other')) OR (event_type NOT IN('received','partially_returned','returned') AND method IS NULL)),
    CONSTRAINT chk_deposit_event_reason CHECK(event_type NOT IN('partially_retained','fully_retained') OR CHAR_LENGTH(TRIM(COALESCE(reason,''))) BETWEEN 1 AND 255),
    CONSTRAINT chk_deposit_event_status CHECK(status='posted'),
    UNIQUE KEY uq_deposit_event_number(event_number),
    UNIQUE KEY uq_deposit_requested(requested_deposit_id),
    KEY idx_deposit_event_deposit(agency_id,deposit_id,occurred_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cash_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agency_id BIGINT UNSIGNED NOT NULL,
    cash_register_id BIGINT UNSIGNED NOT NULL,
    movement_number VARCHAR(50) NOT NULL,
    movement_type VARCHAR(30) NOT NULL,
    direction VARCHAR(10) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL,
    source_entity_type VARCHAR(40) NOT NULL,
    source_entity_id BIGINT UNSIGNED NOT NULL,
    reason VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'posted',
    occurred_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    source_key VARCHAR(190) GENERATED ALWAYS AS (CONCAT(movement_type,'|',source_entity_type,'|',source_entity_id)) PERSISTENT,
    CONSTRAINT fk_cash_movement_agency FOREIGN KEY(agency_id) REFERENCES agencies(id),
    CONSTRAINT fk_cash_movement_register FOREIGN KEY(cash_register_id,agency_id) REFERENCES cash_registers(id,agency_id),
    CONSTRAINT fk_cash_movement_user FOREIGN KEY(created_by) REFERENCES users(id),
    CONSTRAINT chk_cash_movement_type CHECK(movement_type IN('payment_in','refund_out','deposit_in','deposit_return_out','expense_out','manual_in','manual_out','closing_adjustment')),
    CONSTRAINT chk_cash_movement_direction CHECK((movement_type IN('payment_in','deposit_in','manual_in') AND direction='in') OR (movement_type IN('refund_out','deposit_return_out','expense_out','manual_out') AND direction='out') OR (movement_type='closing_adjustment' AND direction IN('in','out'))),
    CONSTRAINT chk_cash_movement_amount CHECK(amount>0),
    CONSTRAINT chk_cash_movement_reason CHECK(movement_type NOT IN('manual_in','manual_out','closing_adjustment') OR CHAR_LENGTH(TRIM(COALESCE(reason,''))) BETWEEN 1 AND 255),
    CONSTRAINT chk_cash_movement_status CHECK(status='posted'),
    UNIQUE KEY uq_cash_movement_number(movement_number),
    UNIQUE KEY uq_cash_movement_source(agency_id,source_key),
    KEY idx_cash_movement_register(cash_register_id,occurred_at,id),
    KEY idx_cash_movement_agency_type(agency_id,movement_type,occurred_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A table can survive an interrupted CREATE/ALTER with a compatible column
-- set but a missing secondary index.  Those indexes are additive and safe to
-- recreate; a missing PRIMARY key is rejected by the preflight above.
ALTER TABLE financial_number_allocations
    ADD UNIQUE KEY IF NOT EXISTS uq_finance_number (allocated_number),
    ADD KEY IF NOT EXISTS idx_finance_number_state (agency_id,number_type,status,allocated_at,id);
ALTER TABLE finance_idempotency_keys
    ADD UNIQUE KEY IF NOT EXISTS uq_finance_idempotency (agency_id,key_hash),
    ADD KEY IF NOT EXISTS idx_finance_idempotency_actor (created_by,created_at,id);
ALTER TABLE payment_adjustments
    ADD UNIQUE KEY IF NOT EXISTS uq_payment_adjustment_number (adjustment_number),
    ADD UNIQUE KEY IF NOT EXISTS uq_payment_adjustment_excess (excess_payment_id),
    ADD KEY IF NOT EXISTS idx_payment_adjustment_payment (agency_id,payment_id,occurred_at,id);
ALTER TABLE deposit_events
    ADD UNIQUE KEY IF NOT EXISTS uq_deposit_event_number (event_number),
    ADD UNIQUE KEY IF NOT EXISTS uq_deposit_requested (requested_deposit_id),
    ADD KEY IF NOT EXISTS idx_deposit_event_deposit (agency_id,deposit_id,occurred_at,id);
ALTER TABLE cash_movements
    ADD UNIQUE KEY IF NOT EXISTS uq_cash_movement_number (movement_number),
    ADD UNIQUE KEY IF NOT EXISTS uq_cash_movement_source (agency_id,source_key),
    ADD KEY IF NOT EXISTS idx_cash_movement_register (cash_register_id,occurred_at,id),
    ADD KEY IF NOT EXISTS idx_cash_movement_agency_type (agency_id,movement_type,occurred_at,id);

-- Existing-table foreign keys. Exact conflicts fail instead of being dropped.
SET @p5a_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='invoices' AND CONSTRAINT_NAME='fk_invoices_customer_agency'),
    'DO 0','ALTER TABLE invoices ADD CONSTRAINT fk_invoices_customer_agency FOREIGN KEY(customer_id,agency_id) REFERENCES customers(id,agency_id) ON UPDATE RESTRICT ON DELETE RESTRICT'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='invoices' AND CONSTRAINT_NAME='fk_invoices_reservation_agency'),
    'DO 0','ALTER TABLE invoices ADD CONSTRAINT fk_invoices_reservation_agency FOREIGN KEY(reservation_id,agency_id) REFERENCES reservations(id,agency_id) ON UPDATE RESTRICT ON DELETE RESTRICT'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='payments' AND CONSTRAINT_NAME='fk_payments_reservation_agency'),
    'DO 0','ALTER TABLE payments ADD CONSTRAINT fk_payments_reservation_agency FOREIGN KEY(reservation_id,agency_id) REFERENCES reservations(id,agency_id) ON UPDATE RESTRICT ON DELETE RESTRICT'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='payments' AND CONSTRAINT_NAME='fk_payments_invoice_agency'),
    'DO 0','ALTER TABLE payments ADD CONSTRAINT fk_payments_invoice_agency FOREIGN KEY(invoice_id,agency_id) REFERENCES invoices(id,agency_id) ON UPDATE RESTRICT ON DELETE RESTRICT'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='expenses' AND CONSTRAINT_NAME='fk_expenses_vehicle_agency'),
    'DO 0','ALTER TABLE expenses ADD CONSTRAINT fk_expenses_vehicle_agency FOREIGN KEY(vehicle_id,agency_id) REFERENCES vehicles(id,agency_id) ON UPDATE RESTRICT ON DELETE RESTRICT'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='invoices' AND CONSTRAINT_NAME='fk_invoices_original_agency'),
    'DO 0','ALTER TABLE invoices ADD CONSTRAINT fk_invoices_original_agency FOREIGN KEY(original_invoice_id,agency_id) REFERENCES invoices(id,agency_id) ON UPDATE RESTRICT ON DELETE RESTRICT'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='expenses' AND CONSTRAINT_NAME='fk_expenses_original_agency'),
    'DO 0','ALTER TABLE expenses ADD CONSTRAINT fk_expenses_original_agency FOREIGN KEY(original_expense_id,agency_id) REFERENCES expenses(id,agency_id) ON UPDATE RESTRICT ON DELETE RESTRICT'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='deposits' AND CONSTRAINT_NAME='fk_deposits_agency'),
    'DO 0','ALTER TABLE deposits ADD CONSTRAINT fk_deposits_agency FOREIGN KEY(agency_id) REFERENCES agencies(id) ON UPDATE RESTRICT ON DELETE RESTRICT'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='deposits' AND CONSTRAINT_NAME='fk_deposits_reservation_agency'),
    'DO 0','ALTER TABLE deposits ADD CONSTRAINT fk_deposits_reservation_agency FOREIGN KEY(reservation_id,agency_id) REFERENCES reservations(id,agency_id) ON UPDATE RESTRICT ON DELETE RESTRICT'); EXECUTE IMMEDIATE @p5a_sql;

-- Foreign keys on a compatible existing finance table are additive.  A
-- same-named definition was checked above and therefore cannot be silently
-- replaced here.
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='financial_number_allocations' AND CONSTRAINT_NAME='fk_finance_number_agency'), 'ALTER TABLE financial_number_allocations ADD CONSTRAINT fk_finance_number_agency FOREIGN KEY(agency_id) REFERENCES agencies(id) ON UPDATE RESTRICT ON DELETE RESTRICT','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='financial_number_allocations' AND CONSTRAINT_NAME='fk_finance_number_user'), 'ALTER TABLE financial_number_allocations ADD CONSTRAINT fk_finance_number_user FOREIGN KEY(allocated_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='finance_idempotency_keys' AND CONSTRAINT_NAME='fk_finance_idempotency_agency'), 'ALTER TABLE finance_idempotency_keys ADD CONSTRAINT fk_finance_idempotency_agency FOREIGN KEY(agency_id) REFERENCES agencies(id) ON UPDATE RESTRICT ON DELETE RESTRICT','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='finance_idempotency_keys' AND CONSTRAINT_NAME='fk_finance_idempotency_user'), 'ALTER TABLE finance_idempotency_keys ADD CONSTRAINT fk_finance_idempotency_user FOREIGN KEY(created_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='payment_adjustments' AND CONSTRAINT_NAME='fk_payment_adjustment_agency'), 'ALTER TABLE payment_adjustments ADD CONSTRAINT fk_payment_adjustment_agency FOREIGN KEY(agency_id) REFERENCES agencies(id) ON UPDATE RESTRICT ON DELETE RESTRICT','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='payment_adjustments' AND CONSTRAINT_NAME='fk_payment_adjustment_payment'), 'ALTER TABLE payment_adjustments ADD CONSTRAINT fk_payment_adjustment_payment FOREIGN KEY(payment_id,agency_id) REFERENCES payments(id,agency_id) ON UPDATE RESTRICT ON DELETE RESTRICT','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='payment_adjustments' AND CONSTRAINT_NAME='fk_payment_adjustment_deposit'), 'ALTER TABLE payment_adjustments ADD CONSTRAINT fk_payment_adjustment_deposit FOREIGN KEY(destination_deposit_id,agency_id) REFERENCES deposits(id,agency_id) ON UPDATE RESTRICT ON DELETE RESTRICT','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='payment_adjustments' AND CONSTRAINT_NAME='fk_payment_adjustment_user'), 'ALTER TABLE payment_adjustments ADD CONSTRAINT fk_payment_adjustment_user FOREIGN KEY(created_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='deposit_events' AND CONSTRAINT_NAME='fk_deposit_event_agency'), 'ALTER TABLE deposit_events ADD CONSTRAINT fk_deposit_event_agency FOREIGN KEY(agency_id) REFERENCES agencies(id) ON UPDATE RESTRICT ON DELETE RESTRICT','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='deposit_events' AND CONSTRAINT_NAME='fk_deposit_event_deposit'), 'ALTER TABLE deposit_events ADD CONSTRAINT fk_deposit_event_deposit FOREIGN KEY(deposit_id,agency_id) REFERENCES deposits(id,agency_id) ON UPDATE RESTRICT ON DELETE RESTRICT','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='deposit_events' AND CONSTRAINT_NAME='fk_deposit_event_payment'), 'ALTER TABLE deposit_events ADD CONSTRAINT fk_deposit_event_payment FOREIGN KEY(payment_id,agency_id) REFERENCES payments(id,agency_id) ON UPDATE RESTRICT ON DELETE RESTRICT','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='deposit_events' AND CONSTRAINT_NAME='fk_deposit_event_user'), 'ALTER TABLE deposit_events ADD CONSTRAINT fk_deposit_event_user FOREIGN KEY(created_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='cash_movements' AND CONSTRAINT_NAME='fk_cash_movement_agency'), 'ALTER TABLE cash_movements ADD CONSTRAINT fk_cash_movement_agency FOREIGN KEY(agency_id) REFERENCES agencies(id) ON UPDATE RESTRICT ON DELETE RESTRICT','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='cash_movements' AND CONSTRAINT_NAME='fk_cash_movement_register'), 'ALTER TABLE cash_movements ADD CONSTRAINT fk_cash_movement_register FOREIGN KEY(cash_register_id,agency_id) REFERENCES cash_registers(id,agency_id) ON UPDATE RESTRICT ON DELETE RESTRICT','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='cash_movements' AND CONSTRAINT_NAME='fk_cash_movement_user'), 'ALTER TABLE cash_movements ADD CONSTRAINT fk_cash_movement_user FOREIGN KEY(created_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT','DO 0'); EXECUTE IMMEDIATE @p5a_sql;

-- Post-DDL assertion: every expected FK must now be present with the exact
-- ordered relationship and restrictive action rules. This closes the gap
-- between additive DDL and the authoritative information_schema definition.
SET @p5a_bad = (
 SELECT COUNT(*)
 FROM _p5a_expected_fks e
 JOIN information_schema.TABLES t ON t.TABLE_SCHEMA=@p5a_schema AND t.TABLE_NAME=e.table_name
 LEFT JOIN (
   SELECT k.TABLE_NAME,k.CONSTRAINT_NAME,
          MAX(tc.CONSTRAINT_TYPE) constraint_type,
          GROUP_CONCAT(k.COLUMN_NAME ORDER BY k.ORDINAL_POSITION SEPARATOR ',') local_columns,
          MAX(k.REFERENCED_TABLE_SCHEMA) referenced_schema,MAX(k.REFERENCED_TABLE_NAME) referenced_table,
          GROUP_CONCAT(k.REFERENCED_COLUMN_NAME ORDER BY k.ORDINAL_POSITION SEPARATOR ',') referenced_columns,
          MAX(r.UPDATE_RULE) update_rule,MAX(r.DELETE_RULE) delete_rule
   FROM information_schema.KEY_COLUMN_USAGE k
   JOIN information_schema.TABLE_CONSTRAINTS tc ON tc.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND tc.TABLE_NAME=k.TABLE_NAME AND tc.CONSTRAINT_NAME=k.CONSTRAINT_NAME
   JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
   WHERE k.CONSTRAINT_SCHEMA=@p5a_schema AND k.REFERENCED_TABLE_NAME IS NOT NULL
   GROUP BY k.TABLE_NAME,k.CONSTRAINT_NAME
 ) a ON a.TABLE_NAME=e.table_name AND a.CONSTRAINT_NAME=e.constraint_name
 WHERE a.CONSTRAINT_NAME IS NULL OR a.constraint_type<>'FOREIGN KEY'
    OR a.local_columns<>e.local_columns OR a.referenced_schema<>e.referenced_schema OR a.referenced_table<>e.referenced_table OR a.referenced_columns<>e.referenced_columns
    OR UPPER(a.update_rule) NOT IN ('RESTRICT','NO ACTION') OR UPPER(a.delete_rule) NOT IN ('RESTRICT','NO ACTION')
);
SET @p5a_sql=IF(@p5a_bad=0,'DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5A schema mismatch: expected foreign-key definition absent or incompatible'''); EXECUTE IMMEDIATE @p5a_sql;

-- CHECK constraints are also additive when absent.  Existing same-named
-- expressions were compared conservatively above and therefore are never
-- rewritten.
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='financial_number_allocations' AND CONSTRAINT_NAME='chk_finance_number_type'), 'ALTER TABLE financial_number_allocations ADD CONSTRAINT chk_finance_number_type CHECK(number_type IN(''payment'',''payment_adjustment'',''invoice'',''credit_note'',''deposit_event'',''cash_movement''))','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='financial_number_allocations' AND CONSTRAINT_NAME='chk_finance_number_status'), 'ALTER TABLE financial_number_allocations ADD CONSTRAINT chk_finance_number_status CHECK(status IN(''reserved'',''consumed'',''voided''))','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='finance_idempotency_keys' AND CONSTRAINT_NAME='chk_finance_idempotency_status'), 'ALTER TABLE finance_idempotency_keys ADD CONSTRAINT chk_finance_idempotency_status CHECK(status IN(''in_progress'',''completed''))','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='payment_adjustments' AND CONSTRAINT_NAME='chk_payment_adjustment_type'), 'ALTER TABLE payment_adjustments ADD CONSTRAINT chk_payment_adjustment_type CHECK(adjustment_type IN(''full_reversal'',''partial_refund'',''full_refund'',''excess_reallocation''))','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='payment_adjustments' AND CONSTRAINT_NAME='chk_payment_adjustment_amount'), 'ALTER TABLE payment_adjustments ADD CONSTRAINT chk_payment_adjustment_amount CHECK(amount>0)','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='payment_adjustments' AND CONSTRAINT_NAME='chk_payment_adjustment_method'), 'ALTER TABLE payment_adjustments ADD CONSTRAINT chk_payment_adjustment_method CHECK((adjustment_type=''excess_reallocation'' AND method IS NULL AND destination_deposit_id IS NOT NULL) OR (adjustment_type<>''excess_reallocation'' AND method IN(''cash'',''card'',''bank_transfer'',''cheque'',''online'',''other'')))','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='payment_adjustments' AND CONSTRAINT_NAME='chk_payment_adjustment_reason'), 'ALTER TABLE payment_adjustments ADD CONSTRAINT chk_payment_adjustment_reason CHECK(CHAR_LENGTH(TRIM(reason)) BETWEEN 1 AND 255)','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='payment_adjustments' AND CONSTRAINT_NAME='chk_payment_adjustment_status'), 'ALTER TABLE payment_adjustments ADD CONSTRAINT chk_payment_adjustment_status CHECK(status=''posted'')','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='deposit_events' AND CONSTRAINT_NAME='chk_deposit_event_type'), 'ALTER TABLE deposit_events ADD CONSTRAINT chk_deposit_event_type CHECK(event_type IN(''requested'',''received'',''held'',''partially_retained'',''fully_retained'',''partially_returned'',''returned''))','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='deposit_events' AND CONSTRAINT_NAME='chk_deposit_event_amount'), 'ALTER TABLE deposit_events ADD CONSTRAINT chk_deposit_event_amount CHECK((event_type=''held'' AND amount=0) OR (event_type<>''held'' AND amount>0))','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='deposit_events' AND CONSTRAINT_NAME='chk_deposit_event_method'), 'ALTER TABLE deposit_events ADD CONSTRAINT chk_deposit_event_method CHECK((event_type IN(''received'',''partially_returned'',''returned'') AND method IN(''cash'',''card'',''bank_transfer'',''cheque'',''online'',''other'')) OR (event_type NOT IN(''received'',''partially_returned'',''returned'') AND method IS NULL))','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='deposit_events' AND CONSTRAINT_NAME='chk_deposit_event_reason'), 'ALTER TABLE deposit_events ADD CONSTRAINT chk_deposit_event_reason CHECK(event_type NOT IN(''partially_retained'',''fully_retained'') OR CHAR_LENGTH(TRIM(COALESCE(reason,''''))) BETWEEN 1 AND 255)','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='deposit_events' AND CONSTRAINT_NAME='chk_deposit_event_status'), 'ALTER TABLE deposit_events ADD CONSTRAINT chk_deposit_event_status CHECK(status=''posted'')','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='cash_movements' AND CONSTRAINT_NAME='chk_cash_movement_type'), 'ALTER TABLE cash_movements ADD CONSTRAINT chk_cash_movement_type CHECK(movement_type IN(''payment_in'',''refund_out'',''deposit_in'',''deposit_return_out'',''expense_out'',''manual_in'',''manual_out'',''closing_adjustment''))','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='cash_movements' AND CONSTRAINT_NAME='chk_cash_movement_direction'), 'ALTER TABLE cash_movements ADD CONSTRAINT chk_cash_movement_direction CHECK((movement_type IN(''payment_in'',''deposit_in'',''manual_in'') AND direction=''in'') OR (movement_type IN(''refund_out'',''deposit_return_out'',''expense_out'',''manual_out'') AND direction=''out'') OR (movement_type=''closing_adjustment'' AND direction IN(''in'',''out'')))','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='cash_movements' AND CONSTRAINT_NAME='chk_cash_movement_amount'), 'ALTER TABLE cash_movements ADD CONSTRAINT chk_cash_movement_amount CHECK(amount>0)','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='cash_movements' AND CONSTRAINT_NAME='chk_cash_movement_reason'), 'ALTER TABLE cash_movements ADD CONSTRAINT chk_cash_movement_reason CHECK(movement_type NOT IN(''manual_in'',''manual_out'',''closing_adjustment'') OR CHAR_LENGTH(TRIM(COALESCE(reason,''''))) BETWEEN 1 AND 255)','DO 0'); EXECUTE IMMEDIATE @p5a_sql;
SET @p5a_sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@p5a_schema AND TABLE_NAME='cash_movements' AND CONSTRAINT_NAME='chk_cash_movement_status'), 'ALTER TABLE cash_movements ADD CONSTRAINT chk_cash_movement_status CHECK(status=''posted'')','DO 0'); EXECUTE IMMEDIATE @p5a_sql;

-- Exact structural assertions catch incompatible partial DDL before cutover is recorded.
SET @p5a_bad = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=@p5a_schema AND (
        (TABLE_NAME='reservations' AND COLUMN_NAME='legacy_finance_paid_amount' AND (COLUMN_TYPE<>'decimal(12,2)' OR IS_NULLABLE<>'NO')) OR
        (TABLE_NAME='payments' AND COLUMN_NAME='is_legacy_opening' AND (COLUMN_TYPE<>'tinyint(1)' OR IS_NULLABLE<>'NO')) OR
        (TABLE_NAME='deposits' AND COLUMN_NAME='agency_id' AND (COLUMN_TYPE<>'bigint(20) unsigned' OR IS_NULLABLE<>'NO')) OR
        (TABLE_NAME='cash_registers' AND COLUMN_NAME='currency' AND (COLUMN_TYPE<>'char(3)' OR IS_NULLABLE<>'NO'))
    )
);
SET @p5a_sql=IF(@p5a_bad=0,'DO 0','SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT=''Phase 5A schema mismatch: incompatible finance column'''); EXECUTE IMMEDIATE @p5a_sql;
