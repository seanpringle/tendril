<?php

class Package_Report extends Package
{
    const EXPIRE = 3600;

    public function page()
    {
        switch ($this->action())
        {
            case 'table_find':
                list ($rows) = $this->data_table_find();
                include ROOT .'tpl/report/table_find.php';
                break;

            case 'table_missing':
                list ($rows) = $this->data_table_missing();
                include ROOT .'tpl/report/table_missing.php';
                break;

            case 'table_status':
                list ($rows) = $this->data_table_status();
                include ROOT .'tpl/report/table_status.php';
                break;

            case 'column_find':
                list ($rows) = $this->data_column_find();
                include ROOT .'tpl/report/column_find.php';
                break;

            case 'column_missing':
                list ($rows) = $this->data_column_missing();
                include ROOT .'tpl/report/column_missing.php';
                break;

            case 'index_find':
                list ($rows) = $this->data_index_find();
                include ROOT .'tpl/report/index_find.php';
                break;

            case 'index_missing':
                list ($rows) = $this->data_index_missing();
                include ROOT .'tpl/report/index_missing.php';
                break;

            case 'indexes_diff':
                list ($rows) = $this->data_indexes_diff();
                include ROOT .'tpl/report/indexes_diff.php';
                break;

            case 'innodb':
                list ($rows) = $this->data_innodb();
                include ROOT .'tpl/report/innodb.php';
                break;

            case 'slow_queries':
                list ($rows, $dns, $g_cols, $g_rows) = $this->data_slow_queries();
                include ROOT .'tpl/report/slow_queries.php';
                break;

            case 'slow_queries_checksum':
                list ($rows, $dns, $g_cols, $g_rows) = $this->data_slow_queries_checksum();
                include ROOT .'tpl/report/slow_queries_checksum.php';
                break;

            case 'sampled_queries':
                list ($rows, $dns, $g_cols, $g_rows) = $this->data_sampled_queries();
                include ROOT .'tpl/report/sampled_queries.php';
                break;

            case 'sampled_queries_footprint':
                list ($rows, $dns, $g_cols, $g_rows) = $this->data_sampled_queries_footprint();
                include ROOT .'tpl/report/sampled_queries_footprint.php';
                break;

            case 'schemas':
                list ($rows) = $this->data_schemas();
                include ROOT .'tpl/report/schemas.php';
                break;

            case 'clusters':
                list ($rows) = $this->data_clusters();
                include ROOT .'tpl/report/clusters.php';
                break;

            case 'row_distribution':
                list ($query) = $this->data_row_distribution();
                include ROOT .'tpl/report/row_distribution.php';
                break;

            case 'processlist':
                list ($rows, $dns) = $this->data_processlist();
                include ROOT .'tpl/report/processlist.php';
                break;

            case 'trxlist':
                list ($rows, $dns) = $this->data_trxlist();
                include ROOT .'tpl/report/trxlist.php';
                break;

            default:
                include ROOT .'tpl/report/list.php';
        }
    }

    public static function format_csv($text)
    {
        $dbs = explode(',', $text);
        if (count($dbs) > 10)
        {
            $count = count($dbs) - 10;
            $dbs = array_slice($dbs, 0, 9);

            return sprintf('%s (%d more)', join(', ', $dbs), $count);
        }

        return join(', ', $dbs);
    }

    private function schemata_ignore()
    {
        return sql('tendril.schemata_ignore')->fetch_field('schema_name');
    }

    public function regex_host($text)
    {
        $text = preg_replace('/^(masters|slaves|family):/', '^\\1:', $text);

        $text = preg_replace_callback('/masters/',
            function($match) {
                $hosts = sql('tendril.servers')
                    ->where('id in (select master_id from replication)')
                    ->where('id not in (select server_id from replication)')
                    ->fields('concat(host,":",port) as h')
                    ->fetch_field('h');
                return sprintf('(%s)', join('|', $hosts));
            },
            $text
        );
        $text = preg_replace_callback('/slaves:([a-z0-9.]+)/',
            function($match) {
                $mid = sql('tendril.servers')
                    ->where_like('host', $match[1].'%')
                    ->fields('id')
                    ->fetch_value();
                $hosts = sql('tendril.replication rep')
                    ->join('servers srv', 'rep.server_id = srv.id')
                    ->where_eq('rep.master_id', $mid)
                    ->fields('concat(srv.host,":",srv.port) as h')
                    ->fetch_field('h');
                return sprintf('(%s)', join('|', $hosts));
            },
            $text
        );
        $text = preg_replace_callback('/family:([a-z0-9.]+)/',
            function($match) {
                list($mid, $host) = sql('tendril.servers')
                    ->where_like('host', $match[1].'%')
                    ->fields(array('id', 'concat(host,":",port) as h'))
                    ->fetch_one_numeric();
                $hosts = sql('tendril.replication rep')
                    ->join('servers srv', 'rep.server_id = srv.id')
                    ->where_eq('rep.master_id', $mid)
                    ->fields('concat(srv.host,":",srv.port) as h')
                    ->fetch_field('h');
                return sprintf('(%s|%s)', $host, join('|', $hosts));
            },
            $text
        );
        $text = preg_replace_callback('/slave-per-master/',
            function($match) {

                $masters = sql('tendril.servers')
                    ->where('id in (select master_id from replication)')
                    ->where('id not in (select server_id from replication)')
                    ->fields('id')
                    ->fetch_field('id');

                $slaves = sql('tendril.servers')
                    ->where('id in (select server_id from replication)')
                    ->where_not_in_if('id', $masters)
                    ->fields('id')
                    ->fetch_field('id');

                $qps = sql('tendril.global_status_log gsl')
                    ->fields(array(
                        'gsl.server_id',
                        'rep.master_id',
                        'floor((max(value)-min(value))/(unix_timestamp(max(stamp))-unix_timestamp(min(stamp)))) as qps',
                    ))
                    ->join('tendril.strings str', 'gsl.name_id = str.id')
                    ->join('tendril.replication rep', 'gsl.server_id = rep.server_id')
                    ->where_eq('str.string', 'questions')
                    ->where('gsl.stamp > now() - interval 1 hour')
                    ->where_in_if('rep.server_id', $slaves)
                    ->where_in_if('rep.master_id', $masters)
                    ->group('rep.server_id');

                sql::rawquery('drop temporary table if exists qps');
                sql::rawquery(sprintf('create temporary table qps as %s', $qps->get_select()));

                $slave_ids = sql('tendril.servers srv')
                    ->join('qps', 'srv.id = qps.server_id')
                    ->group('qps.master_id')
                    ->fields(array(
                        'cast(substring_index(group_concat(server_id order by qps desc), ",", 1) as unsigned) as slave_id',
                    ))
                    ->fetch_field('slave_id');

                $hosts = sql('tendril.servers')
                    ->where_in('id', $slave_ids)
                    ->fields('concat(host,":",port) as h')
                    ->group('h')
                    ->order('h', 'desc')
                    ->fetch_field('h');

                return sprintf('(%s)', join('|', $hosts));
            },
            $text
        );
        return $text;
    }

