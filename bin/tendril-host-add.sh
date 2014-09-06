#!/bin/bash

host="$1"
port="$2"
config="$3"
section="$4"

[ "$host"    ] || exit 1
[ "$port"    ] || exit 1
[ "$config"  ] || exit 1
[ "$section" ] || exit 1

if [ ! -f "$config" ] ; then
  echo "missing $config" 1>&2
  exit 1
fi

user=$(grep "\[$section\]" -A 100 $config | egrep '^user'     | awk '{print $3}')
pass=$(grep "\[$section\]" -A 100 $config | egrep '^password' | awk '{print $3}')

server=$(echo "$host.$port" | sed 's/\./_/g')
federated="mysql://${user}:${pass}@${host}:${port}/information_schema"
federated_mysql="mysql://${user}:${pass}@${host}:${port}/mysql"

cat <<eod

-- INSERT IGNORE for InnoDB without auto-inc holes
insert into servers (host, port)
  select '${host}', '${port}' from sequence a
  left join servers b on b.host = '${host}' and b.port = '${port}'
  where b.host is null and b.port is null limit 1;

update servers set enabled = 1 where host = '${host}' and port = ${port};

drop event if exists ${server}_schema;
drop event if exists ${server}_schema_prep;
drop event if exists ${server}_activity;
drop event if exists ${server}_sampled;
drop event if exists ${server}_status;
drop event if exists ${server}_variables;
drop event if exists ${server}_usage;
drop event if exists ${server}_privileges;
drop event if exists ${server}_replication;

drop table if exists ${server}_schemata;
CREATE TABLE ${server}_schemata (
  CATALOG_NAME varchar(512) NOT NULL DEFAULT '',
  SCHEMA_NAME varchar(64) NOT NULL DEFAULT '',
  DEFAULT_CHARACTER_SET_NAME varchar(32) NOT NULL DEFAULT '',
  DEFAULT_COLLATION_NAME varchar(32) NOT NULL DEFAULT '',
  SQL_PATH varchar(512) DEFAULT NULL
) ENGINE=FEDERATED CONNECTION='${federated}/SCHEMATA' DEFAULT CHARSET=utf8;

drop table if exists ${server}_tables;
CREATE TABLE ${server}_tables (
  TABLE_CATALOG varchar(512) NOT NULL DEFAULT '',
  TABLE_SCHEMA varchar(64) NOT NULL DEFAULT '',
  TABLE_NAME varchar(64) NOT NULL DEFAULT '',
  TABLE_TYPE varchar(64) NOT NULL DEFAULT '',
  ENGINE varchar(64) DEFAULT NULL,
  VERSION bigint(21) unsigned DEFAULT NULL,
  ROW_FORMAT varchar(10) DEFAULT NULL,
  TABLE_ROWS bigint(21) unsigned DEFAULT NULL,
  AVG_ROW_LENGTH bigint(21) unsigned DEFAULT NULL,
  DATA_LENGTH bigint(21) unsigned DEFAULT NULL,
  MAX_DATA_LENGTH bigint(21) unsigned DEFAULT NULL,
  INDEX_LENGTH bigint(21) unsigned DEFAULT NULL,
  DATA_FREE bigint(21) unsigned DEFAULT NULL,
  AUTO_INCREMENT bigint(21) unsigned DEFAULT NULL,
  CREATE_TIME datetime DEFAULT NULL,
  UPDATE_TIME datetime DEFAULT NULL,
  CHECK_TIME datetime DEFAULT NULL,
  TABLE_COLLATION varchar(32) DEFAULT NULL,
  CHECKSUM bigint(21) unsigned DEFAULT NULL,
  CREATE_OPTIONS varchar(255) DEFAULT NULL,
  TABLE_COMMENT varchar(2048) NOT NULL DEFAULT '',
  INDEX (TABLE_SCHEMA, TABLE_NAME)
) ENGINE=FEDERATED CONNECTION='${federated}/TABLES' DEFAULT CHARSET=utf8;

drop table if exists ${server}_tablenames;
CREATE TABLE ${server}_tablenames (
  TABLE_SCHEMA varchar(64) NOT NULL DEFAULT '',
  TABLE_NAME varchar(64) NOT NULL DEFAULT '',
  INDEX (TABLE_SCHEMA, TABLE_NAME)
) ENGINE=FEDERATED CONNECTION='${federated}/TABLES' DEFAULT CHARSET=utf8;

drop table if exists ${server}_partitions;
CREATE TABLE ${server}_partitions (
  TABLE_CATALOG varchar(512) NOT NULL DEFAULT '',
  TABLE_SCHEMA varchar(64) NOT NULL DEFAULT '',
  TABLE_NAME varchar(64) NOT NULL DEFAULT '',
  PARTITION_NAME varchar(64) DEFAULT NULL,
  SUBPARTITION_NAME varchar(64) DEFAULT NULL,
  PARTITION_ORDINAL_POSITION bigint(21) unsigned DEFAULT NULL,
  SUBPARTITION_ORDINAL_POSITION bigint(21) unsigned DEFAULT NULL,
  PARTITION_METHOD varchar(18) DEFAULT NULL,
  SUBPARTITION_METHOD varchar(12) DEFAULT NULL,
  PARTITION_EXPRESSION longtext,
  SUBPARTITION_EXPRESSION longtext,
  PARTITION_DESCRIPTION longtext,
  TABLE_ROWS bigint(21) unsigned NOT NULL DEFAULT '0',
  AVG_ROW_LENGTH bigint(21) unsigned NOT NULL DEFAULT '0',
  DATA_LENGTH bigint(21) unsigned NOT NULL DEFAULT '0',
  MAX_DATA_LENGTH bigint(21) unsigned DEFAULT NULL,
  INDEX_LENGTH bigint(21) unsigned NOT NULL DEFAULT '0',
  DATA_FREE bigint(21) unsigned NOT NULL DEFAULT '0',
  CREATE_TIME datetime DEFAULT NULL,
  UPDATE_TIME datetime DEFAULT NULL,
  CHECK_TIME datetime DEFAULT NULL,
  CHECKSUM bigint(21) unsigned DEFAULT NULL,
  PARTITION_COMMENT varchar(80) NOT NULL DEFAULT '',
  NODEGROUP varchar(12) NOT NULL DEFAULT '',
  TABLESPACE_NAME varchar(64) DEFAULT NULL,
  INDEX (TABLE_SCHEMA, TABLE_NAME, PARTITION_NAME)
) ENGINE=FEDERATED CONNECTION='${federated}/PARTITIONS' DEFAULT CHARSET=utf8;

