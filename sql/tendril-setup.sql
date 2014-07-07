
delimiter ;;

drop event if exists tendril_partition_add;;
create event tendril_partition_add
    on schedule every 1 minute starts date(now()) + interval 0 second
    do begin

        declare tomorrow int default 0;
        declare logtable varchar(100) default null;

        set tomorrow := to_days(now())+1;

        set logtable := (
            select t.table_name from (
                select distinct part.table_name
                from information_schema.partitions part
                join purge_schedule shed
                    on part.table_name = shed.table_name
                where part.table_schema = database()
                    and part.table_name like '%log'
                    and part.partition_method = 'RANGE'
                    and lower(part.partition_expression) like 'to_days%'
            ) t
            left join information_schema.partitions p
                on p.table_schema = database()
                and t.table_name = p.table_name
                and p.table_name like '%log'
                and p.partition_name = concat('p',tomorrow)
            where p.partition_name is null
            limit 1
        );

        if (logtable is not null) then

            set @sql := concat(
                'alter table ',logtable,' add partition (partition p',tomorrow,' values less than (',(tomorrow+1),'))'
            );

            prepare stmt from @sql; execute stmt; deallocate prepare stmt;

            insert into event_log values (now(), @sql);

        end if;
    end ;;

delimiter ;

delimiter ;;

drop event if exists tendril_partition_drop;;
create event tendril_partition_drop
    on schedule every 1 minute starts date(now()) + interval 10 second
    do begin

        declare partname varchar(100) default null;
        declare logtable varchar(100) default null;

        set logtable := (
            select part.table_name
            from information_schema.partitions part
            join purge_schedule shed
                on part.table_name = shed.table_name
            where part.table_schema = database()
                and part.table_name like '%log'
                and part.partition_method = 'RANGE'
                and lower(part.partition_expression) like 'to_days%'
                and part.partition_name = concat('p',to_days(now() - interval shed.days day))
            limit 1
        );

        if (logtable is not null) then

            set partname := (select concat('p',to_days(now() - interval days day))
                from purge_schedule shed where table_name = logtable);

            set @sql := concat(
                'alter table ',logtable,' drop partition ',partname
            );

            prepare stmt from @sql; execute stmt; deallocate prepare stmt;

            insert into event_log values (now(), @sql);

        end if;
    end ;;

delimiter ;

delimiter ;;

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

delimiter ;
