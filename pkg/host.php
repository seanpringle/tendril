<?php

class Package_Host extends Package
{
    public function head()
    {
        if ($this->action() == 'index')
            return $this->head_refresh();
    }

    public function process()
    {
        $this->host = null;

        $name = $this->request(0);
        $port = $this->request(1, 'pint', 3306);

        if ($name && $port && ($host = Server::by_name_port($name, $port)) && $host->ok)
        {
            $this->host = $host;
        }

        if ($this->action() == 'chart')
        {
            $this->view_ajax();
        }
    }

    public function ajax()
    {
        switch ($this->action())
        {
            case 'chart':
                return $this->ajax_chart();
        }
        return array( 'what' );
    }

    public function page()
    {
        switch ($this->action())
        {
            case 'view':
                list ($host, $graphs, $hosts, $versions, $uptimes, $repsql, $replag, $ram) = $this->data_view();
                include ROOT .'tpl/host/view.php';
                break;

            default:
                list ($hosts, $versions, $uptimes, $repsql, $replag, $ram) = $this->data_list();
                include ROOT .'tpl/host/list.php';
        }
    }

    private function data_list()
    {
        require_once ROOT .'pkg/report.php';

        $host = $this->request('host');

        $hosts = sql('tendril.servers h')->fields('h.*')
            ->left_join('tendril.replication rm', 'h.id = rm.server_id')
            ->left_join('tendril.servers hm', 'rm.master_id = hm.id')
            ->left_join('tendril.replication rs', 'h.id = rs.master_id')
            ->left_join('tendril.servers hs', 'rs.server_id = hs.id')
            ->field('group_concat(distinct rm.master_id order by hm.host, hm.port) as master_ids')
            ->field('group_concat(distinct rs.server_id order by hs.host, hs.port) as slave_ids')
            ->field('0 as qps')
            ->field('h.event_activity as contact');

        if ($host)
        {
            $hosts->where_regexp('concat(h.host,":",h.port)', Package_Report::regex_host($host));
        }

        if ($this->host)
        {
            $hosts->where_or(array(
                'h.id'  => $this->host->id,
                'rm.server_id' => $this->host->id,
                'rm.master_id' => $this->host->id,
            ));
        }

        $hosts = $hosts
            ->group('h.id')
            ->order('h.host')
            ->order('h.port')
            ->fetch_all('id');

        $qps = sql('tendril.global_status_log gsl')
            ->fields(array(
                'srv.id',
                'floor((max(value)-min(value))/(unix_timestamp(max(stamp))-unix_timestamp(min(stamp)))) as qps',
            ))
            ->join('tendril.strings str', 'gsl.name_id = str.id')
            ->join('tendril.servers srv', 'gsl.server_id = srv.id')
            ->where_eq('str.string', 'questions')
            ->where('gsl.stamp > now() - interval 10 minute')
            ->where_in_if('server_id', array_keys($hosts))
            ->group('server_id')
            ->fetch_pair('id', 'qps');

        foreach ($qps as $id => $n)
            if (isset($hosts[$id])) $hosts[$id]['qps'] = $n;

        $versions = sql('tendril.global_variables')
            ->fields('server_id, variable_value')
            ->where_eq('variable_name', 'version')
            ->where_in_if('server_id', array_keys($hosts))
            ->fetch_pair('server_id', 'variable_value');

        $uptimes = sql('tendril.global_status')
            ->fields('server_id, variable_value')
            ->where_eq('variable_name', 'uptime')
            ->where_in_if('server_id', array_keys($hosts))
            ->fetch_pair('server_id', 'variable_value');

        $repsql = sql('tendril.slave_status a')
            ->fields(array(
                'a.server_id',
                'group_concat(distinct a.variable_value order by a.variable_value) as variable_value',
            ))
            ->where_like('a.variable_name', '%slave_sql_running')
            ->where_in_if('a.server_id', array_keys($hosts))
            ->group('a.server_id')
            ->fetch_pair('server_id', 'variable_value');

        $replag = sql('tendril.slave_status a')
            ->join('tendril.slave_status b', 'a.server_id = b.server_id')
            ->fields('a.server_id, max(a.variable_value) as variable_value')
            ->where_like('a.variable_name', '%seconds_behind_master')
            ->where_like('b.variable_name', '%slave_sql_running')
            ->where_eq('b.variable_value', 'Yes')
            ->where_in_if('a.server_id', array_keys($hosts))
            ->group('a.server_id')
            ->fetch_pair('server_id', 'variable_value');

        $ram = sql('tendril.global_variables')
            // extrapolate ram from buffer pool size
            // puppet $ram calcs seem to have rounding errors, hence 0.732 < 0.75
            ->fields('server_id, variable_value/0.732/1024/1024/1024 as variable_value')
            ->where_eq('variable_name', 'innodb_buffer_pool_size')
            ->where_in_if('server_id', array_keys($hosts))
            ->fetch_pair('server_id', 'variable_value');

        return array( $hosts, $versions, $uptimes, $repsql, $replag, $ram );
    }