    private function data_table_find()
    {
        $rows = array();

        $host   = $this->request('host');
        $schema = $this->request('schema');
        $table  = $this->request('table');

        if ($host || $schema || $table)
        {
            $search = sql('tendril.servers srv')

                ->join('tendril.schemata sch',
                    'srv.id = sch.server_id')

                ->join('tendril.tables tab',
                    'sch.server_id = tab.server_id and sch.schema_name = tab.table_schema')

                ->where_not_in('sch.schema_name', $this->schemata_ignore())

                ->group('srv.id')
                ->order('srv.host')
                ->order('srv.port')

                ->fields(array(
                    'srv.*',
                    'group_concat(distinct sch.schema_name order by sch.schema_name) as schema_names',
                    'group_concat(distinct tab.table_name order by tab.table_name) as table_names',
                ));

            if ($host)
            {
                $search->where_regexp('concat(srv.host,":",srv.port)', self::regex_host($host));
            }

            if ($schema)
            {
                $search->where_regexp('sch.schema_name', $schema);
            }

            if ($table)
            {
                $search->where_regexp('tab.table_name', $table);
            }

            $rows = $search->fetch_all();
        }

        return array( $rows );
    }

    private function data_table_missing()
    {
        $rows = array();

        $host   = $this->request('host');
        $schema = $this->request('schema');
        $table  = $this->request('table');

        if ($table)
        {
            $search = sql('tendril.servers srv')

                ->join('tendril.schemata sch',
                    'srv.id = sch.server_id')

                ->left_join('tendril.tables tab',
                    'sch.server_id = tab.server_id and sch.schema_name = tab.table_schema'
                    .' and tab.table_name = '.sql::quote($table))

                ->where_null('tab.table_name')

                ->where_not_in('sch.schema_name', $this->schemata_ignore())

                ->group('srv.id')
                ->order('srv.host')
                ->order('srv.port')

                ->fields(array(
                    'srv.*',
                    'group_concat(distinct sch.schema_name order by sch.schema_name) as schema_names',
                    'group_concat(distinct tab.table_name order by tab.table_name) as table_names',
                ));

            if ($host)
            {
                $search->where_regexp('concat(srv.host,":",srv.port)', self::regex_host($host));
            }

            if ($schema)
            {
                $search->where_regexp('sch.schema_name', $schema);
            }

            $rows = $search->fetch_all();
        }

        return array( $rows );
    }

    private function data_table_status()
    {
        $rows = array();

        $host   = $this->request('host');
        $schema = $this->request('schema');
        $table  = $this->request('table');
        $engine = $this->request('engine');
        $data   = $this->request('data',  'float', 0);
        $index  = $this->request('index', 'float', 0);

        if ($host || $schema || $table)
        {
            $search = sql('tendril.servers srv')

                ->join('tendril.schemata sch',
                    'srv.id = sch.server_id')

                ->join('tendril.tables tab',
                    'sch.server_id = tab.server_id and sch.schema_name = tab.table_schema')

                ->where_not_in('sch.schema_name', $this->schemata_ignore())

                ->order('srv.host')
                ->order('srv.port')
                ->order('sch.schema_name')
                ->order('tab.table_name')
                ->order('tab.data_length')

                ->fields(array(
                    'srv.id as server_id',
                    'tab.*',
                    'tab.data_length/1024/1024/1024 as data_length_gb',
                    'tab.index_length/1024/1024/1024 as index_length_gb',
                ));

            if ($host)
            {
                $search->where_regexp('concat(srv.host,":",srv.port)', self::regex_host($host));
            }

            if ($schema)
            {
                $search->where_regexp('sch.schema_name', $schema);
            }

            if ($table)
            {
                $search->where_regexp('tab.table_name', $table);
            }

            if ($engine)
            {
                $search->where_regexp('tab.engine', $engine);
            }

            if ($data)
            {
                $search->where_gt('tab.data_length', $data*1024*1024*1024);
            }

            if ($index)
            {
                $search->where_gt('tab.index_length', $index*1024*1024*1024);
            }

            $rows = $search->fetch_all();
        }

        return array( $rows );
    }

