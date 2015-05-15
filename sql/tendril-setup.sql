
delimiter ;;

drop event if exists tendril_partition_add;;
create event tendril_partition_add
    on schedule every 10 minute starts date(now()) + interval 0 second
    do begin

        declare all_done int default 0;
        declare tomorrow int default 0;
        declare logtable varchar(100) default null;

        declare new_partitions cursor for
            select t.table_name from (
                select distinct part.table_name
                from information_schema.partitions part
                join purge_schedule shed
                    on part.table_name = shed.table_name
                where part.table_schema = database()
                    and part.table_name regexp '(log|sampled)$'
                    and part.table_name not regexp '^(db|es|virt|silver)'
                    and part.table_name in (
                        select table_name from information_schema.tables
                            where engine in ('InnoDB', 'TokuDB') and table_schema = 'tendril'
                    )
                    and part.partition_method = 'RANGE'
                    and lower(part.partition_expression) like 'to_days%'
            ) t
            left join information_schema.partitions p
                on p.table_schema = database()
                and t.table_name = p.table_name
                and p.table_name regexp '(log|sampled)$'
                and p.table_name not regexp '^(db|es|virt|silver)'
                and p.partition_name = concat('p',to_days(now())+1)
            where p.partition_name is null;

        declare continue handler for not found set all_done = 1;

        set all_done = 0;
        open new_partitions;

        repeat fetch new_partitions into logtable;

            if (all_done = 0 and logtable is not null) then

                set tomorrow := to_days(now())+1;

                set @sql := concat(
                    'alter table ',logtable,' add partition (partition p',tomorrow,' values less than (',(tomorrow+1),'))'
                );

                prepare stmt from @sql; execute stmt; deallocate prepare stmt;

                insert into event_log values (now(), @sql);

            end if;

            until all_done
        end repeat;

        close new_partitions;

    end ;;

delimiter ;

delimiter ;;

drop event if exists tendril_partition_drop;;
create event tendril_partition_drop
    on schedule every 10 minute starts date(now()) + interval 10 second
    do begin

        declare all_done int default 0;
        declare partname varchar(100) default null;
        declare logtable varchar(100) default null;

        declare old_partitions cursor for
            select part.table_name, part.partition_name
            from information_schema.partitions part
            join purge_schedule shed
                on part.table_name = shed.table_name
            where part.table_schema = database()
                and part.table_name regexp '(log|sampled)$'
                and part.table_name not regexp '^(db|es|virt|silver)'
                and part.table_name in (
                    select table_name from information_schema.tables where engine in ('InnoDB', 'TokuDB') and table_schema = 'tendril'
                )
                and part.partition_method = 'RANGE'
                and lower(part.partition_expression) like 'to_days%'
                and part.partition_name regexp '^p[0-9]+$'
                and cast(substring(part.partition_name,2) as unsigned) <= to_days(now() - interval shed.days day);

        declare continue handler for not found set all_done = 1;

        set all_done = 0;
        open old_partitions;

        repeat fetch old_partitions into logtable, partname;

            if (all_done = 0 and logtable is not null and partname is not null) then

                set @sql := concat(
                    'alter table ',logtable,' drop partition ',partname
                );

                prepare stmt from @sql; execute stmt; deallocate prepare stmt;

                insert into event_log values (now(), @sql);

            end if;

            until all_done
        end repeat;

        close old_partitions;

    end ;;

delimiter ;