drop table if exists ${server}_columns;
CREATE TABLE ${server}_columns (
  TABLE_CATALOG varchar(512) NOT NULL DEFAULT '',
  TABLE_SCHEMA varchar(64) NOT NULL DEFAULT '',
  TABLE_NAME varchar(64) NOT NULL DEFAULT '',
  COLUMN_NAME varchar(64) NOT NULL DEFAULT '',
  ORDINAL_POSITION bigint(21) unsigned NOT NULL DEFAULT '0',
  COLUMN_DEFAULT longtext,
  IS_NULLABLE varchar(3) NOT NULL DEFAULT '',
  DATA_TYPE varchar(64) NOT NULL DEFAULT '',
  CHARACTER_MAXIMUM_LENGTH bigint(21) unsigned DEFAULT NULL,
  CHARACTER_OCTET_LENGTH bigint(21) unsigned DEFAULT NULL,
  NUMERIC_PRECISION bigint(21) unsigned DEFAULT NULL,
  NUMERIC_SCALE bigint(21) unsigned DEFAULT NULL,
  DATETIME_PRECISION bigint(21) unsigned DEFAULT NULL,
  CHARACTER_SET_NAME varchar(32) DEFAULT NULL,
  COLLATION_NAME varchar(32) DEFAULT NULL,
  COLUMN_TYPE longtext NOT NULL,
  COLUMN_KEY varchar(3) NOT NULL DEFAULT '',
  EXTRA varchar(27) NOT NULL DEFAULT '',
  PRIVILEGES varchar(80) NOT NULL DEFAULT '',
  COLUMN_COMMENT varchar(1024) NOT NULL DEFAULT '',
  INDEX (TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME)
) ENGINE=FEDERATED CONNECTION='${federated}/COLUMNS' DEFAULT CHARSET=utf8;

drop table if exists ${server}_stats;
CREATE TABLE ${server}_stats (
  TABLE_CATALOG varchar(512) NOT NULL DEFAULT '',
  TABLE_SCHEMA varchar(64) NOT NULL DEFAULT '',
  TABLE_NAME varchar(64) NOT NULL DEFAULT '',
  NON_UNIQUE bigint(1) NOT NULL DEFAULT '0',
  INDEX_SCHEMA varchar(64) NOT NULL DEFAULT '',
  INDEX_NAME varchar(64) NOT NULL DEFAULT '',
  SEQ_IN_INDEX bigint(2) NOT NULL DEFAULT '0',
  COLUMN_NAME varchar(64) NOT NULL DEFAULT '',
  COLLATION varchar(1) DEFAULT NULL,
  CARDINALITY bigint(21) DEFAULT NULL,
  SUB_PART bigint(3) DEFAULT NULL,
  PACKED varchar(10) DEFAULT NULL,
  NULLABLE varchar(3) NOT NULL DEFAULT '',
  INDEX_TYPE varchar(16) NOT NULL DEFAULT '',
  COMMENT varchar(16) DEFAULT NULL,
  INDEX_COMMENT varchar(1024) NOT NULL DEFAULT '',
  INDEX (TABLE_SCHEMA)
) ENGINE=FEDERATED CONNECTION='${federated}/STATISTICS' DEFAULT CHARSET=utf8;

drop table if exists ${server}_triggers;
CREATE TABLE ${server}_triggers (
  TRIGGER_CATALOG varchar(512) NOT NULL DEFAULT '',
  TRIGGER_SCHEMA varchar(64) NOT NULL DEFAULT '',
  TRIGGER_NAME varchar(64) NOT NULL DEFAULT '',
  EVENT_MANIPULATION varchar(6) NOT NULL DEFAULT '',
  EVENT_OBJECT_CATALOG varchar(512) NOT NULL DEFAULT '',
  EVENT_OBJECT_SCHEMA varchar(64) NOT NULL DEFAULT '',
  EVENT_OBJECT_TABLE varchar(64) NOT NULL DEFAULT '',
  ACTION_ORDER bigint(4) NOT NULL DEFAULT '0',
  ACTION_CONDITION longtext,
  ACTION_STATEMENT longtext NOT NULL,
  ACTION_ORIENTATION varchar(9) NOT NULL DEFAULT '',
  ACTION_TIMING varchar(6) NOT NULL DEFAULT '',
  ACTION_REFERENCE_OLD_TABLE varchar(64) DEFAULT NULL,
  ACTION_REFERENCE_NEW_TABLE varchar(64) DEFAULT NULL,
  ACTION_REFERENCE_OLD_ROW varchar(3) NOT NULL DEFAULT '',
  ACTION_REFERENCE_NEW_ROW varchar(3) NOT NULL DEFAULT '',
  CREATED datetime DEFAULT NULL,
  SQL_MODE varchar(8192) NOT NULL DEFAULT '',
  DEFINER varchar(189) NOT NULL DEFAULT '',
  CHARACTER_SET_CLIENT varchar(32) NOT NULL DEFAULT '',
  COLLATION_CONNECTION varchar(32) NOT NULL DEFAULT '',
  DATABASE_COLLATION varchar(32) NOT NULL DEFAULT '',
  INDEX (TRIGGER_SCHEMA, TRIGGER_NAME)
) ENGINE=FEDERATED CONNECTION='${federated}/TRIGGERS' DEFAULT CHARSET=utf8;

drop table if exists ${server}_sch_privs;
CREATE TABLE ${server}_sch_privs (
  GRANTEE varchar(81) NOT NULL DEFAULT '',
  TABLE_CATALOG varchar(512) NOT NULL DEFAULT '',
  TABLE_SCHEMA varchar(64) NOT NULL DEFAULT '',
  PRIVILEGE_TYPE varchar(64) NOT NULL DEFAULT '',
  IS_GRANTABLE varchar(3) NOT NULL DEFAULT ''
) ENGINE=FEDERATED CONNECTION='${federated}/SCHEMA_PRIVILEGES' DEFAULT CHARSET=utf8;

drop table if exists ${server}_tbl_privs;
CREATE TABLE ${server}_tbl_privs (
  GRANTEE varchar(81) NOT NULL DEFAULT '',
  TABLE_CATALOG varchar(512) NOT NULL DEFAULT '',
  TABLE_SCHEMA varchar(64) NOT NULL DEFAULT '',
  TABLE_NAME varchar(64) NOT NULL DEFAULT '',
  PRIVILEGE_TYPE varchar(64) NOT NULL DEFAULT '',
  IS_GRANTABLE varchar(3) NOT NULL DEFAULT ''
) ENGINE=FEDERATED CONNECTION='${federated}/TABLE_PRIVILEGES' DEFAULT CHARSET=utf8;

