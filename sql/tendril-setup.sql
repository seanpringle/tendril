
delimiter ;;

drop event if exists tendril_purge_queries_seen_log;;
create event tendril_purge_queries_seen_log
    on schedule every 1 hour starts date(now()) + interval 8 minute
    do begin

        select @days := to_days(now() - interval (p.days+1) day)
            from purge_schedule p where p.table_name = 'queries_seen_log';

        select @partition := count(*) from information_schema.partitions
            where table_schema = database() and table_name = 'queries_seen_log'
                and partition_name = concat('p',@days);

        if (@partition = 1) then

            set @sql := concat(
                'alter table queries_seen_log drop partition p',@days
            );

            prepare stmt from @sql; execute stmt; deallocate prepare stmt;

            insert into event_log values (now(), @sql);

        end if;
    end ;;

drop event if exists tendril_extend_queries_seen_log;;
create event tendril_extend_queries_seen_log
    on schedule every 1 hour starts date(now()) + interval 7 minute
    do begin

        set @days := to_days(now())+1;

        select @partition := count(*) from information_schema.partitions
            where table_schema = database() and table_name = 'queries_seen_log'
                and partition_name = concat('p',@days);

        if (@partition = 0) then

            set @sql := concat(
                'alter table queries_seen_log add partition (partition p',@days,' values less than (',(@days+1),'))'
            );

            prepare stmt from @sql; execute stmt; deallocate prepare stmt;

            insert into event_log values (now(), @sql);

        end if;
    end ;;


drop event if exists tendril_purge_processlist_query_log;;
create event tendril_purge_processlist_query_log
    on schedule every 1 hour starts date(now()) + interval 8 minute
    do begin

        select @days := to_days(now() - interval (p.days+1) day)
            from purge_schedule p where p.table_name = 'processlist_query_log';

        select @partition := count(*) from information_schema.partitions
            where table_schema = database() and table_name = 'processlist_query_log'
                and partition_name = concat('p',@days);

        if (@partition = 1) then

            set @sql := concat(
                'alter table processlist_query_log drop partition p',@days
            );

            prepare stmt from @sql; execute stmt; deallocate prepare stmt;

            insert into event_log values (now(), @sql);

        end if;
    end ;;

drop event if exists tendril_extend_processlist_query_log;;
create event tendril_extend_processlist_query_log
    on schedule every 1 hour starts date(now()) + interval 7 minute
    do begin

        set @days := to_days(now())+1;

        select @partition := count(*) from information_schema.partitions
            where table_schema = database() and table_name = 'processlist_query_log'
                and partition_name = concat('p',@days);

        if (@partition = 0) then

            set @sql := concat(
                'alter table processlist_query_log add partition (partition p',@days,' values less than (',(@days+1),'))'
            );

            prepare stmt from @sql; execute stmt; deallocate prepare stmt;

            insert into event_log values (now(), @sql);

        end if;
    end ;;


drop event if exists tendril_purge_global_status_log;;
create event tendril_purge_global_status_log
    on schedule every 1 hour starts date(now()) + interval 6 minute
    do begin

        select @days := to_days(now() - interval (p.days+1) day)
            from purge_schedule p where p.table_name = 'global_status_log';

        select @partition := count(*) from information_schema.partitions
            where table_schema = database() and table_name = 'global_status_log'
                and partition_name = concat('p',@days);

        if (@partition = 1) then

            set @sql := concat(
                'alter table global_status_log drop partition p',@days
            );

            prepare stmt from @sql; execute stmt; deallocate prepare stmt;

            insert into event_log values (now(), @sql);

        end if;
    end ;;

drop event if exists tendril_extend_global_status_log;;
create event tendril_extend_global_status_log
    on schedule every 1 hour starts date(now()) + interval 5 minute
    do begin

        set @days := to_days(now())+1;

        select @partition := count(*) from information_schema.partitions
            where table_schema = database() and table_name = 'global_status_log'
                and partition_name = concat('p',@days);

        if (@partition = 0) then

            set @sql := concat(
                'alter table global_status_log add partition (partition p',@days,' values less than (',(@days+1),'))'
            );

            prepare stmt from @sql; execute stmt; deallocate prepare stmt;

            insert into event_log values (now(), @sql);

        end if;
    end ;;