    private function data_view()
    {
        $groups = array(
            'Data Traffic' => 'Bytes_received,Bytes_sent',
            'Query Traffic' => 'Connections,Questions,Com_select',
            'Query Write Traffic' => 'Com_insert,Com_update,Com_delete,Com_replace',
            'Replication' => 'Seconds_Behind_Master',
            'Sorting' => 'Sort_range,Sort_scan,Sort_merge_passes,Sort_rows',
            'Accessing' => 'Select_full_join,Select_range,Select_scan',
            'Files' => 'Opened_files',
            'Implicit Temporary Tables' => 'Created_tmp_tables,Created_tmp_disk_tables',
            'Threads' => 'Threads_cached,Threads_connected',
            'Connection Problems' => 'Aborted_clients,Aborted_connects,Access_denied_errors,Com_kill',
            'InnoDB Page I/O' => 'Innodb_pages_read,Innodb_pages_written,Innodb_pages_created',
            'InnoDB Disk I/O' => 'Innodb_log_writes,Innodb_data_reads,Innodb_data_writes,Innodb_data_fsyncs',
            'InnoDB Adaptive Hash' => 'Innodb_adaptive_hash_hash_searches,Innodb_adaptive_hash_non_hash_searches',
            'InnoDB Buffer Pool' => 'Innodb_buffer_pool_pages_total,Innodb_buffer_pool_pages_data,Innodb_buffer_pool_pages_dirty,Innodb_buffer_pool_pages_free',
            'InnoDB Purge Lag' => 'Innodb_history_list_length',
            'InnoDB Checkpoint Age' => 'Innodb_checkpoint_max_age,Innodb_checkpoint_age',
            'InnoDB Mutexes' => 'Innodb_mutex_os_waits,Innodb_mutex_spin_rounds,Innodb_mutex_spin_waits',
            'InnoDB Lock OS Waits' => 'Innodb_s_lock_os_waits,Innodb_x_lock_os_waits',
            'InnoDB Lock Spin Rounds' => 'Innodb_s_lock_spin_rounds,Innodb_x_lock_spin_rounds',
            'InnoDB Lock Spin Waits' => 'Innodb_s_lock_spin_waits,Innodb_x_lock_spin_waits',
            'TokuDB Databases' => 'Tokudb_db_opens,Tokudb_db_closes',
            'TokuDB Cachetable' => 'Tokudb_cachetable_miss,Tokudb_cachetable_prefetches,Tokudb_cachetable_evictions',
            'TokuDB Cachetable Pressure' => 'Tokudb_cachetable_wait_pressure_count,Tokudb_cachetable_long_wait_pressure_count',
            'TokuDB Locktable' => 'Tokudb_locktree_open_current,Tokudb_locktree_wait_count,Tokudb_locktree_long_wait_count,Tokudb_locktree_timeout_count',
            'TokuDB Buffers' => 'Tokudb_buffers_fetched_target_query,Tokudb_buffers_fetched_prelocked_range,Tokudb_buffers_fetched_prefetch,Tokudb_buffers_fetched_for_write',
        );

        $connections = sql('tendril.replication')
            ->where_eq('server_id', $this->host->id)
            ->where_null('connection_name', false)
            ->fields(array(
                'concat(connection_name,".Seconds_Behind_Master") as name',
            ))
            ->fetch_field('name');

        if (count($connections) > 0)
        {
            $groups['Replication'] = join(',', $connections);
        }

        $graphs = array();
        foreach ($groups as $title => $group)
        {
            $fields = explode(',', $group);
            $graphs[$title] = array(
                new Host_Chart_7day($this->host->name(), $fields),
                new Host_Chart_24hour($this->host->name(), $fields),
            );
        }

        return array_merge(array( $this->host, $graphs ), $this->data_list());
    }

    private function ajax_chart()
    {
        $type   = $this->request('type', 'string');
        $fields = $this->request('fields', 'csv');

        if (class_exists($type) && $fields)
        {
            $report = new $type($this->host, $fields);
            return array( 'ok', $report->generate() );
        }

        return array( 'what' );
    }
}

class Host_Chart_24hour
{
    protected $host;
    protected $names = array();
    protected $mode = 1;

    public function __construct($host, $names)
    {
        $this->host = $host;
        $this->names = $names;

        foreach ($names as $name)
        {
            if (preg_match('/^(Innodb_(history_list_length|checkpoint.*age|buffer_pool_page.*)|.*Seconds_Behind_Master)$/', $name))
                $this->mode = 0;
        }
    }

    public function description()
    {
        return '24h / 5m';
    }

    public function fields()
    {
        return join(',', $this->names);
    }