drop table if exists ${server}_col_privs;
CREATE TABLE ${server}_col_privs (
  GRANTEE varchar(81) NOT NULL DEFAULT '',
  TABLE_CATALOG varchar(512) NOT NULL DEFAULT '',
  TABLE_SCHEMA varchar(64) NOT NULL DEFAULT '',
  TABLE_NAME varchar(64) NOT NULL DEFAULT '',
  COLUMN_NAME varchar(64) NOT NULL DEFAULT '',
  PRIVILEGE_TYPE varchar(64) NOT NULL DEFAULT '',
  IS_GRANTABLE varchar(3) NOT NULL DEFAULT ''
) ENGINE=FEDERATED CONNECTION='${federated}/COLUMN_PRIVILEGES' DEFAULT CHARSET=utf8;

drop table if exists ${server}_global_status;
CREATE TABLE ${server}_global_status (
  VARIABLE_NAME varchar(64) NOT NULL DEFAULT '',
  VARIABLE_VALUE varchar(1024) DEFAULT NULL
) ENGINE=FEDERATED CONNECTION='${federated}/GLOBAL_STATUS' DEFAULT CHARSET=utf8;

drop table if exists ${server}_global_vars;
CREATE TABLE ${server}_global_vars (
  VARIABLE_NAME varchar(64) NOT NULL DEFAULT '',
  VARIABLE_VALUE varchar(1024) DEFAULT NULL
) ENGINE=FEDERATED CONNECTION='${federated}/GLOBAL_VARIABLES' DEFAULT CHARSET=utf8;

drop table if exists ${server}_user_stats;
CREATE TABLE ${server}_user_stats (
  USER varchar(48) NOT NULL DEFAULT '',
  TOTAL_CONNECTIONS int(11) NOT NULL DEFAULT '0',
  CONCURRENT_CONNECTIONS int(11) NOT NULL DEFAULT '0',
  CONNECTED_TIME int(11) NOT NULL DEFAULT '0',
  BUSY_TIME double NOT NULL DEFAULT '0',
  CPU_TIME double NOT NULL DEFAULT '0',
  BYTES_RECEIVED bigint(21) NOT NULL DEFAULT '0',
  BYTES_SENT bigint(21) NOT NULL DEFAULT '0',
  BINLOG_BYTES_WRITTEN bigint(21) NOT NULL DEFAULT '0',
  ROWS_READ bigint(21) NOT NULL DEFAULT '0',
  ROWS_SENT bigint(21) NOT NULL DEFAULT '0',
  ROWS_DELETED bigint(21) NOT NULL DEFAULT '0',
  ROWS_INSERTED bigint(21) NOT NULL DEFAULT '0',
  ROWS_UPDATED bigint(21) NOT NULL DEFAULT '0',
  SELECT_COMMANDS bigint(21) NOT NULL DEFAULT '0',
  UPDATE_COMMANDS bigint(21) NOT NULL DEFAULT '0',
  OTHER_COMMANDS bigint(21) NOT NULL DEFAULT '0',
  COMMIT_TRANSACTIONS bigint(21) NOT NULL DEFAULT '0',
  ROLLBACK_TRANSACTIONS bigint(21) NOT NULL DEFAULT '0',
  DENIED_CONNECTIONS bigint(21) NOT NULL DEFAULT '0',
  LOST_CONNECTIONS bigint(21) NOT NULL DEFAULT '0',
  ACCESS_DENIED bigint(21) NOT NULL DEFAULT '0',
  EMPTY_QUERIES bigint(21) NOT NULL DEFAULT '0'
) ENGINE=FEDERATED CONNECTION='${federated}/USER_STATISTICS' DEFAULT CHARSET=utf8;


drop table if exists ${server}_table_stats;
CREATE TABLE ${server}_table_stats (
  TABLE_SCHEMA varchar(192) NOT NULL DEFAULT '',
  TABLE_NAME varchar(192) NOT NULL DEFAULT '',
  ROWS_READ bigint(21) NOT NULL DEFAULT '0',
  ROWS_CHANGED bigint(21) NOT NULL DEFAULT '0',
  ROWS_CHANGED_X_INDEXES bigint(21) NOT NULL DEFAULT '0'
) ENGINE=FEDERATED CONNECTION='${federated}/TABLE_STATISTICS' DEFAULT CHARSET=utf8;

drop table if exists ${server}_client_stats;
CREATE TABLE ${server}_client_stats (
  CLIENT varchar(64) NOT NULL DEFAULT '',
  TOTAL_CONNECTIONS bigint(21) NOT NULL DEFAULT '0',
  CONCURRENT_CONNECTIONS bigint(21) NOT NULL DEFAULT '0',
  CONNECTED_TIME bigint(21) NOT NULL DEFAULT '0',
  BUSY_TIME double NOT NULL DEFAULT '0',
  CPU_TIME double NOT NULL DEFAULT '0',
  BYTES_RECEIVED bigint(21) NOT NULL DEFAULT '0',
  BYTES_SENT bigint(21) NOT NULL DEFAULT '0',
  BINLOG_BYTES_WRITTEN bigint(21) NOT NULL DEFAULT '0',
  ROWS_READ bigint(21) NOT NULL DEFAULT '0',
  ROWS_SENT bigint(21) NOT NULL DEFAULT '0',
  ROWS_DELETED bigint(21) NOT NULL DEFAULT '0',
  ROWS_INSERTED bigint(21) NOT NULL DEFAULT '0',
  ROWS_UPDATED bigint(21) NOT NULL DEFAULT '0',
  SELECT_COMMANDS bigint(21) NOT NULL DEFAULT '0',
  UPDATE_COMMANDS bigint(21) NOT NULL DEFAULT '0',
  OTHER_COMMANDS bigint(21) NOT NULL DEFAULT '0',
  COMMIT_TRANSACTIONS bigint(21) NOT NULL DEFAULT '0',
  ROLLBACK_TRANSACTIONS bigint(21) NOT NULL DEFAULT '0',
  DENIED_CONNECTIONS bigint(21) NOT NULL DEFAULT '0',
  LOST_CONNECTIONS bigint(21) NOT NULL DEFAULT '0',
  ACCESS_DENIED bigint(21) NOT NULL DEFAULT '0',
  EMPTY_QUERIES bigint(21) NOT NULL DEFAULT '0'
) ENGINE=FEDERATED CONNECTION='${federated}/CLIENT_STATISTICS' DEFAULT CHARSET=utf8;