    private function data_column_find()
    {
        $rows = array();

        $host   = $this->request('host');
        $schema = $this->request('schema');
        $table  = $this->request('table');
        $column = $this->request('column');

        if ($host || $schema || $table || $column)
        {
            $search = sql('tendril.servers srv')

                ->join('tendril.schemata sch',
                    'srv.id = sch.server_id')

                ->join('tendril.columns col',
                    'sch.server_id = col.server_id and sch.schema_name = col.table_schema')

                ->where_not_in('sch.schema_name', $this->schemata_ignore())

                ->group('srv.id')
                ->order('srv.host')
                ->order('srv.port')

                ->fields(array(
                    'srv.*',
                    'group_concat(distinct sch.schema_name order by sch.schema_name) as schema_names',
                    'group_concat(distinct col.table_name  order by col.table_name)  as table_names',
                    'group_concat(distinct col.column_name order by col.column_name) as column_names',
                ));

            if ($host)
            {
                $search->where_regexp('concat(srv.host,":",srv.port)', self::regex_host($host));
            }

            if ($schema)
            {
                $search->where_regexp('sch.schema_name', $schema);
            }

            if ($table)
            {
                $search->where_regexp('col.table_name', $table);
            }

            if ($column)
            {
                $search->where_regexp('col.column_name', $column);
            }

            $rows = $search->fetch_all();
        }

        return array( $rows );
    }

    private function data_column_missing()
    {
        $rows = array();

        $host   = $this->request('host');
        $schema = $this->request('schema');
        $table  = $this->request('table');
        $column = $this->request('column');

        if ($table && $column)
        {
            $search = sql('tendril.servers srv')

                ->join('tendril.schemata sch',
                    'srv.id = sch.server_id')

                ->left_join('tendril.columns col',
                    'sch.server_id = col.server_id and sch.schema_name = col.table_schema'
                    .' and table_name = '.sql::quote($table).' and column_name = '.sql::quote($column))
                ->where_null('column_name')

                ->where_not_in('sch.schema_name', $this->schemata_ignore())

                ->group('srv.id')
                ->order('srv.host')
                ->order('srv.port')

                ->fields(array(
                    'srv.*',
                    'group_concat(sch.schema_name order by sch.schema_name) as schema_names',
                ));

            if ($host)
            {
                $host_ids = sql('tendril.servers srv')->fields('srv.id')
                    ->where_regexp('concat(srv.host,":",srv.port)', self::regex_host($host))
                    ->fetch_field('id');
                $search->where_in('srv.id', $host_ids ? $host_ids: array(0));
            }

            if ($schema)
            {
                $search->where_regexp('sch.schema_name', $schema);
            }

            $rows = $search->fetch_all();
        }

        return array( $rows );
    }

    private function data_index_find()
    {
        $rows = array();

        $host   = $this->request('host');
        $schema = $this->request('schema');
        $table  = $this->request('table');
        $index  = $this->request('index');

        if ($table && $index)
        {
            $search = sql('tendril.servers srv')

                ->join('tendril.schemata sch',
                    'srv.id = sch.server_id')

                ->join('tendril.statistics stat',
                    'sch.server_id = stat.server_id and sch.schema_name = stat.table_schema')

                ->where_not_in('sch.schema_name', $this->schemata_ignore())

                ->where_not_in('stat.index_name',
                    array('PRIMARY'))

                ->group('srv.id')
                ->order('srv.host')
                ->order('srv.port')

                ->fields(array(
                    'srv.*',
                    'group_concat(distinct sch.schema_name order by sch.schema_name) as schema_names',
                    'group_concat(distinct stat.table_name order by stat.table_name) as table_names',
                    'group_concat(distinct stat.index_name order by stat.index_name) as index_names',
                ));

            if ($host)
            {
                $search->where_regexp('concat(srv.host,":",srv.port)', self::regex_host($host));
            }

            if ($schema)
            {
                $search->where_regexp('sch.schema_name', $schema);
            }

            if ($table)
            {
                $search->where_regexp('stat.table_name', $table);
            }

            if ($index)
            {
                $search->where_regexp('stat.index_name', $index);
            }

            $rows = $search->fetch_all();
        }

        return array( $rows );
    }
    private function data_index_missing()
    {
        $rows = array();

        $host   = $this->request('host');
        $schema = $this->request('schema');
        $table  = $this->request('table');
        $index  = $this->request('index');

        if ($table && $index)
        {
            $search = sql('tendril.servers srv')

                ->join('tendril.schemata sch',
                    'srv.id = sch.server_id')

                ->join('tendril.tables tab',
                    'sch.server_id = tab.server_id and sch.schema_name = tab.table_schema'
                    .' and tab.table_name = '.sql::quote($table))

                ->left_join('tendril.statistics stat',
                    'sch.server_id = stat.server_id and sch.schema_name = stat.table_schema'
                    .' and stat.table_name = '.sql::quote($table).' and stat.index_name = '.sql::quote($index))

                ->where_null('stat.index_name')

                ->where_not_in('sch.schema_name', $this->schemata_ignore())

                ->group('srv.id')
                ->order('srv.host')
                ->order('srv.port')

                ->fields(array(
                    'srv.*',
                    'group_concat(sch.schema_name order by sch.schema_name) as schema_names',
                ));

            if ($host)
            {
                $search->where_regexp('concat(srv.host,":",srv.port)', self::regex_host($host));
            }

            if ($schema)
            {
                $search->where_regexp('sch.schema_name', $schema);
            }

            $rows = $search->fetch_all();
        }

        return array( $rows );
    }

