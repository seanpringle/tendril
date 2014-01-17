<?php

class Package_Dashboard extends Package
{
    public function page()
    {
        list ($cols, $rows) = $this->data_index();
        include ROOT .'tpl/dashboard/index.php';
    }

    private function data_index()
    {
        list ($cols, $rows) = $this->data_com_kill();
        return array($cols, $rows);
    }

    private function data_com_kill()
    {
        $cols = array(
            'x' => array('Hour', 'datetime'),
        );

        $fields = array(
            'now() - interval s.value * 10 minute as x',
        );

        $names = array(
            'Com_kill',
        );

        $name_ids = sql::query('tendril.strings')
            ->where_in('string', map('strtolower', $names))
            ->fetch_pair('string', 'id');

        foreach ($names as $i => $name)
        {
            $cols['y'.($i+1)] = array($name, 'number');

            $fields[] = sprintf('(%s) as y%d',
                sql::query('tendril.global_status_log gsl')
                    ->fields('cast(ifnull(sum(gsl.value),0) as unsigned)')
                    ->where('gsl.stamp between x - interval 10 minute and x')
                    ->where_eq('gsl.name_id', $name_ids[strtolower($name)])
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

        $last = $rows[0]['y1'];
        foreach ($rows as $i => $row)
        {
            $next = $rows[$i]['y1'];
            $rows[$i]['y1'] -= $last;
            $last = $next;
        }

        return array( $cols, $rows );
    }
}