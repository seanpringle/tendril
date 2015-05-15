<p class="note">
    Queries running longer than 10s gathered via SHOW FULL PROCESSLIST snapshots.
</p>

<style type="text/css">

#activity-processlist .query td {
    font-size: smaller;
    color: #666;
    padding-bottom: 1em;
}

#activity-hide label {
    padding: 0 0.5em;
}

#activity-processlist .time.long {
    color: red;
}

</style>

<form id="activity-hide" method="GET" class="search merge-down">
    <table cellspacing="0" cellpadding="0">
    <tr>
        <td>
            <input type="submit" value="Hide Users &raquo;" />
        </td>
        <td>
            <label>
                <input type="checkbox" name="wikiadmin" value="0" <?= pkg()->request('wikiadmin', 'uint', 1) === 0 ? 'checked':'' ?> />
                <span>wikiadmin</span>
            </label>
        </td>
        <td>
            <label>
                <input type="checkbox" name="wikiuser" value="0" <?= pkg()->request('wikiuser', 'uint', 1) === 0 ? 'checked':'' ?> />
                <span>wikiuser</span>
            </label>
        </td>
        <td>
            <label>
                <input type="checkbox" name="research" value="0" <?= pkg()->request('research', 'uint', 1) === 0 ? 'checked':'' ?> />
                <span>research</span>
            </label>
        </td>
        <td>
            <label>
                <input type="checkbox" name="labsusers" value="0" <?= pkg()->request('labsusers', 'uint', 1) === 0 ? 'checked':'' ?> />
                <span>labs users</span>
            </label>
        </td>
    </tr>
    </table>
</form>

</form>

<table id="activity-processlist">

    <tr>
        <th>Server</th>
        <th>Connection</th>
        <th>User</th>
        <th>Client</th>
        <th>Database</th>
        <th>Time</th>
    </tr>

<?php

foreach ($processlist as $row)
{
    $host = new Server($row['server_id']);

    list ($ip, $port) = explode(':', $row['host']);

    $client = expect($dns, $ip, 'string', $ip);
    list($client_short) = preg_match('/^[a-z]/', $client) ? explode('.', $client): $client;

    $cells = array(
        tag('td', array(
            'class' => 'server',
            'html' =>
                tag('a', array(
                    'href' => sprintf('/host/view/%s/%d', $host->name(), $host->port),
                    'html' => escape($host->name_short()),
                ))
        )),
        tag('td', array(
            'class' => 'id',
            'html' => escape($row['id']),
        )),
        tag('td', array(
            'class' => 'user',
            'html' => escape($row['user']),
        )),
        tag('td', array(
            'class' => 'host',
            'title' => escape($client),
            'html' => escape($client_short),
        )),
        tag('td', array(
            'class' => 'db',
            'html' => escape($row['db']),
        )),
        tag('td', array(
            'class' => sprintf('time %s', $row['time'] > 86400 ? 'long': ''),
            'html' => escape(duration_short($row['time'])),
        )),

    );

    print tag('tr', array(
        'class' => 'metadata',
        'html' => str($cells)
    ));

    $sample = $row['info'];

    if (($ips = find_ipv4($sample)) && ($name = gethostbyaddr($ips[0])) && $name != $ips[0])
    {
        $sample = sprintf('%s /* %s */', $sample, $name);
    }

    $cells = array(
        tag('td', array(
            'class' => 'info',
            'colspan' => 6,
            'html' => trim($sample) ? escape($sample): 'n/a',
        )),
    );

    print tag('tr', array(
        'class' => 'query',
        'html' => str($cells)
    ));

}

?>

</table>