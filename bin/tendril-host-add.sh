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
drop event if exists ${server}_activity;
drop event if exists ${server}_activity_purge;
drop event if exists ${server}_sampled;
drop event if exists ${server}_status;
drop event if exists ${server}_status_purge;
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
  TABLE_CATALOG varchar(512) not null default '',
  TABLE_SCHEMA varchar(64) not null default '',
  TABLE_NAME varchar(64) not null default '',
  TABLE_TYPE varchar(64) not null default '',
  ENGINE varchar(64) default null,
  VERSION bigint(21) unsigned default null,
  ROW_FORMAT varchar(10) default null,
  TABLE_ROWS bigint(21) unsigned default null,
  AVG_ROW_LENGTH bigint(21) unsigned default null,
  DATA_LENGTH bigint(21) unsigned default null,
  MAX_DATA_LENGTH bigint(21) unsigned default null,
  INDEX_LENGTH bigint(21) unsigned default null,
  DATA_FREE bigint(21) unsigned default null,
  AUTO_INCREMENT bigint(21) unsigned default null,
  CREATE_TIME datetime default null,
  UPDATE_TIME datetime default null,
  CHECK_TIME datetime default null,
  TABLE_COLLATION varchar(32) default null,
  CHECKSUM bigint(21) unsigned default null,
  CREATE_OPTIONS varchar(255) default null,
  TABLE_COMMENT varchar(2048) not null default '',
  INDEX (TABLE_SCHEMA, TABLE_NAME)
) ENGINE=FEDERATED CONNECTION='${federated}/TABLES' DEFAULT CHARSET=utf8;

drop table if exists ${server}_tablenames;
CREATE TABLE ${server}_tablenames (
  TABLE_SCHEMA varchar(64) not null default '',
  TABLE_NAME varchar(64) not null default '',
  INDEX (TABLE_SCHEMA, TABLE_NAME)
) ENGINE=FEDERATED CONNECTION='${federated}/TABLES' DEFAULT CHARSET=utf8;

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
  INDEX (TABLE_SCHEMA, TABLE_NAME)
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
  INDEX (TABLE_SCHEMA, TABLE_NAME)
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
CREATE TABLE ${server}_slave_status
ENGINE=CONNECT CONNECTION='${federated}'
  TABLE_TYPE=MYSQL SRCDEF='SHOW SLAVE STATUS';

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

