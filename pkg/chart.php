<?php

require_once ROOT .'pkg/report.php';

class Package_Chart extends Package_Report
{
    public function page()
    {
        list ($cols, $rows) = $this->data();

        $status_vars = sql::query('tendril.global_status')
            ->cache(sql::MEMCACHE, 3600)
            ->fields('distinct variable_name')
            ->fetch_field('variable_name');

        include ROOT .'tpl/chart/view.php';
    }

    private function data()
    {
        $vars   = $this->request('vars', 'string');
        $host   = $this->request('hosts', 'string');
        $hours  = 24;
        $mins   = 5;
        $mode   = $this->request('mode', 'string', 'delta');
        $vgroup = $this->request('vg', 'bool', false);

        $cols = $rows = array();

        if ($host && $vars)
        {
            $cols = array(
                'x' => array($mins.'min', 'datetime'),
            );

            $fields = array(
                'now() - interval value*'.$mins.' minute as x',
            );

            $mode = sprintf('cast(ifnull(%s,0) as unsigned)',
                $mode == 'delta' ? 'max(gsl.value) - min(gsl.value)' : 'max(gsl.value)');

            $servers = sql::query('tendril.servers srv')
                ->cache(sql::MEMCACHE, 3600)
                ->where_regexp('concat(srv.host,":",srv.port)', self::regex_host($host))
                ->limit(10)
                ->fields('id')->fetch_field('id');

            $names = sql::query('tendril.strings')
                ->cache(sql::MEMCACHE, 3600)
                ->where_regexp('string', $vars)
                ->limit(10)
                ->fetch_pair('string', 'id');

            $i = 1;
            foreach ($servers as $server_id)
            {
                $host = new Host($server_id);

                if ($vgroup)
                {
                    $cols['y'.$i] = array(
                        $host->describe(),
                        'number'
                    );

                    $subqueries = array();

                    foreach ($names as $name => $name_id)
                    {
                        $table = preg_match('/Seconds_Behind_Master/', $name)
                            ? 'tendril.slave_status_log': 'tendril.global_status_log';

                        $subqueries[] = sprintf('(%s)',
                            sql::query($table.' gsl')
                                ->fields($mode)
                                ->where('gsl.stamp between x - interval '.$mins.' minute and x')
                                ->where_eq('gsl.server_id', $server_id)
                                ->where_eq('gsl.name_id', $name_id)
                                ->where('gsl.stamp > now() - interval 24 hour')
                                ->get_select()
                        );

                    }
                    $fields[] = sprintf('(%s) as y%d', $subqueries ? join(' + ', $subqueries): '0', $i);
                    $i++;
                }
                else
                {
                    foreach ($names as $name => $name_id)
                    {
                        $cols['y'.$i] = array(
                            $host->describe() . (count($names) > 1 ? ' '.$name: ''),
                            'number'
                        );

                        $table = preg_match('/Seconds_Behind_Master/', $name)
                            ? 'tendril.slave_status_log': 'tendril.global_status_log';

                        $fields[] = sprintf('(%s) as y%d',
                            sql::query($table.' gsl')
                                ->fields($mode)
                                ->where('gsl.stamp between x - interval '.$mins.' minute and x')
                                ->where_eq('gsl.server_id', $server_id)
                                ->where_eq('gsl.name_id', $name_id)
                                ->where('gsl.stamp > now() - interval 24 hour')
                                ->get_select(),
                            $i
                        );

                        $i++;
                    }
                }
            }

            $rows = sql::query('sequence s')
                ->cache(sql::MEMCACHE, 300)
                ->where_between('value', 1, $hours * round(60/$mins))
                ->having('x is not null')
                ->fields($fields)
                ->order('value')
                ->fetch_all();
        }

        return array( $cols, $rows );
    }
}