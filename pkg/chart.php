<?php

require_once ROOT .'pkg/report.php';

class Package_Chart extends Package_Report
{
    public function page()
    {
        list ($cols, $rows) = $this->data();

        $status_vars = sql('tendril.global_status')
            ->cache(SQL::MEMCACHE, 3600)
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

            $seq_upper = $hours * round(60/$mins);

            $name_ids = sql('tendril.strings')
                ->cache(SQL::MEMCACHE, 300)
                ->where_regexp('string', $vars)
                ->fetch_pair('string', 'id');

            $servers = sql('tendril.servers srv')
                ->cache(SQL::MEMCACHE, 3600)
                ->where_regexp('concat(srv.host,":",srv.port)', self::regex_host($host))
                ->limit(10)
                ->fields(array('host', 'port', 'id'))
                ->fetch_all('id');

            $inner = sql('seq_1_to_'.$seq_upper.' s')
                ->fields('now() - interval seq * '.$mins.' minute as x')
                ->left_join('tendril.global_status_log gsl',
                    sprintf('gsl.stamp > now() - interval '.$hours.' hour'))
                ->where_in('gsl.server_id', array_keys($servers))
                ->where_in('gsl.name_id', $name_ids)
                ->where_between('gsl.stamp',
                    sql::expr('now() - interval seq * '.$mins.' minute - interval '.$mins.' minute'),
                    sql::expr('now() - interval seq * '.$mins.' minute'))
                ->group('x');

            $outer = sql()
                ->from('(select now() - interval seq * '.$mins.' minute as x from seq_1_to_'.$seq_upper.')', 'o')
                ->cache(SQL::MEMCACHE, $mins*60)
                ->fields('o.x')
                ->order('x');

            $i = 0;
            foreach ($servers as $server_id => $server)
            {
                $host = substr($server['host'], 0, strpos($server['host'], '.'));
                if ($server['port'] != 3306) $host .= ':'.$server['port'];

                foreach ($name_ids as $name => $id)
                {
                    $cols['y'.($i+1)] = count($servers) > 1
                        ? array($host.' '.$name, 'number')
                        : array($name, 'number');

                    $outer->field(
                        sprintf('i.y%d', ($i+1))
                    );

                    if ($mode == 'delta')
                    {
                        $inner->field(
                            sprintf("cast(ifnull(max(if(server_id = %d and name_id = %d,cast(value as unsigned),0)),0) -"
                                ." ifnull(min(if(server_id = %d and name_id = %d,cast(value as unsigned),null)),0) as unsigned) as %s",
                                $server_id, $id, $server_id, $id, 'y'.($i+1)
                            )
                        );
                    }
                    else
                    {
                        $inner->field(
                            sprintf("cast(ifnull(max(if(server_id = %d and name_id = %d,cast(value as unsigned),0)),0) as unsigned) as %s",
                                $server_id, $id, 'y'.($i+1)
                            )
                        );
                    }
                    $i++;
                }
            }

            $rows = $outer->left_join(
                sprintf('(%s) as i', $inner->get_select()),
                'o.x = i.x')
            ->fetch_all();

            foreach ($rows as $i => $row)
                $rows[$i] = $row->export();
        }

        return array( $cols, $rows );
    }
}