    private function data_indexes_diff()
    {
        $hostA  = $this->request('a');
        $hostB  = $this->request('b');
        $schema = $this->request('schema');

        $rows = array();

        if ($hostA && $hostB)
        {
            $hA = new Server($hostA);
            $hB = new Server($hostB);

            $searchA = sql('tendril.statistics a')
                ->fields(array(
                    'a.table_schema as schema_name',
                    'a.table_name as table_name',
                    'a.index_name as index_name_a',
                    'b.index_name as index_name_b',
                ))
                ->left_join('tendril.statistics b',
                    'a.table_schema = b.table_schema'
                    .' and a.table_name = b.table_name'
                    .' and a.index_name = b.index_name'
                    .' and b.server_id = ('.$hB->id.')')
                ->where('a.server_id = ('.$hA->id.')')
                ->having('index_name_b is null');

            if ($schema)
            {
                $searchA->where_regexp('a.table_schema', $schema);
            }

            $searchB = sql('tendril.statistics b')
                ->fields(array(
                    'b.table_schema as schema_name',
                    'b.table_name as table_name',
                    'a.index_name as index_name_a',
                    'b.index_name as index_name_b',
                ))
                ->left_join('tendril.statistics a',
                    'b.table_schema = a.table_schema'
                    .' and b.table_name = a.table_name'
                    .' and b.index_name = a.index_name'
                    .' and a.server_id = ('.$hA->id.')')
                ->where('b.server_id = ('.$hB->id.')')
                ->having('index_name_a is null');

            if ($schema)
            {
                $searchB->where_regexp('b.table_schema', $schema);
            }

            $search = 'select * from'
                .' ('.$searchA->get_select() .' union '. $searchB->get_select().') t'
                .' order by schema_name, table_name';

            $rows = sql::command($search)->fetch_all();
        }

        return array( $rows );
    }