create event ${server}_schema
  on schedule every 1 day starts date(now()) + interval floor(rand() * 23) hour
  do begin

    if (get_lock('${server}_schema', 1) = 0) then
      signal sqlstate value '45000' set message_text = 'get_lock';
    end if;

    select @server_id := id, @enabled := enabled from servers where host = '${host}' and port = ${port};

    if (@enabled = 1) then

      create temporary table t1 as select * from ${server}_schemata;
      delete from schemata where server_id = @server_id;
      insert into schemata select @server_id, t1.* from t1;

      -- ref lookup for each schema and table name
      create temporary table t2 select a.* from ${server}_tablenames b
        straight_join ${server}_tables a force index (TABLE_SCHEMA)
        on b.TABLE_SCHEMA = a.TABLE_SCHEMA and b.TABLE_NAME = a.TABLE_NAME;
      delete from tables where server_id = @server_id;
      insert into tables select @server_id, t2.* from t2;

      -- ref lookup for each schema and table name
      create temporary table t3 select a.* from ${server}_tablenames b
        straight_join ${server}_columns a force index (TABLE_SCHEMA)
        on b.TABLE_SCHEMA = a.TABLE_SCHEMA and b.TABLE_NAME = a.TABLE_NAME;
      delete from columns where server_id = @server_id;
      insert into columns select @server_id, t3.* from t3;

      -- ref lookup for each schema and table name
      create temporary table t4 select a.* from ${server}_tablenames b
        straight_join ${server}_stats a force index (TABLE_SCHEMA)
        on b.TABLE_SCHEMA = a.TABLE_SCHEMA and b.TABLE_NAME = a.TABLE_NAME;
      delete from statistics where server_id = @server_id;
      insert into statistics select @server_id, t4.* from t4;

      -- ref lookup for each schema and table name
      create temporary table t5 select a.* from ${server}_schemata b
        straight_join ${server}_triggers a force index (TRIGGER_SCHEMA)
        on b.SCHEMA_NAME = a.TRIGGER_SCHEMA;
      delete from triggers where server_id = @server_id;
      insert into triggers select @server_id, t5.* from t5;

      drop temporary table if exists t1;
      drop temporary table if exists t2;
      drop temporary table if exists t3;
      drop temporary table if exists t4;
      drop temporary table if exists t5;

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
  on schedule every 1 minute starts date(now()) + interval floor(rand() * 59) second
  do begin

    if (get_lock('${server}_status', 1) = 0) then
      signal sqlstate value '45000' set message_text = 'get_lock';
    end if;

    select @server_id := id, @enabled := enabled from servers where host = '${host}' and port = ${port};

    if (@enabled = 1) then

      create temporary table t1 as select * from ${server}_global_status;
      delete from global_status where server_id = @server_id;
      insert into global_status select @server_id, lower(VARIABLE_NAME), variable_value from t1;

      -- insert ignore into strings (string) select lower(VARIABLE_NAME) from t1;
      -- INSERT IGNORE for InnoDB without auto-inc holes
      insert into strings (string)
        select lower(VARIABLE_NAME) from t1
          left join strings b on lower(t1.VARIABLE_NAME) = b.string
          where b.string is null;

      insert into global_status_log (server_id, stamp, name_id, value)
        select @server_id, now(), n.id, gs.variable_value from global_status gs
          join strings n on gs.variable_name = n.string
            where gs.server_id = @server_id and gs.variable_value regexp '^[0-9\.]+';

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

      select @time_max := max(time) from processlist where server_id = @server_id;
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

    if (get_lock('${server}_replication', 1) = 0) then
      signal sqlstate value '45000' set message_text = 'get_lock';
    end if;

    select @server_id := id, @enabled := enabled from servers where host = '${host}' and port = ${port};

    if (@enabled = 1) then

      create temporary table t1 as select * from ${server}_master_status;
      delete from master_status where server_id = @server_id;

      insert into master_status (server_id, variable_name, variable_value) select @server_id, 'File', File from t1;
      insert into master_status (server_id, variable_name, variable_value) select @server_id, 'Position', Position from t1;
      insert into master_status (server_id, variable_name, variable_value) select @server_id, 'Binlog_Do_DB', Binlog_Do_DB from t1;
      insert into master_status (server_id, variable_name, variable_value) select @server_id, 'Binlog_Ignore_DB', Binlog_Ignore_DB from t1;

      drop temporary table if exists t1;

      create temporary table t1 as select * from ${server}_slave_status;
      delete from slave_status where server_id = @server_id;

      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Slave_IO_State', Slave_IO_State from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Master_Host', Master_Host from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Master_User', Master_User from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Master_Port', Master_Port from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Connect_Retry', Connect_Retry from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Master_Log_File', Master_Log_File from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Read_Master_Log_Pos', Read_Master_Log_Pos from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Relay_Log_File', Relay_Log_File from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Relay_Log_Pos', Relay_Log_Pos from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Relay_Master_Log_File', Relay_Master_Log_File from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Slave_IO_Running', Slave_IO_Running from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Slave_SQL_Running', Slave_SQL_Running from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Replicate_Do_DB', Replicate_Do_DB from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Replicate_Ignore_DB', Replicate_Ignore_DB from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Replicate_Do_Table', Replicate_Do_Table from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Replicate_Ignore_Table', Replicate_Ignore_Table from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Replicate_Wild_Do_Table', Replicate_Wild_Do_Table from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Replicate_Wild_Ignore_Table', Replicate_Wild_Ignore_Table from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Last_Errno', Last_Errno from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Last_Error', Last_Error from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Skip_Counter', Skip_Counter from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Exec_Master_Log_Pos', Exec_Master_Log_Pos from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Relay_Log_Space', Relay_Log_Space from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Until_Condition', Until_Condition from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Until_Log_File', Until_Log_File from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Until_Log_Pos', Until_Log_Pos from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Seconds_Behind_Master', Seconds_Behind_Master from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Last_IO_Errno', Last_IO_Errno from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Last_IO_Error', Last_IO_Error from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Last_SQL_Errno', Last_SQL_Errno from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Last_SQL_Error', Last_SQL_Error from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Replicate_Ignore_Server_Ids', Replicate_Ignore_Server_Ids from t1;
      insert into slave_status (server_id, variable_name, variable_value) select @server_id, 'Master_Server_Id', Master_Server_Id from t1;

      insert into strings (string) select lower('Slave_IO_State')
        from seq_1_to_1 left join strings b on lower('Slave_IO_State') = b.string
        where b.string is null;
      insert into strings (string) select lower('Master_Host')
        from seq_1_to_1 left join strings b on lower('Master_Host') = b.string
        where b.string is null;
      insert into strings (string) select lower('Master_User')
        from seq_1_to_1 left join strings b on lower('Master_User') = b.string
        where b.string is null;
      insert into strings (string) select lower('Master_Port')
        from seq_1_to_1 left join strings b on lower('Master_Port') = b.string
        where b.string is null;
      insert into strings (string) select lower('Connect_Retry')
        from seq_1_to_1 left join strings b on lower('Connect_Retry') = b.string
        where b.string is null;
      insert into strings (string) select lower('Master_Log_File')
        from seq_1_to_1 left join strings b on lower('Master_Log_File') = b.string
        where b.string is null;
      insert into strings (string) select lower('Read_Master_Log_Pos')
        from seq_1_to_1 left join strings b on lower('Read_Master_Log_Pos') = b.string
        where b.string is null;
      insert into strings (string) select lower('Relay_Log_File')
        from seq_1_to_1 left join strings b on lower('Relay_Log_File') = b.string
        where b.string is null;
      insert into strings (string) select lower('Relay_Log_Pos')
        from seq_1_to_1 left join strings b on lower('Relay_Log_Pos') = b.string
        where b.string is null;
      insert into strings (string) select lower('Relay_Master_Log_File')
        from seq_1_to_1 left join strings b on lower('Relay_Master_Log_File') = b.string
        where b.string is null;
      insert into strings (string) select lower('Slave_IO_Running')
        from seq_1_to_1 left join strings b on lower('Slave_IO_Running') = b.string
        where b.string is null;
      insert into strings (string) select lower('Slave_SQL_Running')
        from seq_1_to_1 left join strings b on lower('Slave_SQL_Running') = b.string
        where b.string is null;
      insert into strings (string) select lower('Replicate_Do_DB')
        from seq_1_to_1 left join strings b on lower('Replicate_Do_DB') = b.string
        where b.string is null;
      insert into strings (string) select lower('Replicate_Ignore_DB')
        from seq_1_to_1 left join strings b on lower('Replicate_Ignore_DB') = b.string
        where b.string is null;
      insert into strings (string) select lower('Replicate_Do_Table')
        from seq_1_to_1 left join strings b on lower('Replicate_Do_Table') = b.string
        where b.string is null;
      insert into strings (string) select lower('Replicate_Ignore_Table')
        from seq_1_to_1 left join strings b on lower('Replicate_Ignore_Table') = b.string
        where b.string is null;
      insert into strings (string) select lower('Replicate_Wild_Do_Table')
        from seq_1_to_1 left join strings b on lower('Replicate_Wild_Do_Table') = b.string
        where b.string is null;
      insert into strings (string) select lower('Replicate_Wild_Ignore_Table')
        from seq_1_to_1 left join strings b on lower('Replicate_Wild_Ignore_Table') = b.string
        where b.string is null;
      insert into strings (string) select lower('Last_Errno')
        from seq_1_to_1 left join strings b on lower('Last_Errno') = b.string
        where b.string is null;
      insert into strings (string) select lower('Last_Error')
        from seq_1_to_1 left join strings b on lower('Last_Error') = b.string
        where b.string is null;
      insert into strings (string) select lower('Skip_Counter')
        from seq_1_to_1 left join strings b on lower('Skip_Counter') = b.string
        where b.string is null;
      insert into strings (string) select lower('Exec_Master_Log_Pos')
        from seq_1_to_1 left join strings b on lower('Exec_Master_Log_Pos') = b.string
        where b.string is null;
      insert into strings (string) select lower('Relay_Log_Space')
        from seq_1_to_1 left join strings b on lower('Relay_Log_Space') = b.string
        where b.string is null;
      insert into strings (string) select lower('Until_Condition')
        from seq_1_to_1 left join strings b on lower('Until_Condition') = b.string
        where b.string is null;
      insert into strings (string) select lower('Until_Log_File')
        from seq_1_to_1 left join strings b on lower('Until_Log_File') = b.string
        where b.string is null;
      insert into strings (string) select lower('Until_Log_Pos')
        from seq_1_to_1 left join strings b on lower('Until_Log_Pos') = b.string
        where b.string is null;
      insert into strings (string) select lower('Seconds_Behind_Master')
        from seq_1_to_1 left join strings b on lower('Seconds_Behind_Master') = b.string
        where b.string is null;
      insert into strings (string) select lower('Last_IO_Errno')
        from seq_1_to_1 left join strings b on lower('Last_IO_Errno') = b.string
        where b.string is null;
      insert into strings (string) select lower('Last_IO_Error')
        from seq_1_to_1 left join strings b on lower('Last_IO_Error') = b.string
        where b.string is null;
      insert into strings (string) select lower('Last_SQL_Errno')
        from seq_1_to_1 left join strings b on lower('Last_SQL_Errno') = b.string
        where b.string is null;
      insert into strings (string) select lower('Last_SQL_Error')
        from seq_1_to_1 left join strings b on lower('Last_SQL_Error') = b.string
        where b.string is null;
      insert into strings (string) select lower('Replicate_Ignore_Server_Ids')
        from seq_1_to_1 left join strings b on lower('Replicate_Ignore_Server_Ids') = b.string
        where b.string is null;
      insert into strings (string) select lower('Master_Server_Id')
        from seq_1_to_1 left join strings b on lower('Master_Server_Id') = b.string
        where b.string is null;

      insert into slave_status_log (server_id, stamp, name_id, value)
        select @server_id, now(), n.id, gs.variable_value from slave_status gs
          join strings n on lower(gs.variable_name) = n.string
            where gs.server_id = @server_id and gs.variable_value regexp '^[0-9\.]+';

      update servers s
        set
          m_server_id   = (select variable_value from global_variables where server_id = @server_id and variable_name = 'server_id'),
          m_master_id   = (select variable_value from slave_status     where server_id = @server_id and variable_name = 'Master_Server_Id'),
          m_master_port = (select variable_value from slave_status     where server_id = @server_id and variable_name = 'Master_Port')
        where id = @server_id;

      drop temporary table if exists t1;
      drop temporary table if exists t2;

      update servers set event_replication = now() where id = @server_id;

    end if;

    do release_lock('${server}_replication');
  end ;;

delimiter ;

eod
