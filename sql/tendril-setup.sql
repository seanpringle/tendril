
delimiter ;;

drop event if exists tendril_purge_global_status_log;
create event tendril_purge_global_status_log
    on schedule every 15 minute starts date(now()) + interval 0 minute
    do begin
        select @stamp := now() - interval 7 day;
        delete from global_status_log where stamp < @stamp limit 1000000;
    end ;;

drop event if exists tendril_purge_client_statistics_log;
create event tendril_purge_client_statistics_log
    on schedule every 15 minute starts date(now()) + interval 1 minute
    do begin
        select @stamp := now() - interval 7 day;
        delete from client_statistics_log where stamp < @stamp limit 1000000;
    end ;;

drop event if exists tendril_purge_index_statistics_log;
create event tendril_purge_index_statistics_log
    on schedule every 15 minute starts date(now()) + interval 2 minute
    do begin
        select @stamp := now() - interval 7 day;
        delete from index_statistics_log where stamp < @stamp limit 1000000;
    end ;;

drop event if exists tendril_purge_table_statistics_log;
create event tendril_purge_table_statistics_log
    on schedule every 15 minute starts date(now()) + interval 3 minute
    do begin
        select @stamp := now() - interval 7 day;
        delete from table_statistics_log where stamp < @stamp limit 1000000;
    end ;;

drop event if exists tendril_purge_user_statistics_log;
create event tendril_purge_user_statistics_log
    on schedule every 15 minute starts date(now()) + interval 4 minute
    do begin
        select @stamp := now() - interval 7 day;
        delete from user_statistics_log where stamp < @stamp limit 1000000;
    end ;;

drop event if exists tendril_purge_slave_status_log;
create event tendril_purge_slave_status_log
    on schedule every 15 minute starts date(now()) + interval 5 minute
    do begin
        select @stamp := now() - interval 7 day;
        delete from slave_status_log where stamp < @stamp limit 1000000;
    end ;;

drop event if exists tendril_purge_innodb_trx_log;
create event tendril_purge_innodb_trx_log
    on schedule every 15 minute starts date(now()) + interval 6 minute
    do begin
        select @stamp := now() - interval 7 day;
        delete from innodb_trx_log where trx_started < @stamp limit 1000000;
    end ;;

drop event if exists tendril_purge_processlist_query_log;
create event tendril_purge_processlist_query_log
    on schedule every 15 minute starts date(now()) + interval 7 minute
    do begin

        select @stamp := now() - interval 3 day;
        delete from processlist_query_log where stamp < @stamp limit 1000000;
        select @stamp := now() - interval 1 day;
        delete from processlist_query_log where stamp < @stamp and time < 5;

    end ;;

drop event if exists tendril_purge_general_log_sampled;
create event tendril_purge_general_log_sampled
    on schedule every 15 minute starts date(now()) + interval 8 minute
    do begin
        select @stamp := now() - interval 7 day;
        delete from general_log_sampled where event_time < @stamp limit 1000000;
    end ;;

drop event if exists tendril_purge_slow_log_sampled;
create event tendril_purge_slow_log_sampled
    on schedule every 15 minute starts date(now()) + interval 9 minute
    do begin
        select @stamp := now() - interval 7 day;
        delete from slow_log_sampled where start_time < @stamp limit 1000000;
    end ;;

drop event if exists tendril_purge_queries;
create event tendril_purge_queries
    on schedule every 15 minute starts date(now()) + interval 10 minute
    do begin
        select @stamp := now() - interval 7 day;
        delete from queries where last_seen < @stamp limit 1000000;
    end ;;

drop event if exists tendril_purge_queries_seen_log;
create event tendril_purge_queries_seen_log
    on schedule every 15 minute starts date(now()) + interval 11 minute
    do begin
        select @stamp := now() - interval 7 day;
        delete from queries_seen_log where stamp < @stamp limit 1000000;
    end ;;

drop event if exists tendril_purge_processlist_query_log_2;;
drop event if exists tendril_slave_status_logger;;

delimiter ;