drop table if exists ${server}_index_stats;
CREATE TABLE ${server}_index_stats (
  TABLE_SCHEMA varchar(192) NOT NULL DEFAULT '',
  TABLE_NAME varchar(192) NOT NULL DEFAULT '',
  INDEX_NAME varchar(192) NOT NULL DEFAULT '',
  ROWS_READ bigint(21) NOT NULL DEFAULT '0'
) ENGINE=FEDERATED CONNECTION='${federated}/INDEX_STATISTICS' DEFAULT CHARSET=utf8;

drop table if exists ${server}_process;
CREATE TABLE ${server}_process (
  ID bigint(4) NOT NULL DEFAULT '0',
  USER varchar(16) NOT NULL DEFAULT '',
  HOST varchar(64) NOT NULL DEFAULT '',
  DB varchar(64) DEFAULT NULL,
  COMMAND varchar(16) NOT NULL DEFAULT '',
  TIME int(7) NOT NULL DEFAULT '0',
  STATE varchar(64) DEFAULT NULL,
  INFO longtext,
  TIME_MS decimal(22,3) NOT NULL DEFAULT '0.000',
  STAGE tinyint(2) NOT NULL DEFAULT '0',
  MAX_STAGE tinyint(2) NOT NULL DEFAULT '0',
  PROGRESS decimal(7,3) NOT NULL DEFAULT '0.000'
) ENGINE=FEDERATED CONNECTION='${federated}/PROCESSLIST' DEFAULT CHARSET=utf8;

drop table if exists ${server}_innodb_trx;
CREATE TABLE ${server}_innodb_trx (
  trx_id varchar(18) NOT NULL DEFAULT '',
  trx_state varchar(13) NOT NULL DEFAULT '',
  trx_started datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  trx_requested_lock_id varchar(81) DEFAULT NULL,
  trx_wait_started datetime DEFAULT NULL,
  trx_weight bigint(21) unsigned NOT NULL DEFAULT '0',
  trx_mysql_thread_id bigint(21) unsigned NOT NULL DEFAULT '0',
  trx_query varchar(1024) DEFAULT NULL,
  trx_operation_state varchar(64) DEFAULT NULL,
  trx_tables_in_use bigint(21) unsigned NOT NULL DEFAULT '0',
  trx_tables_locked bigint(21) unsigned NOT NULL DEFAULT '0',
  trx_lock_structs bigint(21) unsigned NOT NULL DEFAULT '0',
  trx_lock_memory_bytes bigint(21) unsigned NOT NULL DEFAULT '0',
  trx_rows_locked bigint(21) unsigned NOT NULL DEFAULT '0',
  trx_rows_modified bigint(21) unsigned NOT NULL DEFAULT '0',
  trx_concurrency_tickets bigint(21) unsigned NOT NULL DEFAULT '0',
  trx_isolation_level varchar(16) NOT NULL DEFAULT '',
  trx_unique_checks int(1) NOT NULL DEFAULT '0',
  trx_foreign_key_checks int(1) NOT NULL DEFAULT '0',
  trx_last_foreign_key_error varchar(256) DEFAULT NULL,
  trx_adaptive_hash_latched int(1) NOT NULL DEFAULT '0',
  trx_adaptive_hash_timeout bigint(21) unsigned NOT NULL DEFAULT '0'
) ENGINE=FEDERATED CONNECTION='${federated}/INNODB_TRX' DEFAULT CHARSET=utf8;

drop table if exists ${server}_innodb_locks;
CREATE TABLE ${server}_innodb_locks (
  lock_id varchar(81) NOT NULL DEFAULT '',
  lock_trx_id varchar(18) NOT NULL DEFAULT '',
  lock_mode varchar(32) NOT NULL DEFAULT '',
  lock_type varchar(32) NOT NULL DEFAULT '',
  lock_table varchar(1024) NOT NULL DEFAULT '',
  lock_index varchar(1024) DEFAULT NULL,
  lock_space bigint(21) unsigned DEFAULT NULL,
  lock_page bigint(21) unsigned DEFAULT NULL,
  lock_rec bigint(21) unsigned DEFAULT NULL,
  lock_data varchar(8192) DEFAULT NULL
) ENGINE=FEDERATED CONNECTION='${federated}/INNODB_LOCKS' DEFAULT CHARSET=utf8;

drop table if exists ${server}_slave_status;

drop table if exists ${server}_master_status;
CREATE TABLE ${server}_master_status
ENGINE=CONNECT CONNECTION='${federated}'
  TABLE_TYPE=MYSQL SRCDEF='SHOW MASTER STATUS';

drop table if exists ${server}_general_log_sampled;
CREATE TABLE ${server}_general_log_sampled (
  event_time timestamp(6) NOT NULL,
  user_host mediumtext NOT NULL,
  thread_id int(11) NOT NULL,
  server_id int(10) unsigned NOT NULL,
  command_type varchar(64) NOT NULL,
  argument mediumtext NOT NULL
) ENGINE=FEDERATED CONNECTION='${federated_mysql}/general_log' DEFAULT CHARSET=utf8;

drop table if exists ${server}_slow_log_sampled;
CREATE TABLE ${server}_slow_log_sampled (
  start_time timestamp(6) NOT NULL,
  user_host mediumtext NOT NULL,
  query_time time(6) NOT NULL,
  lock_time time(6) NOT NULL,
  rows_sent int(11) NOT NULL,
  rows_examined int(11) NOT NULL,
  db varchar(512) NOT NULL,
  last_insert_id int(11) NOT NULL,
  insert_id int(11) NOT NULL,
  server_id int(10) unsigned NOT NULL,
  sql_text mediumtext NOT NULL
) ENGINE=FEDERATED CONNECTION='${federated_mysql}/slow_log' DEFAULT CHARSET=utf8;

delimiter ;;

create event ${server}_schema_prep
  on schedule every 1 day starts date(now()) + interval floor(rand() * 23) hour
  do begin

    select @server_id := id, @enabled := enabled from servers where host = '${host}' and port = ${port};

    delete from schemata_check where server_id = @server_id;
    insert into schemata_check select @server_id, t.schema_name from ${server}_schemata t;

  end ;;

