
DROP TABLE IF EXISTS global_status_log_5m;
CREATE TABLE global_status_log_5m (
  server_id int(10) unsigned NOT NULL,
  stamp datetime NOT NULL,
  name_id int(10) unsigned NOT NULL,
  value double NOT NULL DEFAULT '0',
  INDEX i1 (server_id, name_id, stamp),
  INDEX i2 (stamp, name_id, server_id)
) ENGINE=InnoDB;

drop event if exists tendril_global_status_log_5m;
drop event if exists tendril_purge_global_status_log_5m;

delimiter ;;

  create event tendril_global_status_log_5m
  on schedule every 5 minute starts date(now())
  do begin

    if (get_lock('tendril_global_status_log_5m', 1) = 0) then
      signal sqlstate value '45000' set message_text = 'get_lock';
    end if;

    select @stamp := now() - interval 5 minute;

    create temporary table t1 as
      select
        server_id,
        max(stamp) as stamp,
        name_id
      from global_status_log
      where stamp > @stamp
      group by server_id, name_id
      order by null;

    insert ignore into global_status_log_5m
      select t1.*, l.value from t1 join global_status_log l
        on t1.server_id = l.server_id and t1.name_id = l.name_id and t1.stamp = l.stamp;

    drop table t1;

    do release_lock('tendril_global_status_log_5m');
  end ;;

  create event tendril_purge_global_status_log_5m
  on schedule every 5 minute starts date(now())
  do begin

    if (get_lock('tendril_purge_global_status_log_5m', 1) = 0) then
      signal sqlstate value '45000' set message_text = 'get_lock';
    end if;

    select @stamp := now() - interval 7 day;
    delete from global_status_log_5m where stamp < @stamp;

    do release_lock('tendril_purge_global_status_log_5m');
  end ;;

  delimiter ;