    private function data_innodb()
    {
        $host = $this->request('host');

        $string_ids = sql('tendril.strings')
            ->where_in('string', array(
                'innodb_buffer_pool_read_requests',
                'innodb_buffer_pool_reads',
                'innodb_data_read',
                'innodb_data_written',
                'innodb_deadlocks',
                'innodb_s_lock_os_waits',
                'innodb_x_lock_os_waits',
                'innodb_s_lock_spin_waits',
                'innodb_x_lock_spin_waits',
                'innodb_s_lock_spin_rounds',
                'innodb_x_lock_spin_rounds',
            ))
            ->cache(sql::MEMCACHE, self::EXPIRE)
            ->fetch_pair('string', 'id');

        $bpool_reqs_id    = $string_ids['innodb_buffer_pool_read_requests'];
        $bpool_reads_id   = $string_ids['innodb_buffer_pool_reads'];
        $data_read_id     = $string_ids['innodb_data_read'];
        $data_written_id  = $string_ids['innodb_data_written'];
        $deadlocks_id     = $string_ids['innodb_deadlocks'];
        $os_s_waits_id    = $string_ids['innodb_s_lock_os_waits'];
        $os_x_waits_id    = $string_ids['innodb_x_lock_os_waits'];
        $spin_s_waits_id  = $string_ids['innodb_s_lock_spin_waits'];
        $spin_x_waits_id  = $string_ids['innodb_x_lock_spin_waits'];
        $spin_s_rounds_id = $string_ids['innodb_s_lock_spin_rounds'];
        $spin_x_rounds_id = $string_ids['innodb_x_lock_spin_rounds'];

        $search = sql('tendril.servers srv')
            ->fields('srv.id')
            ->where_eq('srv.enabled', 1)
            ->order('srv.host')
            ->order('srv.port');

        if ($host)
        {
            $search->where_regexp('concat(srv.host,":",srv.port)', self::regex_host($host));
        }

        $server_ids = $search->fetch_field('id');
        $searches = array();

        foreach ($server_ids as $server_id)
        {
            $purge = sql('tendril.global_status')
                ->fields('variable_value')->where_eq('server_id', $server_id)
                ->where_eq('variable_name', 'innodb_history_list_length');

            $fpt = sql('tendril.global_variables')
                ->fields('variable_value')->where_eq('server_id', $server_id)
                ->where_eq('variable_name', 'innodb_file_per_table');

            $bps = sql('tendril.global_variables')
                ->fields('variable_value/1024/1024/1024')->where_eq('server_id', $server_id)
                ->where_eq('variable_name', 'innodb_buffer_pool_size');

            $lfs = sql('tendril.global_variables')
                ->fields('variable_value/1024/1024')->where_eq('server_id', $server_id)
                ->where_eq('variable_name', 'innodb_log_file_size');

            $flatc = sql('tendril.global_variables')
                ->fields('variable_value')->where_eq('server_id', $server_id)
                ->where_eq('variable_name', 'innodb_flush_log_at_trx_commit');

            $bphr = sql('tendril.global_status_log gs1')
                ->join('tendril.global_status_log gs2')
                ->where_eq('gs1.server_id', $server_id)
                ->where_eq('gs2.server_id', $server_id)
                ->where_eq('gs1.name_id', $bpool_reqs_id)
                ->where_eq('gs2.name_id', $bpool_reads_id)
                ->where('gs1.stamp > now() - interval 1 hour')
                ->where('gs2.stamp > now() - interval 1 hour')
                ->fields('(max(gs1.value)-min(gs1.value))/((max(gs1.value)-min(gs1.value))+(max(gs2.value)-min(gs2.value))) * 100');

            $bpwf = sql('tendril.global_status')
                ->fields('variable_value')->where_eq('server_id', $server_id)
                ->where_eq('variable_name', 'innodb_buffer_pool_wait_free');

            $ior = sql('tendril.global_status_log')
                ->where_eq('server_id', $server_id)
                ->where_eq('name_id', $data_read_id)
                ->where('stamp > now() - interval 1 hour')
                ->fields('max(value)-min(value)');

            $iow = sql('tendril.global_status_log')
                ->where_eq('server_id', $server_id)
                ->where_eq('name_id', $data_written_id)
                ->where('stamp > now() - interval 1 hour')
                ->fields('max(value)-min(value)');

            $deadlocks = sql('tendril.global_status_log')
                ->where_eq('server_id', $server_id)
                ->where_eq('name_id', $deadlocks_id)
                ->where('stamp > now() - interval 1 hour')
                ->fields('max(value)-min(value)');

            $os_s_waits = sql('tendril.global_status_log')
                ->where_eq('server_id', $server_id)
                ->where_eq('name_id', $os_s_waits_id)
                ->where('stamp > now() - interval 1 hour')
                ->fields('(max(value)-min(value)) / 3600');

            $os_x_waits = sql('tendril.global_status_log')
                ->where_eq('server_id', $server_id)
                ->where_eq('name_id', $os_x_waits_id)
                ->where('stamp > now() - interval 1 hour')
                ->fields('(max(value)-min(value)) / 3600');

            $spin_s_waits = sql('tendril.global_status_log')
                ->where_eq('server_id', $server_id)
                ->where_eq('name_id', $spin_s_waits_id)
                ->where('stamp > now() - interval 1 hour')
                ->fields('(max(value)-min(value)) / 3600');

            $spin_x_waits = sql('tendril.global_status_log')
                ->where_eq('server_id', $server_id)
                ->where_eq('name_id', $spin_x_waits_id)
                ->where('stamp > now() - interval 1 hour')
                ->fields('(max(value)-min(value)) / 3600');

            $spin_s_rounds = sql('tendril.global_status_log')
                ->where_eq('server_id', $server_id)
                ->where_eq('name_id', $spin_s_rounds_id)
                ->where('stamp > now() - interval 1 hour')
                ->fields('(max(value)-min(value)) / 3600');

            $spin_x_rounds = sql('tendril.global_status_log')
                ->where_eq('server_id', $server_id)
                ->where_eq('name_id', $spin_x_rounds_id)
                ->where('stamp > now() - interval 1 hour')
                ->fields('(max(value)-min(value)) / 3600');

            $search = sql('tendril.servers srv')
                ->where_eq('srv.id', $server_id)

                ->fields(array(
                    'srv.*',
                    sprintf('(%s) as innodb_file_per_table',
                        $fpt->get_select()),
                    sprintf('(%s) as innodb_buffer_pool_size',
                        $bps->get_select()),
                    sprintf('(%s) as innodb_log_file_size',
                        $lfs->get_select()),
                    sprintf('(%s) as innodb_flush_log_at_trx_commit',
                        $flatc->get_select()),
                    sprintf('(%s) as innodb_history_list_length',
                        $purge->get_select()),
                    sprintf('(%s) as innodb_buffer_pool_wait_free',
                        $bpwf->get_select()),
                    sprintf('(%s) as buffer_pool_hit_rate',
                        $bphr->get_select()),
                    sprintf('((%s) / (%s)) as io_ratio',
                        $ior->get_select(),
                        $iow->get_select()),
                    sprintf('(%s) as os_s_waits',
                        $os_s_waits->get_select()),
                    sprintf('(%s) as os_x_waits',
                        $os_x_waits->get_select()),
                    sprintf('(%s) as spin_s_waits',
                        $spin_s_waits->get_select()),
                    sprintf('(%s) as spin_x_waits',
                        $spin_x_waits->get_select()),
                    sprintf('(%s) as spin_s_rounds',
                        $spin_s_rounds->get_select()),
                    sprintf('(%s) as spin_x_rounds',
                        $spin_x_rounds->get_select()),
                    sprintf('(%s) as deadlocks',
                        $deadlocks->get_select()),
                ));

            $searches[] = $search->get_select();
        }

        $rows = sql::rawquery(join($searches, ' union '))
            ->cache(sql::MEMCACHE, 300)
            ->fetch_all();

        return array( $rows );
    }