create event ${server}_schema
  on schedule every 1 minute starts date(now()) + interval floor(rand() * 59) second
  do begin

    if (get_lock('${server}_schema', 1) = 0) then
      signal sqlstate value '45000' set message_text = 'get_lock';
    end if;

    select @server_id := id, @enabled := enabled from servers where host = '${host}' and port = ${port};

    if (@enabled = 1) then

      -- SCHEMATA

      begin

        declare db_done int default 0;
        declare db_name varchar(100);

        declare db_names cursor for
          select schema_name from schemata_check where server_id = @server_id limit 10;

        declare continue handler for not found set db_done = 1;

        set db_done = 0;
        open db_names;

        repeat fetch db_names into db_name;

          if (db_done = 0 and db_name is not null) then

            delete from schemata where server_id = @server_id and schema_name = db_name;

            create temporary table new_tables as
              select table_name from ${server}_tablenames where table_schema = db_name;

            begin

              declare tbl_done int default 0;
              declare tbl_name varchar(100);

              declare tbl_names cursor for
                select table_name from new_tables;

              declare continue handler for not found set tbl_done = 1;

              set tbl_done = 0;
              open tbl_names;

              repeat fetch tbl_names into tbl_name;

                if (tbl_done = 0 and tbl_name is not null) then

                  -- TABLES

                  create temporary table new_table as
                    select * from ${server}_tables where table_schema = db_name and table_name = tbl_name;

                  select @fields := group_concat(column_name) from information_schema.columns
                    where table_schema = database() and table_name = '${server}_tables';

                  delete from tables where server_id = @server_id and table_schema = db_name and table_name = tbl_name;

                  set @sql := concat(
                    'insert into tables (server_id, ', @fields, ') select @server_id, ', @fields, ' from new_table'
                  );

                  prepare stmt from @sql; execute stmt; deallocate prepare stmt;

                  drop temporary table new_table;

                  -- COLUMNS

                  create temporary table new_columns as
                    select * from ${server}_columns where table_schema = db_name and table_name = tbl_name;

                  select @fields := group_concat(column_name) from information_schema.columns
                    where table_schema = database() and table_name = '${server}_columns';

                  delete from columns where server_id = @server_id
                    and table_schema = db_name and table_name = tbl_name;

                  set @sql := concat(
                    'insert into columns (server_id, ', @fields, ') select @server_id, ', @fields, ' from new_columns'
                  );

                  prepare stmt from @sql; execute stmt; deallocate prepare stmt;

                  drop temporary table new_columns;

                  -- PARTITIONS

                  create temporary table new_partitions as
                    select * from ${server}_partitions where table_schema = db_name and table_name = tbl_name;

                  select @fields := group_concat(column_name) from information_schema.columns
                    where table_schema = database() and table_name = '${server}_partitions';

                  delete from partitions where server_id = @server_id
                    and table_schema = db_name and table_name = tbl_name;

                  set @sql := concat(
                    'insert into partitions (server_id, ', @fields, ') select @server_id, ', @fields, ' from new_partitions'
                  );

                  prepare stmt from @sql; execute stmt; deallocate prepare stmt;

                  drop temporary table new_partitions;

                end if;

                until tbl_done
              end repeat;

              close tbl_names;

            end;

            -- TRIGGERS

            create temporary table new_triggers as
              select * from ${server}_triggers where trigger_schema = db_name;

            select @fields := group_concat(column_name) from information_schema.columns
              where table_schema = database() and table_name = '${server}_triggers';

            delete from triggers where server_id = @server_id and trigger_schema = db_name;

            set @sql := concat(
              'insert into triggers (server_id, ', @fields, ') select @server_id, ', @fields, ' from new_triggers'
            );

            prepare stmt from @sql; execute stmt; deallocate prepare stmt;

            -- clean up

            delete from tables where server_id = @server_id and table_schema = db_name and table_name not in (
              select table_name from new_tables
            );

            delete from triggers where server_id = @server_id and trigger_schema = db_name and trigger_name not in (
              select trigger_name from new_triggers
            );

            drop temporary table new_tables;
            drop temporary table new_triggers;

            delete from schemata_check where server_id = @server_id and schema_name = db_name;
            insert into schemata select @server_id, t.* from ${server}_schemata t where t.schema_name = db_name;

          end if;

          until db_done
        end repeat;

        close db_names;

      end;

      update servers set event_schema = now() where id = @server_id;

    end if;

    do release_lock('${server}_schema');
  end ;;

create event ${server}_privileges
  on schedule every 1 day starts date(now()) + interval floor(rand() * 23) hour
  do begin

    if (get_lock('${server}_privileges', 1) = 0) then
        signal sqlstate value '45000' set message_text = 'get_lock';
    end if;

    select @server_id := id, @enabled := enabled from servers where host = '${host}' and port = ${port};

    if (@enabled = 1) then

      delete from schema_privileges where server_id = @server_id;
      insert into schema_privileges select @server_id, t1.* from ${server}_sch_privs t1;

      delete from table_privileges where server_id = @server_id;
      insert into table_privileges select @server_id, t2.* from ${server}_tbl_privs t2;

      delete from column_privileges where server_id = @server_id;
      insert into column_privileges select @server_id, t3.* from ${server}_col_privs t3;

      update servers set event_privileges = now() where id = @server_id;

    end if;

    do release_lock('${server}_privileges');
  end ;;

