
<style>
#sampled-queries-footprint .right {
    text-align: right;
}
#sampled-queries-footprint tr.sample {
    font-size: smaller;
    color: #999;
}
#chart {
    border: 1px solid #999;
    margin-bottom: 1em;
}
body > section form.search input[name=footprint] {
    width: 20em;
}
</style>

<form method="GET" class="search">
    <table cellspacing="0" cellpadding="0">
    <tr>
        <th>Footprint</th>
        <th>Host</th>
        <th>Hours</th>
        <th></th>
    </tr>
    <tr>
        <td>
            <input type="text" name="footprint" value="<?= escape(pkg()->request('footprint')) ?>" placeholder="regex" />
        </td>
        <td>
            <input type="text" name="host" value="<?= escape(pkg()->request('host')) ?>" placeholder="regex" />
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

<table id="sampled-queries-footprint">

<tr>
    <th class="">
        Host
    </th>
    <th class="right">
        Stamp
    </th>
</tr>

<?php

foreach ($rows as $row)
{
    $host = new Server($row['server_id']);
    $sample = str_replace(',', ', ', $row['content']);

    $cells = array(
        tag('td', array(
            'title' => 'Host',
            'html' =>
                tag('a', array(
                    'href' => sprintf('/host/view/%s/%d', $host->name(), $host->port()),
                    'html' => escape($host->describe()),
                )),
        )),
        tag('td', array(
            'class' => 'right',
            'title' => datetime_casual($row['stamp']),
            'html' => date('Y-m-d H:i:s', strtotime($row['stamp'])),
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

