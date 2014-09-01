#!/bin/bash

host="$1"
port="$2"

[ "$host" ] || exit 1
[ "$port" ] || exit 1

server=$(echo "$host.$port" | sed 's/\./_/g')

cat <<eod

select @server_id := id from servers where host = '${host}' and port = ${port};

delete from servers where id = @server_id;

drop event if exists ${server}_schema;
drop event if exists ${server}_activity;
drop event if exists ${server}_sampled;
drop event if exists ${server}_status;
drop event if exists ${server}_variables;
drop event if exists ${server}_usage;
drop event if exists ${server}_privileges;
drop event if exists ${server}_replication;

drop table if exists ${server}_client_stats;
drop table if exists ${server}_col_privs;
drop table if exists ${server}_columns;
drop table if exists ${server}_global_status;
drop table if exists ${server}_global_vars;
drop table if exists ${server}_index_stats;
drop table if exists ${server}_process;
drop table if exists ${server}_sch_privs;
drop table if exists ${server}_schemata;
drop table if exists ${server}_stats;
drop table if exists ${server}_triggers;
drop table if exists ${server}_table_stats;
drop table if exists ${server}_tables;
drop table if exists ${server}_tablenames;
drop table if exists ${server}_tbl_privs;
drop table if exists ${server}_user_stats;
drop table if exists ${server}_general_log_sampled;
drop table if exists ${server}_innodb_locks;
drop table if exists ${server}_innodb_trx;
drop table if exists ${server}_master_status;
drop table if exists ${server}_slave_status;
drop table if exists ${server}_slow_log_sampled;
drop table if exists ${server}_partitions;

eod