create event ${server}_usage
  on schedule every 1 hour starts date(now()) + interval floor(rand() * 59) minute
  do begin

    if (get_lock('${server}_usage', 1) = 0) then
        signal sqlstate value '45000' set message_text = 'get_lock';
    end if;

    select @server_id := id, @enabled := enabled from servers where host = '${host}' and port = ${port};

    if (@enabled = 1) then

      create temporary table t1 as select * from ${server}_user_stats;
      create temporary table t2 as select * from ${server}_table_stats;
      create temporary table t3 as select * from ${server}_client_stats;
      create temporary table t4 as select * from ${server}_index_stats;

      delete from user_statistics where server_id = @server_id;
      insert into user_statistics select @server_id, user,
        sum(total_connections), sum(concurrent_connections), sum(connected_time), sum(busy_time), sum(cpu_time), sum(bytes_received),
        sum(bytes_sent), sum(binlog_bytes_written), sum(rows_read), sum(rows_sent), sum(rows_deleted), sum(rows_inserted), sum(rows_updated),
        sum(select_commands), sum(update_commands), sum(other_commands), sum(commit_transactions), sum(rollback_transactions),
        sum(denied_connections), sum(lost_connections), sum(access_denied), sum(empty_queries)
        from t1 group by user;
      insert into user_statistics_log select @server_id, now(), user,
        sum(total_connections), sum(concurrent_connections), sum(connected_time), sum(busy_time), sum(cpu_time), sum(bytes_received),
        sum(bytes_sent), sum(binlog_bytes_written), sum(rows_read), sum(rows_sent), sum(rows_deleted), sum(rows_inserted), sum(rows_updated),
        sum(select_commands), sum(update_commands), sum(other_commands), sum(commit_transactions), sum(rollback_transactions),
        sum(denied_connections), sum(lost_connections), sum(access_denied), sum(empty_queries)
        from t1 group by user;

      delete from table_statistics where server_id = @server_id;
      insert into table_statistics select @server_id, table_schema, table_name, sum(rows_read), sum(rows_changed), sum(rows_changed_x_indexes)
        from t2 group by table_schema, table_name;
      insert into table_statistics_log select @server_id, now(), table_schema, table_name, sum(rows_read), sum(rows_changed), sum(rows_changed_x_indexes)
        from t2 group by table_schema, table_name;

      delete from client_statistics where server_id = @server_id;
      insert into client_statistics select @server_id, client,
        sum(total_connections), sum(concurrent_connections), sum(connected_time), sum(busy_time), sum(cpu_time), sum(bytes_received),
        sum(bytes_sent), sum(binlog_bytes_written), sum(rows_read), sum(rows_sent), sum(rows_deleted), sum(rows_inserted), sum(rows_updated),
        sum(select_commands), sum(update_commands), sum(other_commands), sum(commit_transactions), sum(rollback_transactions),
        sum(denied_connections), sum(lost_connections), sum(access_denied), sum(empty_queries)
        from t3 group by client;
      insert into client_statistics_log select @server_id, now(), client,
        sum(total_connections), sum(concurrent_connections), sum(connected_time), sum(busy_time), sum(cpu_time), sum(bytes_received),
        sum(bytes_sent), sum(binlog_bytes_written), sum(rows_read), sum(rows_sent), sum(rows_deleted), sum(rows_inserted), sum(rows_updated),
        sum(select_commands), sum(update_commands), sum(other_commands), sum(commit_transactions), sum(rollback_transactions),
        sum(denied_connections), sum(lost_connections), sum(access_denied), sum(empty_queries)
        from t3 group by client;

      delete from index_statistics where server_id = @server_id;
      insert into index_statistics select @server_id, table_schema, table_name, index_name, sum(rows_read)
        from t4 group by table_schema, table_name, index_name;
      insert into index_statistics_log select @server_id, now(), table_schema, table_name, index_name, sum(rows_read)
        from t4 group by table_schema, table_name, index_name;

      drop temporary table if exists t1;
      drop temporary table if exists t2;
      drop temporary table if exists t3;
      drop temporary table if exists t4;

      update servers set event_usage = now() where id = @server_id;

    end if;

    do release_lock('${server}_usage');
  end ;;

create event ${server}_status
  on schedule every 30 second starts date(now()) + interval floor(rand() * 29) second
  do begin

    if (get_lock('${server}_status', 1) = 0) then
      signal sqlstate value '45000' set message_text = 'get_lock';
    end if;

    select @server_id := id, @enabled := enabled from servers where host = '${host}' and port = ${port};

    if (@enabled = 1) then

      create temporary table t1 as select * from ${server}_global_status;
      delete from global_status where server_id = @server_id and variable_name not like '%.%';
      insert into global_status select @server_id, lower(VARIABLE_NAME), variable_value from t1;

      -- insert ignore into strings (string) select lower(VARIABLE_NAME) from t1;
      -- INSERT IGNORE for InnoDB without auto-inc holes
      insert into strings (string)
        select lower(VARIABLE_NAME) from t1
          left join strings b on lower(t1.VARIABLE_NAME) = b.string
          where b.string is null;

      insert ignore into global_status_log (server_id, stamp, name_id, value)
        select @server_id, now(), n.id, gs.variable_value from global_status gs
          join strings n on gs.variable_name = n.string
          where gs.server_id = @server_id
            and gs.variable_value regexp '^[0-9\.]+'
            and gs.variable_name not in (
              select distinct variable_name from slave_status
            );

      drop temporary table if exists t1;

      update servers set event_status = now() where id = @server_id;

    end if;

    do release_lock('${server}_status');
  end ;;

create event ${server}_variables
  on schedule every 1 minute starts date(now()) + interval floor(rand() * 59) second
  do begin

    if (get_lock('${server}_variables', 1) = 0) then
      signal sqlstate value '45000' set message_text = 'get_lock';
    end if;

    select @server_id := id, @enabled := enabled from servers where host = '${host}' and port = ${port};

    if (@enabled = 1) then

      delete from global_variables where server_id = @server_id;
      insert into global_variables select @server_id, lower(VARIABLE_NAME), variable_value from ${server}_global_vars;

      update servers set event_variables = now() where id = @server_id;

    end if;

    do release_lock('${server}_variables');
  end ;;

create event ${server}_activity
  on schedule every 10 second starts date(now()) + interval floor(rand() * 9) second
  do begin

    if (get_lock('${server}_activity', 1) = 0) then
      signal sqlstate value '45000' set message_text = 'get_lock';
    end if;

    select @server_id := id, @enabled := enabled from servers where host = '${host}' and port = ${port};

    if (@enabled = 1) then

      delete from processlist  where server_id = @server_id;
      delete from innodb_trx   where server_id = @server_id;
      delete from innodb_locks where server_id = @server_id;

      insert into processlist  select @server_id, t.* from ${server}_process t where t.user <> '${user}';
      insert into innodb_trx   select @server_id, t.* from ${server}_innodb_trx t;
      insert into innodb_locks select @server_id, t.* from ${server}_innodb_locks t;

      insert into innodb_trx_log   select * from innodb_trx where server_id = @server_id;
      insert into innodb_locks_log select now(), l.* from innodb_locks l;

      -- mariadb 5.5 bug
      update processlist set time = 0 where server_id = @server_id and time = 2147483647;

      select @time_max := max(time) from processlist where server_id = @server_id
        and user <> 'system user' and command in ('Query', 'Execute');

      if (@time_max is null) then
        set @time_max = 10;
      end if;

      if (@time_max > 604800) then
        set @time_max = 604800;
      end if;

      select @stamp := now() - interval @time_max+10 second;

      insert into processlist_query_log
        (server_id, stamp, id, user, host, db, time, info)
        select p.server_id, now(), p.id, p.user, p.host, p.db, p.time, p.info
        from processlist p
        left join processlist_query_log q
          on q.server_id = @server_id
          and p.id = q.id
          and p.user = q.user
          and p.host = q.host
          and p.db = q.db
          and p.info = q.info
          and p.time > q.time
          and q.stamp > now() - interval 7 day
        where
          p.server_id = @server_id
          and p.command = 'Query'
          and q.server_id is null;

      update processlist_query_log q
        join processlist p
          on p.server_id = @server_id
          and p.id = q.id
          and p.user = q.user
          and p.host = q.host
          and p.db = q.db
          and p.info = q.info
          and p.time > q.time
          and q.stamp > now() - interval p.time second
        set q.time = p.time
        where q.server_id = @server_id
          and p.server_id = @server_id
          and p.command = 'Query'
          and q.stamp > @stamp;

      update processlist_query_log q
        join innodb_trx t
          on t.server_id = @server_id
          and q.id = t.trx_mysql_thread_id
          and substr(q.info,1,1000) = substr(t.trx_query,1,1000)
          and length(t.trx_id) > 0
        set q.trx_id = t.trx_id
        where q.server_id = @server_id
          and t.server_id = @server_id
          and q.trx_id is null
          and q.stamp > @stamp
          and t.trx_started > @stamp;

      update servers set event_activity = now() where id = @server_id;

    end if;

    do release_lock('${server}_activity');
  end ;;

