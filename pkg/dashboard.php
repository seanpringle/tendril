<?php

class Package_Dashboard extends Package
{
    public function process()
    {

    }

    public function page()
    {
        switch ($this->action())
        {
            default:
                list ($hosts_disabled, $event_activity, $event_variables, $event_status, $event_cron, $event_privileges, $event_schema) = $this->data_index();
                include ROOT .'tpl/dashboard/index.php';
        }
    }

    private function data_index()
    {
        $hosts_disabled = sql('servers')
            ->fields('id')
            ->where_eq('enabled', 0)
            ->fetch_field('id');

        $event_activity = sql('servers')
            ->fields('id')
            ->where_eq('enabled', 1)
            ->where('event_activity < now() - interval 1 minute')
            ->fetch_field('id');

        $event_variables = sql('servers')
            ->fields('id')
            ->where_eq('enabled', 1)
            ->where('event_variables < now() - interval 10 minute')
            ->fetch_field('id');

        $event_status = sql('servers')
            ->fields('id')
            ->where_eq('enabled', 1)
            ->where('event_activity < now() - interval 10 minute')
            ->fetch_field('id');

        $event_cron = sql('servers')
            ->fields('id')
            ->where_eq('enabled', 1)
            ->where('event_cron < now() - interval 10 minute')
            ->fetch_field('id');

        $event_privileges = sql('servers')
            ->fields('id')
            ->where_eq('enabled', 1)
            ->where('event_privileges < now() - interval 2 day')
            ->fetch_field('id');

        $event_schema = sql('servers')
            ->fields('id')
            ->where_eq('enabled', 1)
            ->where('event_schema < now() - interval 2 day')
            ->fetch_field('id');

        return array( $hosts_disabled, $event_activity, $event_variables, $event_status, $event_cron, $event_privileges, $event_schema );
    }
}

