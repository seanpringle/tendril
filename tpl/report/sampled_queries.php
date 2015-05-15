
<p class="note">
    <strong>Sampled Queries</strong> pulled from general and slow query logs (only some hosts).
</p>

<style>
#sampled-queries .right {
    text-align: right;
}
#sampled-queries tr.sample {
    font-size: smaller;
    color: #999;
}
#chart {
    border: 1px solid #999;
    margin-bottom: 1em;
}
</style>

<form method="GET" class="search">
    <table cellspacing="0" cellpadding="0">
    <tr>
        <th>Host</th>
        <th>Query</th>
        <th>Hours</th>
        <th></th>
    </tr>
    <tr>
        <td>
            <input type="text" name="host" value="<?= escape(pkg()->request('host')) ?>" placeholder="regex" />
        </td>
        <td>
            <select name="qmode">
                <option value="eq" <?= pkg()->request('qmode') == 'eq' ? ' selected': '' ?>>=~</option>
                <option value="ne" <?= pkg()->request('qmode') == 'ne' ? ' selected': '' ?>>!~</option>
            </select>
            <input type="text" name="query" value="<?= escape(pkg()->request('query')) ?>" placeholder="regex" />
        </td>
        <td>
            <input style="width: 2em" type="text" name="hours" value="<?= escape(pkg()->request('hours')) ?>" placeholder="#" />
        </td>
        <td>
            <input type="submit" value="Search" />
        </td>
    </tr>
    </table>
</form>

<script type="text/javascript">

google.setOnLoadCallback(drawChart);

function drawChart()
{
    var data = new google.visualization.DataTable();

    var cols = <?php print json_encode($g_cols); ?>;
    var rows = <?php print json_encode($g_rows); ?>;

    for (var j in cols)
    {
        data.addColumn(cols[j][1], cols[j][0]);
    }

    for (var i = 0; i < rows.length; i++)
    {
        var point = [];
        for (var j in cols)
        {
            if (cols[j][1] == 'date' || cols[j][1] == 'datetime')
                point.push(new Date(rows[i][j].replace(/-/g, '/')));
            else
                point.push(rows[i][j]);
        }

        data.addRow(point);
    }

    var options = {
        'width'  : '100%',
        'height' : 200,
        'legend' : { 'position': 'top' },
        'chartArea' : { 'width': '91%', 'left': '5%' }
    };

    var chart = new google.visualization.ColumnChart($('#chart').get(0));
    chart.draw(data, options);
}

</script>

<div id="chart"></div>

<table id="sampled-queries">

<tr>
    <th>
        Hosts
    </th>
    <th class="right">
        Hits
    </th>
</tr>

<?php

foreach ($rows as $row)
{
    $hosts = array();
    foreach (explode(',', $row['servers']) as $server_id)
    {
        $host = new Server($server_id);
        $hosts[] = tag('a', array(
            'href' => sprintf('/host/view/%s/%d', $host->name(), $host->port()),
            'html' => escape($host->describe()),
        ));
    }

    $hosts  = join(', ', $hosts);
    $sample = str_replace(',', ', ', $row['sample']);

    $shost = new Server($row['sample_server_id']);
    $sample = sprintf('%s /* %s %s */', $sample, $row['footprint'], $shost->describe());

    if (($ips = find_ipv4($sample)) && ($name = dns_reverse($ips[0], $dns)) && $name != $ips[0])
    {
        $sample = sprintf('%s /* %s */', $sample, $name);
    }

    $cells = array(
        tag('td', array(
            'html' => $hosts,
        )),
        tag('td', array(
            'title' => 'Hits',
            'class' => 'right',
            'html' => tag('a', array(
                'href' => sprintf('/report/sampled_queries_footprint?footprint=%s&host=%s&hours=%s',
                    $row['footprint'],
                    urlencode(pkg()->request('host')),
                    urlencode(pkg()->request('hours'))
                ),
                'html' => $row['hits'],
            )),
        )),
    );

    print tag('tr', array(
        'html' => $cells,
    ));

    $cells = array(
        tag('td', array(
            'colspan' => 2,
            'html' => escape($sample),
        ))
    );

    print tag('tr', array(
        'class' => 'sample',
        'html' => $cells,
    ));

}

?>

</table>