create event ${server}_sampled
  on schedule every 10 second starts date(now()) + interval floor(rand() * 9) second
  do begin

    if (get_lock('${server}_sampled', 1) = 0) then
      signal sqlstate value '45000' set message_text = 'get_lock';
    end if;

    select @server_id := id, @enabled := enabled from servers where host = '${host}' and port = ${port};

    if (@enabled = 1) then

      create temporary table t1 as
        select event_time, user_host, thread_id, server_id, command_type, argument
          from ${server}_general_log_sampled;

      insert into general_log_sampled
        (server_id, event_time, user_host, thread_id, m_server_id, command_type, argument, checksum)
          select @server_id, event_time, user_host, thread_id, server_id, command_type, argument, md5(argument)
            from t1;

      insert ignore into queries (checksum, first_seen, content)
        select md5(t1.argument), min(event_time), t1.argument from t1
          where t1.command_type = 'Query'
            group by t1.argument;

      update queries q
        join t1 on q.checksum = md5(t1.argument)
          set last_seen = event_time;

      insert into queries_seen_log (checksum, server_id, stamp)
        select md5(t1.argument), @server_id, t1.event_time from t1;

      create temporary table t2 as
        select start_time, user_host, query_time, lock_time, rows_sent, rows_examined,
            db, last_insert_id, insert_id, server_id, sql_text
          from ${server}_slow_log_sampled;

      insert into slow_log_sampled
        (server_id, start_time, user_host, query_time, lock_time, rows_sent, rows_examined,
            db, last_insert_id, insert_id, m_server_id, sql_text, checksum)
        select @server_id, start_time, user_host, query_time, lock_time, rows_sent, rows_examined,
            db, last_insert_id, insert_id, server_id, sql_text, md5(sql_text)
          from t2;

      insert ignore into queries (checksum, first_seen, content)
        select md5(t2.sql_text), min(start_time), t2.sql_text from t2
          group by t2.sql_text;

      insert into queries_seen_log (checksum, server_id, stamp)
        select md5(t2.sql_text), @server_id, t2.start_time from t2;

      update queries q
        join t2 on q.checksum = md5(t2.sql_text)
          set last_seen = start_time;

      drop temporary table if exists t1;
      drop temporary table if exists t2;

    end if;

    do release_lock('${server}_sampled');
  end ;;

