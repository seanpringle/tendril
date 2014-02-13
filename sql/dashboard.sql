
DROP TABLE IF EXISTS global_status_log_1d;
CREATE TABLE global_status_log_1d (
  server_id int(10) unsigned NOT NULL,
  stamp timestamp NOT NULL,
  name_id int(10) unsigned NOT NULL,
  value double NOT NULL DEFAULT '0',
  INDEX server_name_stamp_value (server_id, name_id, stamp, value),
  INDEX stamp (stamp)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS global_status_log_7d;
CREATE TABLE global_status_log_7d (
  server_id int(10) unsigned NOT NULL,
  stamp timestamp NOT NULL,
  name_id int(10) unsigned NOT NULL,
  value double NOT NULL DEFAULT '0',
  INDEX server_name_stamp_value (server_id, name_id, stamp, value),
  INDEX stamp (stamp)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS global_status_log_5m;
CREATE TABLE global_status_log_5m (
  server_id int(10) unsigned NOT NULL,
  stamp datetime NOT NULL,
  name_id int(10) unsigned NOT NULL,
  value double NOT NULL DEFAULT '0',
  INDEX i1 (server_id, name_id, stamp),
  INDEX i2 (stamp, name_id, server_id)
) ENGINE=InnoDB;

drop event if exists insert_global_status_log_1d;
drop event if exists delete_global_status_log_1d;
drop event if exists insert_global_status_log_7d;
drop event if exists delete_global_status_log_7d;
drop event if exists insert_global_status_log_5m;
drop event if exists delete_global_status_log_5m;

delimiter ;;

create event insert_global_status_log_1d
  on schedule every 5 minute starts date(now())
  do begin

    if (get_lock('insert_global_status_log_1d', 1) = 0) then
      signal sqlstate value '45000' set message_text = 'get_lock';
    end if;

    create temporary table t1 as
      select
        server_id,
        max(stamp) as stamp,
        name_id
      from tendril.global_status_log
      where stamp > now() - interval 5 minute
      group by server_id, name_id
      order by null;

    insert ignore into global_status_log_1d
      select t1.*, l.value from t1 join tendril.global_status_log l
        on t1.server_id = l.server_id and t1.name_id = l.name_id and t1.stamp = l.stamp;

    drop table t1;

    do release_lock('insert_global_status_log_1d');
  end ;;

  create event delete_global_status_log_1d
  on schedule every 5 minute starts date(now())
  do begin

    if (get_lock('delete_global_status_log_1d', 1) = 0) then
      signal sqlstate value '45000' set message_text = 'get_lock';
    end if;

    delete from global_status_log_1d where stamp < now() - interval 1 day;

    do release_lock('delete_global_status_log_1d');
  end ;;

  create event insert_global_status_log_7d
  on schedule every 30 minute starts date(now())
  do begin

    if (get_lock('insert_global_status_log_7d', 1) = 0) then
      signal sqlstate value '45000' set message_text = 'get_lock';
    end if;

    create temporary table t1 as
      select
        server_id,
        max(stamp) as stamp,
        name_id
      from tendril.global_status_log
      where stamp > now() - interval 30 minute
      group by server_id, name_id
      order by null;

    insert ignore into global_status_log_7d
      select t1.*, l.value from t1 join tendril.global_status_log l
        on t1.server_id = l.server_id and t1.name_id = l.name_id and t1.stamp = l.stamp;

    drop table t1;

    do release_lock('insert_global_status_log_7d');
  end ;;

  create event delete_global_status_log_7d
  on schedule every 30 minute starts date(now())
  do begin

    if (get_lock('delete_global_status_log_7d', 1) = 0) then
      signal sqlstate value '45000' set message_text = 'get_lock';
    end if;

    delete from global_status_log_7d where stamp < now() - interval 7 day;

    do release_lock('delete_global_status_log_7d');
  end ;;

  create event insert_global_status_log_5m
  on schedule every 5 minute starts date(now())
  do begin

    if (get_lock('insert_global_status_log_5m', 1) = 0) then
      signal sqlstate value '45000' set message_text = 'get_lock';
    end if;

    create temporary table t1 as
      select
        server_id,
        max(stamp) as stamp,
        name_id
      from tendril.global_status_log
      where stamp > now() - interval 5 minute
      group by server_id, name_id
      order by null;

    insert ignore into global_status_log_5m
      select t1.*, l.value from t1 join tendril.global_status_log l
        on t1.server_id = l.server_id and t1.name_id = l.name_id and t1.stamp = l.stamp;

    drop table t1;

    do release_lock('insert_global_status_log_5m');
  end ;;

  create event delete_global_status_log_5m
  on schedule every 5 minute starts date(now())
  do begin

    if (get_lock('delete_global_status_log_5m', 1) = 0) then
      signal sqlstate value '45000' set message_text = 'get_lock';
    end if;

    delete from global_status_log_5m where stamp < now() - interval 7 day;

    do release_lock('delete_global_status_log_5m');
  end ;;

  delimiter ;