    public function generate()
    {
        $cols = array(
            'x' => array('Hour', 'datetime'),
        );

        $server_id = $this->host->id;

        $name_ids = sql('tendril.strings')
            ->cache(sql::MEMCACHE, 300)
            ->where_in('string', map('strtolower', $this->names))
            ->fetch_pair('string', 'id');

        $inner = sql('seq_1_to_287 s')
            ->fields('now() - interval seq * 5 minute as x')
            ->left_join('tendril.global_status_log gsl',
                sprintf('gsl.server_id = %d and gsl.stamp > now() - interval 24 hour', $server_id))
            ->where_in('gsl.name_id', $name_ids)
            ->where_between('gsl.stamp',
                text('now() - interval seq * 5 minute - interval 5 minute'),
                text('now() - interval seq * 5 minute'))
            ->group('x');

        $outer = sql()
            ->from('(select now() - interval seq * 5 minute as x from seq_1_to_143)', 'o')
            ->cache(sql::MEMCACHE, 300)
            ->fields('o.x')
            ->order('x');

        foreach ($this->names as $i => $name)
        {
            $cols['y'.($i+1)] = array($name, 'number');
            $id = $name_ids[strtolower($name)];

            $outer->field(
                sprintf('ifnull(i.y%d,0) as y%d', ($i+1), ($i+1))
            );

            if ($this->mode)
            {
                $inner->field(
                    sprintf("cast(ifnull(max(if(name_id = %d,cast(value as unsigned),0)),0) -"
                        ." ifnull(min(if(name_id = %d,cast(value as unsigned),null)),0) as unsigned) as %s",
                        $id, $id, 'y'.($i+1)
                    )
                );
            }
            else
            {
                $inner->field(
                    sprintf("cast(ifnull(max(if(name_id = %d,cast(value as unsigned),0)),0) as unsigned) as %s",
                        $id, 'y'.($i+1)
                    )
                );
            }
        }

        $rows = $outer->left_join(
            sprintf('(%s) as i', $inner->get_select()),
            'o.x = i.x')
        ->fetch_all();

        foreach ($rows as $i => $row)
            $rows[$i] = $row->export();

/*
select
    now() - interval seq * 10 minute as x,

    ifnull(max(if(name_id = 76,cast(value as unsigned),0)),0)
        - ifnull(min(if(name_id = 76,cast(value as unsigned),null)),0) as y1,

    ifnull(max(if(name_id = 100,cast(value as unsigned),0)),0)
        - ifnull(min(if(name_id = 100,cast(value as unsigned),null)),0) as y2

from seq_1_to_143 s

left join tendril.global_status_log gsl1
    on  gsl1.server_id = 1082
    and gsl1.stamp > @stamp

where gsl1.server_id = 1082
    and gsl1.stamp between
        now() - interval seq * 10 minute - interval 11 minute
        and now() - interval seq * 10 minute

    and gsl1.name_id in (76, 100)

group by x
order by seq asc;
*/
        return array( $cols, $rows );
    }
}

class Host_Chart_7day extends Host_Chart_24hour
{
    public function __construct($host, $names)
    {
        parent::__construct($host, $names);
    }

    public function description()
    {
        return '7d / 1h';
    }

    public function generate()
    {
        $cols = array(
            'x' => array('Hour', 'datetime'),
        );

        $server_id = $this->host->id;

        $name_ids = sql('tendril.strings')
            ->cache(sql::MEMCACHE, 1800)
            ->where_in('string', map('strtolower', $this->names))
            ->fetch_pair('string', 'id');

        $inner = sql('seq_1_to_167 s')
            ->fields('now() - interval seq hour as x')
            ->left_join('tendril.global_status_log gsl',
                sprintf('gsl.server_id = %d and gsl.stamp > now() - interval 7 day', $server_id))
            ->where_in('gsl.name_id', $name_ids)
            ->where_between('gsl.stamp',
                text('now() - interval seq hour - interval 1 hour'),
                text('now() - interval seq hour'))
            ->group('x');

        $outer = sql()
            ->from('(select now() - interval seq hour as x from seq_1_to_167)', 'o')
            ->cache(sql::MEMCACHE, 1800)
            ->fields('o.x')
            ->order('x');

        foreach ($this->names as $i => $name)
        {
            $cols['y'.($i+1)] = array($name, 'number');
            $id = $name_ids[strtolower($name)];

            $outer->field(
                sprintf('ifnull(i.y%d,0) as y%d', ($i+1), ($i+1))
            );

            if ($this->mode)
            {
                $inner->field(
                    sprintf("cast(ifnull(max(if(name_id = %d,cast(value as unsigned),0)),0) -"
                        ." ifnull(min(if(name_id = %d,cast(value as unsigned),null)),0) as unsigned) as %s",
                        $id, $id, 'y'.($i+1)
                    )
                );
            }
            else
            {
                $inner->field(
                    sprintf("cast(ifnull(max(if(name_id = %d,cast(value as unsigned),0)),0) as unsigned) as %s",
                        $id, 'y'.($i+1)
                    )
                );
            }
        }

        $rows = $outer->left_join(
            sprintf('(%s) as i', $inner->get_select()),
            'o.x = i.x')
        ->fetch_all();

        foreach ($rows as $i => $row)
            $rows[$i] = $row->export();

        return array( $cols, $rows );
    }
}

