<?php

$events = array(
    'host disabled'
        => array(
            '',
            $hosts_disabled,
        ),
    'event_activity'
        => array(
            'over 1 min',
            $event_activity,
        ),
    'event_variables'
        => array(
            'over 10 min',
            $event_variables,
        ),
    'event_status'
        => array(
            'over 10 min',
            $event_status,
        ),
    'event_cron'
        => array(
            'over 10 min',
            $event_cron,
        ),
    'event_privileges'
        => array(
            'over 2 days',
            $event_privileges,
        ),
    'event_schema'
        => array(
            'over 2 days',
            $event_schema,
        ),
);

?>

<table>

<?php

foreach ($events as $name => $row)
{
    list($comment, $ids) = $row;

    if ($ids)
    {
        $links = array();
        foreach ($ids as $id)
        {
            $host = new Server($id);
            $links[] = tag('a', array(
                'href' => sprintf('/host/%s/%d', $host->name(), $host->port()),
                'html' => $host->describe(),
            ));
        }

        print tag('tr', array(
            'html' =>
                tag('td', array(
                    'html' => escape($name),
                )).
                tag('td', array(
                    'html' => escape($comment),
                )).
                tag('td', array(
                    'html' => join(', ', $links),
                ))
            )
        );
    }
}
?>

</table>

