<?php

class Package_Host extends Package
{
    public function process()
    {
        $this->host = null;

        $name = $this->request(0);
        $port = $this->request(1, 'pint', 3306);

        if ($name && $port && ($host = Host::by_name_port($name, $port)) && $host->ok)
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
                list ($host, $graphs, $variables, $status, $grants, $slave_status, $hosts, $versions, $uptimes, $replag, $ram) = $this->data_view();
                include ROOT .'tpl/host/view.php';
                break;

            default:
                list ($hosts, $versions, $uptimes, $replag, $ram) = $this->data_list();
                include ROOT .'tpl/host/list.php';
        }
    }

    private function data_list()
    {
        require_once ROOT .'pkg/report.php';

        $host = $this->request('host');

        $hosts = sql::query('tendril.servers h')
            ->left_join('tendril.servers h2', 'h.m_master_id = h2.m_server_id and h.m_master_port = h2.port')
            ->left_join('tendril.servers h3', 'h.m_server_id = h3.m_master_id and h.port = h3.m_master_port')
            ->field('h2.id as master_id')
            ->field('group_concat(h3.id order by h3.host) as slave_ids')
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
                'h2.id' => $this->host->id,
                'h3.id' => $this->host->id,
            ));
        }

        $hosts = $hosts
            ->group('h.id')
            ->order('h.host')
            ->order('h.port')
            ->fetch_all('id');

        $qps = sql::query('tendril.global_status_log gsl')
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

        $versions = sql::query('tendril.global_variables')
            ->fields('server_id, variable_value')
            ->where_eq('variable_name', 'version')
            ->where_in_if('server_id', array_keys($hosts))
            ->fetch_pair('server_id', 'variable_value');

        $uptimes = sql::query('tendril.global_status')
            ->fields('server_id, variable_value')
            ->where_eq('variable_name', 'uptime')
            ->where_in_if('server_id', array_keys($hosts))
            ->fetch_pair('server_id', 'variable_value');

        $replag = sql::query('tendril.slave_status a')
            ->join('tendril.slave_status b', 'a.server_id = b.server_id')
            ->fields('a.server_id, a.variable_value')
            ->where_eq('a.variable_name', 'seconds_behind_master')
            ->where_eq('b.variable_name', 'slave_sql_running')
            ->where_eq('b.variable_value', 'Yes')
            ->where_in_if('a.server_id', array_keys($hosts))
            ->fetch_pair('server_id', 'variable_value');

        $ram = sql::query('tendril.global_variables')
            // extrapolate ram from buffer pool size
            // puppet $ram calcs seem to have rounding errors, hence 0.732 < 0.75
            ->fields('server_id, variable_value/0.732/1024/1024/1024 as variable_value')
            ->where_eq('variable_name', 'innodb_buffer_pool_size')
            ->where_in_if('server_id', array_keys($hosts))
            ->fetch_pair('server_id', 'variable_value');

        return array( $hosts, $versions, $uptimes, $replag, $ram );
    }

    private function data_view()
    {
        $variables = sql::query('tendril.global_variables')
            ->join('tendril.servers srv', 'server_id = srv.id')
            ->where_eq('srv.host', $this->host->name())
            ->fetch_pair('variable_name', 'variable_value');

        $status = sql::query('tendril.global_status')
            ->join('tendril.servers srv', 'server_id = srv.id')
            ->where_eq('srv.host', $this->host->name())
            ->fetch_pair('variable_name', 'variable_value');

        $slave_status = sql::query('tendril.slave_status')
            ->join('tendril.servers srv', 'server_id = srv.id')
            ->where_eq('srv.host', $this->host->name())
            ->fetch_pair('variable_name', 'variable_value');

        $groups = array(
            'Data Traffic' => 'Bytes_received,Bytes_sent',
            'Query Traffic' => 'Connections,Questions,Com_select',
            'Query Write Traffic' => 'Com_insert,Com_update,Com_delete,Com_replace',
            'Replication' => 'Seconds_Behind_Master',
            'InnoDB Page I/O' => 'Innodb_pages_read,Innodb_pages_written,Innodb_pages_created',
            'InnoDB Disk I/O' => 'Innodb_log_writes,Innodb_data_reads,Innodb_data_writes,Innodb_data_fsyncs',
            'InnoDB Adaptive Hash' => 'Innodb_adaptive_hash_hash_searches,Innodb_adaptive_hash_non_hash_searches',
            'InnoDB Buffer Pool' => 'Innodb_buffer_pool_pages_total,Innodb_buffer_pool_pages_data,Innodb_buffer_pool_pages_dirty,Innodb_buffer_pool_pages_free',
            'InnoDB Purge Lag' => 'Innodb_history_list_length',
            'InnoDB Checkpoint Age' => 'Innodb_checkpoint_age',
            'InnoDB Mutexes' => 'Innodb_mutex_os_waits,Innodb_mutex_spin_rounds,Innodb_mutex_spin_waits',
            'InnoDB Lock OS Waits' => 'Innodb_s_lock_os_waits,Innodb_x_lock_os_waits',
            'InnoDB Lock Spin Rounds' => 'Innodb_s_lock_spin_rounds,Innodb_x_lock_spin_rounds',
            'InnoDB Lock Spin Waits' => 'Innodb_s_lock_spin_waits,Innodb_x_lock_spin_waits',
            'InnoDB Deadlocks' => 'Innodb_deadlocks',
            'Binlog Cache' => 'Binlog_cache_disk_use,Binlog_cache_use',
            'Implicit Temporary Tables' => 'Created_tmp_tables,Created_tmp_disk_tables',
            'Sorting' => 'Sort_range,Sort_scan,Sort_merge_passes,Sort_rows',
            'Accessing' => 'Select_full_join,Select_range,Select_scan',
            'Files' => 'Opened_files',
            'Threads' => 'Threads_cached,Threads_connected',
            'Connection Problems' => 'Aborted_clients,Aborted_connects,Access_denied_errors,Com_kill',
        );

        $graphs = array();
        foreach ($groups as $title => $group)
        {
            $fields = explode(',', $group);
            $graphs[$title] = array(
                new Host_Chart_7day($this->host->name(), $fields),
                new Host_Chart_24hour($this->host->name(), $fields),
            );
        }

        $grants = sql::query('tendril.schema_privileges')
            ->fields(array(
                'grantee',
                'table_schema',
                'group_concat(privilege_type) as privileges',
            ))
            ->join('tendril.servers srv', 'server_id = srv.id')
            ->where_eq('srv.host', $this->host->name())
            ->group('grantee')->group('table_schema')
            ->order('grantee')->order('table_schema')
            ->fetch_all();

        return array_merge(array( $this->host, $graphs, $variables, $status, $grants, $slave_status ), $this->data_list());
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
            if (preg_match('/^(Innodb_(history_list_length|checkpoint.*age|buffer_pool_page.*)|Seconds_Behind_Master)$/', $name))
                $this->mode = 0;
        }
    }

    public function description()
    {
        return '24h / 10m';
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

        $fields = array(
            'now() - interval s.value * 10 minute as x',
        );

        $mode = sprintf('cast(ifnull(%s,0) as unsigned)',
            $this->mode ? 'max(gsl.value) - min(gsl.value)' : 'max(gsl.value)');

        $server_id = $this->host->id;

        $name_ids = sql::query('tendril.strings')
            ->cache(sql::MEMCACHE, 300)
            ->where_in('string', map('strtolower', $this->names))
            ->fetch_pair('string', 'id');

        foreach ($this->names as $i => $name)
        {
            $cols['y'.($i+1)] = array($name, 'number');

            $table = preg_match('/Seconds_Behind_Master/', $name)
                ? 'tendril.slave_status_log': 'tendril.global_status_log';

            $fields[] = sprintf('(%s) as y%d',
                sql::query($table.' gsl')
                    ->fields($mode)
                    ->where('gsl.stamp between x - interval 11 minute and x')
                    ->where_eq('gsl.server_id', $server_id)
                    ->where_eq('gsl.name_id', $name_ids[strtolower($name)])
                    ->where('gsl.stamp > now() - interval 24 hour')
                    ->get_select(),
                $i+1
            );
        }

        $rows = sql::query('sequence s')
            ->cache(sql::MEMCACHE, 300)
            ->where_between('value', 1, 143)
            ->having('x is not null')
            ->fields($fields)
            ->order('value')
            ->fetch_all();

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

        $fields = array(
            'now() - interval value hour as x',
        );

        $mode = sprintf('cast(ifnull(%s,0) as unsigned)',
            $this->mode ? 'max(gsl.value) - min(gsl.value)' : 'max(gsl.value)');

        $server_id = $this->host->id;

        $name_ids = sql::query('tendril.strings')
            ->cache(sql::MEMCACHE, 1800)
            ->where_in('string', map('strtolower', $this->names))
            ->fetch_pair('string', 'id');

        foreach ($this->names as $i => $name)
        {
            $cols['y'.($i+1)] = array($name, 'number');

            $table = preg_match('/Seconds_Behind_Master/', $name)
                ? 'tendril.slave_status_log': 'dbmon.global_status_log_5m';
                //? 'tendril.slave_status_log': 'tendril.global_status_log';

            $fields[] = sprintf('(%s) as y%d',
                sql::query($table.' gsl')
                    ->fields($mode)
                    ->where('gsl.stamp between x - interval 1 hour and x')
                    ->where_eq('gsl.server_id', $server_id)
                    ->where_eq('gsl.name_id', $name_ids[strtolower($name)])
                    ->where('gsl.stamp > now() - interval 7 day')
                    ->get_select(),
                $i+1
            );
        }

        $rows = sql::query('sequence s')
            ->cache(sql::MEMCACHE, 1800)
            ->where_between('value', 1, 167)
            ->having('x is not null')
            ->fields($fields)
            ->order('value')
            ->fetch_all();

        return array( $cols, $rows );
    }
}