    private function data_slow_queries()
    {
        $host   = $this->request('host');
        $schema = $this->request('schema');
        $user   = $this->request('user');
        $query  = $this->request('query');
        $hours  = $this->request('hours', 'float', 1);

        $qmode = $this->request('qmode', 'string', 'eq');

        $search = sql('tendril.processlist_query_log pql')
            ->cache(sql::MEMCACHE, 300)
            ->left_join('tendril.servers srv', 'pql.server_id = srv.id')
            ->fields(array(
                'pql.checksum',
                'count(*) as hits',
                'max(pql.time) as max_time',
                'avg(pql.time) as avg_time',
                'sum(pql.time) as sum_time',
                'group_concat(distinct pql.user) as users',
                'group_concat(distinct pql.db order by pql.db) as dbs',
                'group_concat(distinct pql.server_id order by srv.host) as servers',
                'pql.info as sample',
                'pql.db as sample_db',
                'pql.time as sample_time',
                'pql.server_id as sample_server_id',
            ))
            ->where_not_null('pql.checksum')
            ->where('pql.stamp > now() - interval '.$hours.' hour')
            ->where_gt('pql.time', 1)
            ->where_not_like('lower(trim(pql.info))', 'show%')
            ->where_not_like('lower(trim(pql.info))', 'select master_pos_wait%')
            //->having('max_time > 10')
            ->group('pql.checksum')
            ->order('sum_time', 'desc')
            ->order('max_time', 'desc')
            ->limit(50);

        $host_ids = array();
        if ($host)
        {
            $host_ids = sql('tendril.servers srv')->fields('srv.id')
                ->where_regexp('concat(srv.host,":",srv.port)', self::regex_host($host))
                ->fetch_field('id');
            $search->where_in('pql.server_id', $host_ids ? $host_ids: array(0));
        }

        if ($schema)
        {
            $search->where_regexp('pql.db', $schema);
        }

        if ($user)
        {
            $search->where_regexp('pql.user', $user);
        }

        if ($query)
        {
            $search->where_regexp('pql.info', $query, $qmode != 'ne');
        }

        $rows = $search->fetch_all('checksum');

        $period = ($hours*3).' minute';

        $g_cols = array(
            'x' => array('Time', 'datetime'),
            'y' => array('Active Slow Queries, '.$period.' sample', 'number'),
        );

        $bars = array();

        for ($i = 0; $i < round($hours*(20/$hours)); $i++)
        {
            $search = sql('processlist_query_log pql')
                //->left_join('tendril.servers srv', 'pql.server_id = srv.id')
                ->cache(sql::MEMCACHE, 300)
                ->fields(array(
                    'now() - interval '.(($i+1)*($hours*3)).' minute as x',
                    'count(distinct concat(pql.server_id,":",pql.id,":",pql.checksum)) as y',
                ))
                ->where_not_null('pql.checksum')
                ->where_gt('pql.time', 1)
                ->where('pql.stamp > now() - interval '.(($i+1)*($hours*3)).' minute')
                ->where('pql.stamp < now() - interval '.(($i)*($hours*3)).' minute')
                ->where_not_like('lower(trim(pql.info))', 'show%')
                ->where_not_like('lower(trim(pql.info))', 'select master_pos_wait%');

            if ($host)
            {
                $search->where_in('pql.server_id', $host_ids ? $host_ids: array(0));
            }

            if ($schema)
            {
                $search->where_regexp('pql.db', $schema);
            }

            if ($user)
            {
                $search->where_regexp('pql.user', $user);
            }

            if ($query)
            {
                $search->where_regexp('pql.info', $query, $qmode != 'ne');
            }

            $bars[] = $search->get_select();
        }

        $g_rows = sql::command(join(' union ', $bars))
            ->fetch_all();

        $dns = sql('tendril.dns')
            ->cache(sql::MEMCACHE, 300)
            ->group('ipv4')
            ->fetch_pair('ipv4', 'host');

        return array( $rows, $dns, $g_cols, $g_rows );
    }

    private function data_slow_queries_checksum()
    {
        $checksum = $this->request('checksum');
        $host     = $this->request('host');
        $schema   = $this->request('schema');
        $user     = $this->request('user');
        $hours    = $this->request('hours', 'float', 1);

        $qmode = $this->request('qmode', 'string', 'eq');

        $rows = array();

        if ($checksum)
        {
            $search = sql('tendril.processlist_query_log pql')
                ->left_join('tendril.servers srv', 'pql.server_id = srv.id')
                ->fields(array('pql.*'))
                ->where_eq('pql.checksum', $checksum)
                ->where('pql.stamp > now() - interval '.$hours.' hour')
                ->where_gt('pql.time', 1)
                ->order('time', 'desc')
                ->limit(50);

            if ($host)
            {
                $search->where_regexp('concat(srv.host,":",srv.port)', self::regex_host($host));
            }

            if ($schema)
            {
                $search->where_regexp('pql.db', $schema);
            }

            if ($user)
            {
                $search->where_regexp('pql.user', $user);
            }

            $rows = $search->fetch_all();
        }

        $period = ($hours*3).' minute';

        $g_cols = array(
            'x' => array('Time', 'datetime'),
            'y' => array('Active Queries, '.$period.' sample', 'number'),
        );

        $search = sql('processlist_query_log pql')
            ->left_join('tendril.servers srv', 'pql.server_id = srv.id')
            ->fields('count(distinct concat(pql.server_id,":",pql.id))')
            ->where_eq('pql.checksum', $checksum)
            ->where_gt('pql.time', 1)
            ->where('pql.stamp between x - interval '.$period.' and x')
            ->where('pql.stamp > now() - interval '.($hours+1).' hour');

        if ($host)
        {
            $search->where_regexp('concat(srv.host,":",srv.port)', self::regex_host($host));
        }

        if ($schema)
        {
            $search->where_regexp('pql.db', $schema);
        }

        if ($user)
        {
            $search->where_regexp('pql.user', $user);
        }

        $fields = array(
            'now() - interval s.value * '.$period.' as x',
            sprintf('(%s) as y', $search->get_select()),
        );

        $g_rows = sql('sequence s')
            ->where_between('value', 0, round($hours*(20/$hours)))
            ->having('x is not null')
            ->fields($fields)
            ->order('x')
            ->fetch_all();

        $dns = sql('tendril.dns')
            ->cache(sql::MEMCACHE, 300)
            ->group('ipv4')
            ->fetch_pair('ipv4', 'host');

        return array( $rows, $dns, $g_cols, $g_rows );
    }