create event ${server}_replication
  on schedule every 10 second starts date(now()) + interval floor(rand() * 9) second
  do begin

    set @have_vars    := 0;
    set @mariadb10    := 0;
    set @table_exists := 0;

    if (get_lock('${server}_replication', 1) = 0) then
      signal sqlstate value '45000' set message_text = 'get_lock';
    end if;

    select @server_id := id, @enabled := enabled from servers
      where host = '${host}' and port = ${port};

    select @have_vars := count(*) from global_variables
      where server_id = @server_id;

    select @mariadb10 := if(variable_value like '10.%',1,0) from global_variables
      where server_id = @server_id and variable_name = 'version';

    if (@enabled = 1 and @have_vars <> 0) then

      select @table_exists := count(*) from information_schema.tables
        where table_schema = database() and table_name = '${server}_slave_status';

      if (@table_exists = 0) then

        if (@mariadb10 = 1) then

          CREATE TABLE ${server}_slave_status ENGINE=CONNECT CONNECTION='${federated}' TABLE_TYPE=MYSQL
            SRCDEF='SHOW ALL SLAVES STATUS';

        else

          CREATE TABLE ${server}_slave_status ENGINE=CONNECT CONNECTION='${federated}' TABLE_TYPE=MYSQL
            SRCDEF='SHOW SLAVE STATUS';

        end if;

      end if;

      -- INSERT IGNORE for InnoDB without auto-inc holes
      insert into strings (string)
        select lower(column_name) from information_schema.columns
          left join strings b on lower(column_name) = b.string
          where b.string is null and table_schema = database() and table_name = '${server}_master_status';

      create temporary table t1 as select * from ${server}_master_status;
      delete from master_status where server_id = @server_id;

      insert into master_status (server_id, variable_name, variable_value) select @server_id, 'File', File from t1;
      insert into master_status (server_id, variable_name, variable_value) select @server_id, 'Position', Position from t1;
      insert into master_status (server_id, variable_name, variable_value) select @server_id, 'Binlog_Do_DB', Binlog_Do_DB from t1;
      insert into master_status (server_id, variable_name, variable_value) select @server_id, 'Binlog_Ignore_DB', Binlog_Ignore_DB from t1;

      drop temporary table if exists t1;

      if (@mariadb10 = 0) then

        -- INSERT IGNORE for InnoDB without auto-inc holes
        insert into strings (string)
          select lower(column_name) from information_schema.columns
            left join strings b on lower(column_name) = b.string
            where b.string is null and table_schema = database() and table_name = '${server}_slave_status';

        create temporary table t1 as select * from ${server}_slave_status;
        delete from slave_status where server_id = @server_id;
        delete from replication where server_id = @server_id;

        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'slave_io_state', Slave_IO_State from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'master_host', Master_Host from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'master_user', Master_User from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'master_port', Master_Port from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'connect_retry', Connect_Retry from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'master_log_file', Master_Log_File from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'read_master_log_pos', Read_Master_Log_Pos from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'relay_log_file', Relay_Log_File from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'relay_log_pos', Relay_Log_Pos from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'relay_master_log_file', Relay_Master_Log_File from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'slave_io_running', Slave_IO_Running from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'slave_sql_running', Slave_SQL_Running from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'replicate_do_db', Replicate_Do_DB from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'replicate_ignore_db', Replicate_Ignore_DB from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'replicate_do_table', Replicate_Do_Table from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'replicate_ignore_table', Replicate_Ignore_Table from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'replicate_wild_do_table', Replicate_Wild_Do_Table from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'replicate_wild_ignore_table', Replicate_Wild_Ignore_Table from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'last_errno', Last_Errno from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'last_error', Last_Error from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'skip_counter', Skip_Counter from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'exec_master_log_pos', Exec_Master_Log_Pos from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'relay_log_space', Relay_Log_Space from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'until_condition', Until_Condition from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'until_log_file', Until_Log_File from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'until_log_pos', Until_Log_Pos from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'seconds_behind_master', Seconds_Behind_Master from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'last_io_errno', Last_IO_Errno from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'last_io_error', Last_IO_Error from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'last_sql_errno', Last_SQL_Errno from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'last_sql_error', Last_SQL_Error from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'replicate_ignore_server_ids', Replicate_Ignore_Server_Ids from t1;
        insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'master_server_id', Master_Server_Id from t1;

        update servers s
          set
            m_server_id   = (select variable_value from global_variables where server_id = @server_id and variable_name = 'server_id'),
            m_master_id   = (select variable_value from slave_status     where server_id = @server_id and variable_name = 'master_server_id'),
            m_master_port = (select variable_value from slave_status     where server_id = @server_id and variable_name = 'master_port')
          where id = @server_id;

        select @master_id := srv.id from servers srv
          join slave_status ss1 on srv.host = ss1.variable_value
            and ss1.variable_name = 'master_host'
            and ss1.server_id = @server_id
          join slave_status ss2 on srv.port = cast(ss2.variable_value as unsigned)
            and ss2.variable_name = 'master_port'
            and ss2.server_id = @server_id;

        if (@master_id is not null) then
          insert into replication (server_id, master_id) values (@server_id, @master_id);
        end if;

        drop temporary table if exists t1;
        drop temporary table if exists t2;

      else

        create temporary table t1 as select * from ${server}_slave_status;
        delete from slave_status where server_id = @server_id;
        delete from replication where server_id = @server_id;

        begin

          declare all_done int default 0;
          declare con_name varchar(10);
          declare con_prefix varchar(11);

          declare rep_cons cursor for
              select Connection_name from t1;

          declare continue handler for not found set all_done = 1;

          set all_done = 0;
          open rep_cons;

          repeat fetch rep_cons into con_name;

              if (all_done = 0 and con_name is not null) then

                set con_prefix := if(length(con_name) > 0, concat(con_name,'.'), '');

                -- INSERT IGNORE for InnoDB without auto-inc holes
                insert into strings (string)
                  select lower(concat(con_prefix,column_name)) from information_schema.columns
                    left join strings b on lower(concat(con_prefix,column_name)) = b.string
                    where b.string is null and table_schema = database() and table_name = '${server}_slave_status';

                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'slave_io_state'), Slave_IO_State
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'master_host'), Master_Host
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'master_user'), Master_User
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'master_port'), Master_Port
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'connect_retry'), Connect_Retry
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'master_log_file'), Master_Log_File
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'read_master_log_pos'), Read_Master_Log_Pos
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'relay_log_file'), Relay_Log_File
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'relay_log_pos'), Relay_Log_Pos
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'relay_master_log_file'), Relay_Master_Log_File
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'slave_io_running'), Slave_IO_Running
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'slave_sql_running'), Slave_SQL_Running
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'replicate_do_db'), Replicate_Do_DB
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'replicate_ignore_db'), Replicate_Ignore_DB
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'replicate_do_table'), Replicate_Do_Table
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'replicate_ignore_table'), Replicate_Ignore_Table
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'replicate_wild_do_table'), Replicate_Wild_Do_Table
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'replicate_wild_ignore_table'), Replicate_Wild_Ignore_Table
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'last_errno'), Last_Errno
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'last_error'), Last_Error
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'skip_counter'), Skip_Counter
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'exec_master_log_pos'), Exec_Master_Log_Pos
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'relay_log_space'), Relay_Log_Space
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'until_condition'), Until_Condition
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'until_log_file'), Until_Log_File
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'until_log_pos'), Until_Log_Pos
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'seconds_behind_master'), Seconds_Behind_Master
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'last_io_errno'), Last_IO_Errno
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'last_io_error'), Last_IO_Error
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'last_sql_errno'), Last_SQL_Errno
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'last_sql_error'), Last_SQL_Error
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'replicate_ignore_server_ids'), Replicate_Ignore_Server_Ids
                  from t1 where Connection_name = con_name;
                insert into slave_status (server_id, variable_name, variable_value) select @server_id, concat(con_prefix,'master_server_id'), Master_Server_Id
                  from t1 where Connection_name = con_name;

                select @master_id := srv.id from servers srv
                  join slave_status ss1 on srv.host = ss1.variable_value
                    and ss1.variable_name = concat(con_prefix,'master_host')
                    and ss1.server_id = @server_id
                  join slave_status ss2 on srv.port = cast(ss2.variable_value as unsigned)
                    and ss2.variable_name = concat(con_prefix,'master_port')
                    and ss2.server_id = @server_id;

                if (@master_id is not null) then
                  insert into replication (server_id, master_id, connection_name)
                    values (@server_id, @master_id, if(length(con_name) > 0,con_name,null));
                end if;

              end if;

              until all_done
          end repeat;

          close rep_cons;

        end;

        drop temporary table if exists t1;
        drop temporary table if exists t2;

      end if;

      insert ignore into slave_status_log (server_id, stamp, name_id, value)
        select @server_id, now(), n.id, gs.variable_value from slave_status gs
          join strings n on gs.variable_name = n.string
            where gs.server_id = @server_id and gs.variable_value regexp '^[0-9\.]+';

      delete gs from global_status gs join slave_status ss
        where gs.variable_name = ss.variable_name
          and gs.server_id = @server_id and ss.server_id = @server_id;

      insert ignore into global_status select * from slave_status where server_id = @server_id;

      insert ignore into global_status_log (server_id, stamp, name_id, value)
        select @server_id, now(), n.id, gs.variable_value from slave_status gs
          join strings n on gs.variable_name = n.string
            where gs.server_id = @server_id and gs.variable_value regexp '^[0-9\.]+';

      update servers set event_replication = now() where id = @server_id;

    end if;

    do release_lock('${server}_replication');
  end ;;

delimiter ;

eod