drop event if exists tendril_purge_innodb_trx_log;;
create event tendril_purge_innodb_trx_log
    on schedule every 1 hour starts date(now()) + interval 4 minute
    do begin

        select @days := to_days(now() - interval (p.days+1) day)
            from purge_schedule p where p.table_name = 'innodb_trx_log';

        select @partition := count(*) from information_schema.partitions
            where table_schema = database() and table_name = 'innodb_trx_log'
                and partition_name = concat('p',@days);

        if (@partition = 1) then

            set @sql := concat(
                'alter table innodb_trx_log drop partition p',@days
            );

            prepare stmt from @sql; execute stmt; deallocate prepare stmt;

            insert into event_log values (now(), @sql);

        end if;
    end ;;

drop event if exists tendril_extend_innodb_trx_log;;
create event tendril_extend_innodb_trx_log
    on schedule every 1 hour starts date(now()) + interval 3 minute
    do begin

        set @days := to_days(now())+1;

        select @partition := count(*) from information_schema.partitions
            where table_schema = database() and table_name = 'innodb_trx_log'
                and partition_name = concat('p',@days);

        if (@partition = 0) then

            set @sql := concat(
                'alter table innodb_trx_log add partition (partition p',@days,' values less than (',(@days+1),'))'
            );

            prepare stmt from @sql; execute stmt; deallocate prepare stmt;

            insert into event_log values (now(), @sql);

        end if;
    end ;;


drop event if exists tendril_purge_client_statistics_log;;
create event tendril_purge_client_statistics_log
    on schedule every 1 minute starts date(now()) + interval 10 second
    do begin
        select @stamp := now() - interval 7 day;
        delete from client_statistics_log where stamp < @stamp limit 1000;
    end ;;

drop event if exists tendril_purge_index_statistics_log;;
create event tendril_purge_index_statistics_log
    on schedule every 1 minute starts date(now()) + interval 15 second
    do begin
        select @stamp := now() - interval 7 day;
        delete from index_statistics_log where stamp < @stamp limit 1000;
    end ;;

drop event if exists tendril_purge_table_statistics_log;;
create event tendril_purge_table_statistics_log
    on schedule every 1 minute starts date(now()) + interval 20 second
    do begin
        select @stamp := now() - interval 7 day;
        delete from table_statistics_log where stamp < @stamp limit 1000;
    end ;;

drop event if exists tendril_purge_user_statistics_log;;
create event tendril_purge_user_statistics_log
    on schedule every 1 minute starts date(now()) + interval 25 second
    do begin
        select @stamp := now() - interval 7 day;
        delete from user_statistics_log where stamp < @stamp limit 1000;
    end ;;

drop event if exists tendril_purge_slave_status_log;;
create event tendril_purge_slave_status_log
    on schedule every 1 minute starts date(now()) + interval 30 second
    do begin
        select @stamp := now() - interval 7 day;
        delete from slave_status_log where stamp < @stamp limit 1000;
    end ;;

drop event if exists tendril_purge_innodb_locks_log;;
create event tendril_purge_innodb_locks_log
    on schedule every 1 minute starts date(now()) + interval 35 second
    do begin
        select @stamp := now() - interval (p.days+1) day from purge_schedule p where p.table_name = 'innodb_locks_log';
        delete from innodb_locks_log where stamp < @stamp limit 1000;
    end ;;


drop event if exists tendril_purge_general_log_sampled;;
create event tendril_purge_general_log_sampled
    on schedule every 1 minute starts date(now()) + interval 45 second
    do begin
        select @stamp := now() - interval 7 day;
        delete from general_log_sampled where event_time < @stamp limit 1000;
    end ;;

drop event if exists tendril_purge_slow_log_sampled;;
create event tendril_purge_slow_log_sampled
    on schedule every 1 minute starts date(now()) + interval 50 second
    do begin
        select @stamp := now() - interval 7 day;
        delete from slow_log_sampled where start_time < @stamp limit 1000;
    end ;;

drop event if exists tendril_purge_queries;;
create event tendril_purge_queries
    on schedule every 1 minute starts date(now()) + interval 55 second
    do begin
        select @stamp := now() - interval 7 day;
        delete from queries where last_seen < @stamp limit 1000;
    end ;;


delimiter ;