    private function data_sampled_queries()
    {
        $host   = $this->request('host');
        $query  = $this->request('query');
        $hours  = $this->request('hours', 'float', 1);

        $qmode = $this->request('qmode', 'string', 'eq');

        $search = sql('tendril.queries_seen_log qsl')
            ->left_join('tendril.servers srv', 'qsl.server_id = srv.id')
            ->join('tendril.queries q', 'qsl.checksum = q.checksum')
            ->fields(array(
                'q.footprint',
                'count(*) as hits',
                'group_concat(distinct qsl.server_id order by srv.host) as servers',
                'q.template as template',
                'q.content as sample',
                'qsl.server_id as sample_server_id',
            ))
            ->where_not_null('q.footprint')
            ->where('qsl.stamp > now() - interval '.$hours.' hour')
            ->group('q.footprint')
            ->order('hits', 'desc')
            ->limit(50);

        $host_ids = array();
        if ($host)
        {
            $host_ids = sql('tendril.servers srv')->fields('srv.id')
                ->where_regexp('concat(srv.host,":",srv.port)', self::regex_host($host))
                ->fetch_field('id');
            $search->where_in('qsl.server_id', $host_ids ? $host_ids: array(0));
        }

        if ($query)
        {
            $search->where_regexp('q.content', $query, $qmode != 'ne');
        }
        else
        {
            $search->where_regexp('lower(q.content)', '^[[:space:]]*(select|insert|update|delete)', $qmode != 'ne');
        }

        $rows = $search->fetch_all('footprint');

        $period = ($hours*3).' minute';

        $g_cols = array(
            'x' => array('Time', 'datetime'),
            'y' => array('Sampled Queries, '.$period.' sample', 'number'),
        );

        $bars = array();

        for ($i = 0; $i < round($hours*(20/$hours)); $i++)
        {
            $search = sql('queries_seen_log qsl')
                //->left_join('tendril.servers srv', 'qsl.server_id = srv.id')
                ->join('tendril.queries q', 'qsl.checksum = q.checksum')
                ->fields(array(
                    'now() - interval '.(($i+1)*($hours*3)).' minute as x',
                    'count(qsl.checksum) as y',
                ))
                ->where_not_null('q.footprint')
                ->where('qsl.stamp > now() - interval '.(($i+1)*($hours*3)).' minute')
                ->where('qsl.stamp < now() - interval '.(($i)*($hours*3)).' minute');

            if ($host)
            {
                $search->where_in('qsl.server_id', $host_ids ? $host_ids: array(0));
            }

            if ($query)
            {
                $search->where_regexp('qsl.info', $query, $qmode != 'ne');
            }
            else
            {
                $search->where_regexp('lower(q.content)', '^[[:space:]]*(select|insert|update|delete)', $qmode != 'ne');
            }
            $bars[] = $search->get_select();
        }

        $g_rows = sql::command(join(' union ', $bars))
            ->fetch_all();

        $dns = sql('tendril.dns')
            ->cache(sql::MEMCACHE, 300)
            ->group('ipv4')
            ->fetch_pair('ipv4', 'host');

        return array( $rows, $dns, $g_cols, $g_rows );
    }

    private function data_sampled_queries_footprint()
    {
        $footprint = $this->request('footprint');
        $host      = $this->request('host');
        $hours     = $this->request('hours', 'float', 1);

        $qmode = $this->request('qmode', 'string', 'eq');

        $rows = array();

        if ($footprint)
        {
            $search = sql('tendril.queries_seen_log qsl')
                ->left_join('tendril.servers srv', 'qsl.server_id = srv.id')
                ->join('tendril.queries q', 'qsl.checksum = q.checksum')
                ->fields(array(
                    'qsl.*',
                    'q.content',
                    'qsl.server_id as sample_server_id',
                ))
                ->where_eq('q.footprint', $footprint)
                ->where('qsl.stamp > now() - interval '.$hours.' hour')
                ->order('qsl.stamp', 'desc')
                ->limit(50);

            if ($host)
            {
                $host_ids = sql('tendril.servers srv')->fields('srv.id')
                    ->where_regexp('concat(srv.host,":",srv.port)', self::regex_host($host))
                    ->fetch_field('id');
                $search->where_in('qsl.server_id', $host_ids ? $host_ids: array(0));
            }

            $rows = $search->fetch_all();
        }

        $period = ($hours*3).' minute';

        $g_cols = array(
            'x' => array('Time', 'datetime'),
            'y' => array('Active Queries, '.$period.' sample', 'number'),
        );

        $search = sql('queries_seen_log qsl')
            ->left_join('tendril.servers srv', 'qsl.server_id = srv.id')
            ->join('tendril.queries q', 'qsl.checksum = q.checksum')
            ->fields('count(qsl.server_id)')
            ->where_eq('q.footprint', $footprint)
            ->where('qsl.stamp between x - interval '.$period.' and x')
            ->where('qsl.stamp > now() - interval '.($hours+1).' hour');

        if ($host)
        {
            $host_ids = sql('tendril.servers srv')->fields('srv.id')
                ->where_regexp('concat(srv.host,":",srv.port)', self::regex_host($host))
                ->fetch_field('id');
            $search->where_in('qsl.server_id', $host_ids ? $host_ids: array(0));
        }

        $fields = array(
            'now() - interval s.value * '.$period.' as x',
            sprintf('(%s) as y', $search->get_select()),
        );

        $g_rows = sql('sequence s')
            ->where_between('value', 0, round($hours*(20/$hours)))
            ->having('x is not null')
            ->fields($fields)
            ->order('x')
            ->fetch_all();

        $dns = sql('tendril.dns')
            ->cache(sql::MEMCACHE, 300)
            ->group('ipv4')
            ->fetch_pair('ipv4', 'host');

        return array( $rows, $dns, $g_cols, $g_rows );
    }

    private function data_schemas()
    {
        $host_ids = sql('tendril.servers')
            ->where_null('m_master_id')
            ->where_like('host', 'db____.%')
            ->fields('id')
            ->fetch_field('id');

        $schema = $this->request('schema');

        $search = sql('tendril.tables tab')
            ->fields(array(
                'tab.server_id',
                'tab.table_schema as schema_name',
                'sum(tab.data_length) as data',
                'sum(tab.data_length)/1024/1204/1024 as data_gb',
                'sum(tab.index_length) as indexes',
                'sum(tab.index_length)/1024/1204/1024 as indexes_gb',
                'sum(data_length + index_length) as total',
                'sum(data_length + index_length)/1024/1024/1024 as total_gb'
            ))
            ->where_in('tab.server_id', $host_ids)
            ->group('tab.table_schema')
            ->order('total', 'desc');

        if ($schema)
        {
            $search->where_regexp('tab.table_schema', $schema);
        }

        $rows = $search->fetch_all();

        return array( $rows );

    }

