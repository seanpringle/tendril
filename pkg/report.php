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
                list ($rows, $dns) = $this->data_slow_queries();
                include ROOT .'tpl/report/slow_queries.php';
                break;

            case 'slow_queries_checksum':
                list ($rows, $dns) = $this->data_slow_queries_checksum();
                include ROOT .'tpl/report/slow_queries_checksum.php';
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
                list ($rows, $dns) = $this->processlist();
                include ROOT .'tpl/report/processlist.php';
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
        return sql::query('tendril.schemata_ignore')->fetch_field('schema_name');
    }

    public function regex_host($text)
    {
        $text = preg_replace('/^(masters|slaves|family):/', '^\\1:', $text);

        $text = preg_replace_callback('/masters/',
            function($match) {
                $hosts = sql::query('tendril.servers')
                    ->where_null('m_master_id')
                    ->fields('concat(host,":",port) as h')
                    ->fetch_field('h');
                return sprintf('(%s)', join('|', $hosts));
            },
            $text
        );
        $text = preg_replace_callback('/slaves:([a-z0-9.]+)/',
            function($match) {
                $mid = sql::query('tendril.servers')
                    ->where_like('host', $match[1].'%')
                    ->fields('m_server_id')
                    ->fetch_value();
                $hosts = sql::query('tendril.servers')
                    ->where_eq('m_master_id', $mid)
                    ->fields('concat(host,":",port) as h')
                    ->fetch_field('h');
                return sprintf('(%s)', join('|', $hosts));
            },
            $text
        );
        $text = preg_replace_callback('/family:([a-z0-9.]+)/',
            function($match) {
                list($mid, $host) = sql::query('tendril.servers')
                    ->where_like('host', $match[1].'%')
                    ->fields(array('m_server_id', 'concat(host,":",port) as h'))
                    ->fetch_one_numeric();
                $hosts = sql::query('tendril.servers')
                    ->where_eq('m_master_id', $mid)
                    ->fields('concat(host,":",port) as h')
                    ->fetch_field('h');
                return sprintf('(%s|%s)', $host, join('|', $hosts));
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
            $search = sql::query('tendril.servers srv')

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
            $search = sql::query('tendril.servers srv')

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
        $data   = $this->request('data',  'float', 0);
        $index  = $this->request('index', 'float', 0);

        if ($host || $schema || $table)
        {
            $search = sql::query('tendril.servers srv')

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
            $search = sql::query('tendril.servers srv')

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
            $search = sql::query('tendril.servers srv')

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

    private function data_index_find()
    {
        $rows = array();

        $host   = $this->request('host');
        $schema = $this->request('schema');
        $table  = $this->request('table');
        $index  = $this->request('index');

        if ($table && $index)
        {
            $search = sql::query('tendril.servers srv')

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
            $search = sql::query('tendril.servers srv')

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
            $hA = new Host($hostA);
            $hB = new Host($hostB);

            $searchA = sql::query('tendril.statistics a')
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

            $searchB = sql::query('tendril.statistics b')
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

        $purge = sql::query('tendril.global_status')
            ->fields('variable_value')->where('server_id = srv.id')
            ->where_eq('variable_name', 'innodb_history_list_length');

        $fpt = sql::query('tendril.global_variables')
            ->fields('variable_value')->where('server_id = srv.id')
            ->where_eq('variable_name', 'innodb_file_per_table');

        $bps = sql::query('tendril.global_variables')
            ->fields('variable_value/1024/1024/1024')->where('server_id = srv.id')
            ->where_eq('variable_name', 'innodb_buffer_pool_size');

        $lfs = sql::query('tendril.global_variables')
            ->fields('variable_value/1024/1024')->where('server_id = srv.id')
            ->where_eq('variable_name', 'innodb_log_file_size');

        $flatc = sql::query('tendril.global_variables')
            ->fields('variable_value')->where('server_id = srv.id')
            ->where_eq('variable_name', 'innodb_flush_log_at_trx_commit');

        $reqs_id  = sql::query('tendril.strings')->cache(sql::MEMCACHE, self::EXPIRE)
            ->where_eq('string', 'innodb_buffer_pool_read_requests')->fetch_value('id');
        $reads_id = sql::query('tendril.strings')->cache(sql::MEMCACHE, self::EXPIRE)
            ->where_eq('string', 'innodb_buffer_pool_reads')->fetch_value('id');

        $bphr = sql::query('tendril.global_status_log_5m gs1')
            ->join('tendril.global_status_log_5m gs2', 'gs1.server_id = gs2.server_id')
            ->where('gs1.server_id = srv.id')
            ->where('gs2.server_id = srv.id')
            ->where_eq('gs1.name_id', $reqs_id)
            ->where_eq('gs2.name_id', $reads_id)
            ->where('gs1.stamp > now() - interval 1 hour')
            ->where('gs2.stamp > now() - interval 1 hour')
            ->fields('(max(gs1.value)-min(gs1.value))/((max(gs1.value)-min(gs1.value))+(max(gs2.value)-min(gs2.value))) * 100');

        $bpwf = sql::query('tendril.global_status')
            ->fields('variable_value')->where('server_id = srv.id')
            ->where_eq('variable_name', 'innodb_buffer_pool_wait_free');

        $r_id = sql::query('tendril.strings')->cache(sql::MEMCACHE, self::EXPIRE)
            ->where_eq('string', 'innodb_data_read')->fetch_value('id');
        $w_id = sql::query('tendril.strings')->cache(sql::MEMCACHE, self::EXPIRE)
            ->where_eq('string', 'innodb_data_written')->fetch_value('id');

        $io = sql::query('tendril.global_status_log_5m gs1')
            ->join('tendril.global_status_log_5m gs2', 'gs1.server_id = gs2.server_id')
            ->where('gs1.server_id = srv.id')
            ->where('gs2.server_id = srv.id')
            ->where_eq('gs1.name_id', $r_id)
            ->where_eq('gs2.name_id', $w_id)
            ->where('gs1.stamp > now() - interval 1 hour')
            ->where('gs2.stamp > now() - interval 1 hour')
            ->fields('(max(gs1.value)-min(gs1.value)) / (max(gs2.value)-min(gs2.value))');

        $dl_id = sql::query('tendril.strings')->cache(sql::MEMCACHE, self::EXPIRE)
            ->where_eq('string', 'innodb_deadlocks')->fetch_value('id');

        $deadlocks = sql::query('tendril.global_status_log_5m')
            ->where('server_id = srv.id')
            ->where_eq('name_id', $dl_id)
            ->where('stamp > now() - interval 1 hour')
            ->fields('max(value)-min(value)');

        $s_id = sql::query('tendril.strings')->cache(sql::MEMCACHE, self::EXPIRE)
            ->where_eq('string', 'innodb_s_lock_os_waits')->fetch_value('id');
        $x_id = sql::query('tendril.strings')->cache(sql::MEMCACHE, self::EXPIRE)
            ->where_eq('string', 'innodb_x_lock_os_waits')->fetch_value('id');

        $os_s_waits = sql::query('tendril.global_status_log_5m')
            ->where('server_id = srv.id')
            ->where_eq('name_id', $s_id)
            ->where('stamp > now() - interval 1 hour')
            ->fields('(max(value)-min(value)) / 3600');

        $os_x_waits = sql::query('tendril.global_status_log_5m')
            ->where('server_id = srv.id')
            ->where_eq('name_id', $x_id)
            ->where('stamp > now() - interval 1 hour')
            ->fields('(max(value)-min(value)) / 3600');

        $s_id = sql::query('tendril.strings')->cache(sql::MEMCACHE, self::EXPIRE)
            ->where_eq('string', 'innodb_s_lock_spin_waits')->fetch_value('id');
        $x_id = sql::query('tendril.strings')->cache(sql::MEMCACHE, self::EXPIRE)
            ->where_eq('string', 'innodb_x_lock_spin_waits')->fetch_value('id');

        $spin_s_waits = sql::query('tendril.global_status_log_5m')
            ->where('server_id = srv.id')
            ->where_eq('name_id', $s_id)
            ->where('stamp > now() - interval 1 hour')
            ->fields('(max(value)-min(value)) / 3600');

        $spin_x_waits = sql::query('tendril.global_status_log_5m')
            ->where('server_id = srv.id')
            ->where_eq('name_id', $x_id)
            ->where('stamp > now() - interval 1 hour')
            ->fields('(max(value)-min(value)) / 3600');

        $s_id = sql::query('tendril.strings')->cache(sql::MEMCACHE, self::EXPIRE)
            ->where_eq('string', 'innodb_s_lock_spin_rounds')->fetch_value('id');
        $x_id = sql::query('tendril.strings')->cache(sql::MEMCACHE, self::EXPIRE)
            ->where_eq('string', 'innodb_x_lock_spin_rounds')->fetch_value('id');

        $spin_s_rounds = sql::query('tendril.global_status_log_5m')
            ->where('server_id = srv.id')
            ->where_eq('name_id', $s_id)
            ->where('stamp > now() - interval 1 hour')
            ->fields('(max(value)-min(value)) / 3600');

        $spin_x_rounds = sql::query('tendril.global_status_log_5m')
            ->where('server_id = srv.id')
            ->where_eq('name_id', $x_id)
            ->where('stamp > now() - interval 1 hour')
            ->fields('(max(value)-min(value)) / 3600');

        $search = sql::query('tendril.servers srv')
            ->cache(sql::MEMCACHE, 300) // global_status_log_5m

            ->order('srv.host')
            ->order('srv.port')

            ->fields(array(
                'srv.*',
                sprintf('(%s) as innodb_file_per_table', $fpt->get_select()),
                sprintf('(%s) as innodb_buffer_pool_size', $bps->get_select()),
                sprintf('(%s) as innodb_log_file_size', $lfs->get_select()),
                sprintf('(%s) as innodb_flush_log_at_trx_commit', $flatc->get_select()),
                sprintf('(%s) as innodb_history_list_length', $purge->get_select()),
                sprintf('(%s) as innodb_buffer_pool_wait_free', $bpwf->get_select()),
                sprintf('(%s) as buffer_pool_hit_rate', $bphr->get_select()),
                sprintf('(%s) as io_ratio', $io->get_select()),
                sprintf('(%s) as os_s_waits', $os_s_waits->get_select()),
                sprintf('(%s) as os_x_waits', $os_x_waits->get_select()),
                sprintf('(%s) as spin_s_waits', $spin_s_waits->get_select()),
                sprintf('(%s) as spin_x_waits', $spin_x_waits->get_select()),
                sprintf('(%s) as spin_s_rounds', $spin_s_rounds->get_select()),
                sprintf('(%s) as spin_x_rounds', $spin_x_rounds->get_select()),
                sprintf('(%s) as deadlocks', $deadlocks->get_select()),
            ));

        if ($host)
        {
            $search->where_regexp('concat(srv.host,":",srv.port)', self::regex_host($host));
        }

        $rows = $search->fetch_all();

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

        $search = sql::query('tendril.processlist_query_log pql')
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
            ->having('max_time > 10')
            ->group('pql.checksum')
            ->order('sum_time', 'desc')
            ->order('max_time', 'desc')
            ->limit(25);

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

        if ($query)
        {
            $search->where_regexp('pql.info', $query, $qmode != 'ne');
        }

        $rows = $search->fetch_all('checksum');

        $dns = sql::query('tendril.dns')
            ->cache(sql::MEMCACHE, 300)
            ->group('ipv4')
            ->fetch_pair('ipv4', 'host');

        return array( $rows, $dns );
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
            $search = sql::query('tendril.processlist_query_log pql')
                ->left_join('tendril.servers srv', 'pql.server_id = srv.id')
                ->fields(array('pql.*'))
                ->where_eq('pql.checksum', $checksum)
                ->where('pql.stamp > now() - interval '.$hours.' hour')
                ->where_gt('pql.time', 1)
                ->order('time', 'desc')
                ->limit(25);

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

        $dns = sql::query('tendril.dns')
            ->cache(sql::MEMCACHE, 300)
            ->group('ipv4')
            ->fetch_pair('ipv4', 'host');

        return array( $rows, $dns );
    }

    private function data_schemas()
    {
        $host_ids = sql::query('tendril.servers')
            ->where_null('m_master_id')
            ->where_like('host', 'db____.%')
            ->fields('id')
            ->fetch_field('id');

        $schema = $this->request('schema');

        $search = sql::query('tendril.tables tab')
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
        $host_ids = sql::query('tendril.servers')
            ->where_null('m_master_id')
            ->where_like('host', 'db____.%')
            ->fields('id')
            ->fetch_field('id');

        $search = sql::query('tendril.tables tab')
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
            $unions[] = sql::query(sprintf('%s.%s', $schema, $table))
                ->where_between($field, $i, $i+$inc-1)
                ->fields(array(
                    sprintf('%d as v', $i),
                    'count(*) as n',
                ))
                ->get_select();
        }

        return array( 'explain ' .join(" union\n", $unions ));
    }

    private function processlist()
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
            $search = sql::query('tendril.processlist p')->fields('p.*')
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

        $dns = sql::query('tendril.dns')
            ->cache(sql::MEMCACHE, 300)
            ->group('ipv4')
            ->fetch_pair('ipv4', 'host');

        return array( $rows, $dns );
    }

}