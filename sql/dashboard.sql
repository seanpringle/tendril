
drop event if exists tendril_global_status_log_5m;
drop event if exists tendril_purge_global_status_log_5m;

delimiter ;;

  create event tendril_global_status_log_5m
  on schedule every 5 minute starts date(now())
  do begin

    if (get_lock('tendril_global_status_log_5m', 1) = 0) then
      signal sqlstate value '45000' set message_text = 'get_lock';
    end if;

    insert into global_status_log_5m (server_id, stamp, name_id, value)
      select server_id, now(), n.id, gs.variable_value from global_status gs
        join strings n on gs.variable_name = n.string
          where gs.variable_value regexp '^[0-9\.]+';

    do release_lock('tendril_global_status_log_5m');
  end ;;

  create event tendril_purge_global_status_log_5m
  on schedule every 5 minute starts date(now())
  do begin

    if (get_lock('tendril_purge_global_status_log_5m', 1) = 0) then
      signal sqlstate value '45000' set message_text = 'get_lock';
    end if;

    select @stamp := now() - interval 7 day;
    delete from global_status_log_5m where stamp < @stamp limit 10000;

    do release_lock('tendril_purge_global_status_log_5m');
  end ;;

  delimiter ;