    private function data_clusters()
    {
        $host_ids = sql('tendril.servers')
            ->where_null('m_master_id')
            ->where_like('host', 'db____.%')
            ->fields('id')
            ->fetch_field('id');

        $search = sql('tendril.tables tab')
            ->fields(array(
                'tab.server_id',
                'group_concat(distinct tab.table_schema) as schema_names',
                'sum(tab.data_length) as data',
                'sum(tab.data_length)/1024/1204/1024 as data_gb',
                'sum(tab.index_length) as indexes',
                'sum(tab.index_length)/1024/1204/1024 as indexes_gb',
                'sum(data_length + index_length) as total',
                'sum(data_length + index_length)/1024/1024/1024 as total_gb'
            ))
            ->where_in('tab.server_id', $host_ids)
            ->group('tab.server_id')
            ->order('total', 'desc');

        $rows = $search->fetch_all();

        return array( $rows );

    }

    private function data_row_distribution()
    {
        $schema = $this->request('schema', 'string', 'SCHEMA');
        $table  = $this->request('table', 'string', 'TABLE');
        $field  = $this->request('field', 'string', 'FIELD');
        $max    = $this->request('max', 'int', 1);
        $min    = $this->request('min', 'int', 10);
        $inc    = $this->request('inc', 'int', 1);

        $unions = array();

        for ($i = $min; $i < $max; $i += $inc)
        {
            $unions[] = sql(sprintf('%s.%s', $schema, $table))
                ->where_between($field, $i, $i+$inc-1)
                ->fields(array(
                    sprintf('%d as v', $i),
                    'count(*) as n',
                ))
                ->get_select();
        }

        return array( 'explain ' .join(" union\n", $unions ));
    }

    private function data_processlist()
    {
        $host    = $this->request('host');
        $schema  = $this->request('schema');
        $user    = $this->request('user');
        $time    = $this->request('time');
        $command = $this->request('command');
        $query   = $this->request('query');

        $qmode = $this->request('qmode', 'string', 'eq');

        $rows = array();

        if ($host || $schema || $user || $time || $command)
        {
            $search = sql('tendril.processlist p')->fields('p.*')
                ->join('tendril.servers srv', 'p.server_id = srv.id')
                ->order('p.time', 'desc')
                ->limit(100);

            if ($host)
            {
                $search->where_regexp('concat(srv.host,":",srv.port)', self::regex_host($host));
            }

            if ($schema)
            {
                $search->where_regexp('p.db', $schema);
            }

            if ($user)
            {
                $search->where_regexp('p.user', $user);
            }

            if ($time)
            {
                $search->where_gt('p.time', $time);
            }

            if ($command)
            {
                $search->where_regexp('p.command', $command);
            }

            if ($query)
            {
                $search->where_regexp('p.info', $query, $qmode != 'ne');
            }

            $rows = $search->fetch_all();
        }

        $dns = sql('tendril.dns')
            ->cache(sql::MEMCACHE, 300)
            ->group('ipv4')
            ->fetch_pair('ipv4', 'host');

        return array( $rows, $dns );
    }

    private function data_trxlist()
    {
        $host    = $this->request('host');
        $schema  = $this->request('schema');
        $user    = $this->request('user');

        $rows = array();

        if ($host || $schema || $user || $time || $command)
        {
            $search = sql('tendril.innodb_trx t')
                ->fields(array(
                    't.*', 'p.*',
                    'unix_timestamp() - unix_timestamp(trx_started) as trx_time',
                ))
                ->join('tendril.servers srv', 't.server_id = srv.id')
                ->left_join('tendril.processlist p', 't.server_id = p.server_id and t.trx_mysql_thread_id = p.id')
                ->order('t.trx_started', 'asc')
                ->limit(100);

            if ($host)
            {
                $search->where_regexp('concat(srv.host,":",srv.port)', self::regex_host($host));
            }

            if ($schema)
            {
                $search->where_regexp('p.db', $schema);
            }

            if ($user)
            {
                $search->where_regexp('p.user', $user);
            }

            $rows = $search->fetch_all();
        }

        foreach ($rows as &$row)
        {
            $row['queries'] = array();
        }

        if ($rows)
        {
            $server_ids = array();
            $thread_ids = array();

            foreach ($rows as $row)
            {
                $server_ids[] = $row['server_id'];
                $thread_ids[] = $row['trx_mysql_thread_id'];
            }

            $queries = sql('processlist_query_log pql')
                ->where_in('server_id', array_unique($server_ids))
                ->where_in('id', array_unique($thread_ids))
                ->where('stamp > now() - interval 10 minute')
                ->fetch_all();

            foreach ($queries as $qrow)
            {
                foreach ($rows as $i => $row)
                {
                    if ($row['server_id'] == $qrow['server_id']
                        && $row['trx_mysql_thread_id'] == $qrow['id']
                        && $row['info'] != $qrow['info'])
                    {
                        $rows[$i]['queries'][] = $qrow;
                    }
                }
            }
        }

        $dns = sql('tendril.dns')
            ->cache(sql::MEMCACHE, 300)
            ->group('ipv4')
            ->fetch_pair('ipv4', 'host');

        return array( $rows, $dns );
    }
}