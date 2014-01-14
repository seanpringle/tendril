<style>

table.host-vars {
    width: 100%;
    table-layout: fixed;
    overflow: hidden;
}

#graphs {
    width: 1200px;
}

#graphs .group .chart {
    display: inline-block;
    margin-bottom: 20px;
    height: 200px;
    width: 500px;
    background: #eee;
    margin-right: 20px;
}

#graphs-blurb {
    margin: 0 0 1.5em 0;
}

</style>

<h3>
    <?= escape($host->describe()) ?> : replication family
</h3>

<?php include 'table.php'; ?>

<h3>
    <?= escape($host->describe()) ?> : graphs
</h3>

<div id="graphs-blurb">
    Only a general overview. For more detail see
    <nav style="display: inline-block;">
        <a href="http://ganglia.wikimedia.org/latest/?r=hour&tab=ch&hreg[]=^<?= $host->name_short() ?>">ganglia</a>
        <a href="https://icinga-admin.wikimedia.org/cgi-bin/icinga/status.cgi?host=<?= $host->name_short() ?>&nostatusheader">icinga</a>
        <a href="https://ishmael.wikimedia.org/?host=<?= $host->name_short() ?>">slow</a>
        <a href="https://ishmael.wikimedia.org/sample/?host=<?= $host->name_short() ?>">sampled</a>
    </nav>
</div>

<div id="graphs">

    <script type="text/javascript">

    google.setOnLoadCallback(drawCharts);

    function drawChart(div, title, description, fields, type)
    {
        var data = {
            'type' : type,
            'fields' : fields,
        };

        $.getJSON('/host/chart/<?= $host->name() ?>/<?= $host->port ?>', data, function(r) {

            if (r[0] == 'ok')
            {
                var data = new google.visualization.DataTable();

                var cols = r[1][0];
                var rows = r[1][1];

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
                    'title'  : title + ' -- ' + description,
                    'width'  : 500,
                    'height' : 200,
                    'legend' : { 'position': 'top' },
                    'chartArea' : { 'width': '90%', 'left': '10%' }
                };

                var chart = new google.visualization.AreaChart($('#'+div).get(0));
                chart.draw(data, options);

                //setTimeout(function() { drawChart(div, title, description, fields, type); }, 60*1000);
            }
        });
    }

    function drawCharts() {

    <?php

    $groups = '';

    foreach ($graphs as $title => $row)
    {
        $group = '';

        foreach ($row as $i => $report)
        {
            $hash = md5(join(':', array($i, $title, $report->fields())));

            $group .= tag('div', array(
                'id' => 'chart' . $hash,
                'class' => 'chart',
            ));

            ?>

            drawChart(
                'chart<?= $hash ?>',
                "<?= escape($title) ?>",
                "<?= escape($report->description()) ?>",
                "<?= $report->fields() ?>",
                "<?= get_class($report) ?>"
            );

            <?php
        }

        $groups .= tag('div', array(
            'class' => 'group',
            'html' => $group,
        ));
    }

    ?>

    }

    </script>

    <div id="groups">

        <?= $groups ?>

    </div>

</div>

</div>

<h3>
    <?= escape($host->describe()) ?> : grants
</h3>

<table id="host-grants" class="host-grants">

<?php

foreach ($grants as $row)
{
    $cells = array(
        tag('td', array(
            'class' => 'grantee',
            'html' => escape($row['grantee']),
        )),
        tag('td', array(
            'class' => 'schema',
            'html' => escape($row['table_schema']),
        )),
        tag('td', array(
            'class' => 'privileges',
            'html' => escape(str_replace(',', ', ', $row['privileges'])),
        )),
    );

    print tag('tr', array(
        'html' => str($cells),
    ));
}

?>

</table>

<h3>
    <?= escape($host->describe()) ?> : slave status
</h3>

<table id="host-slave-status" class="host-vars">

<tr>
    <th class="name">Status Variable</th>
    <th class="value">Value</th>
</tr>

<?php

foreach ($slave_status as $var_key => $var_val)
{
    $cells = array(
        tag('td', array(
            'class' => 'name',
            'html'  => escape($var_key),
        )),
        tag('td', array(
            'class' => 'value',
            'title' => escape($var_val),
            'html'  => escape($var_val),
        )),
    );

    print tag('tr', str($cells));
}

?>

</table>

<h3>
    <?= escape($host->describe()) ?> : system state
</h3>

<div style="width: 50%; float: left;">

<table id="host-variables" class="host-vars">

<tr>
    <th class="name">Variable</th>
    <th class="value">Value</th>
</tr>

<?php

foreach ($variables as $var_key => $var_val)
{
    $cells = array(
        tag('td', array(
            'class' => 'name',
            'html'  => escape($var_key),
        )),
        tag('td', array(
            'class' => 'value',
            'title' => escape($var_val),
            'html'  => escape($var_val),
        )),
    );

    print tag('tr', str($cells));
}

?>

</table>

</div>

<div style="width: 50%; float: right;">

<table id="host-status" class="host-vars">

<tr>
    <th class="name">Status Variable</th>
    <th class="value">Value</th>
</tr>

<?php

foreach ($status as $var_key => $var_val)
{
    $cells = array(
        tag('td', array(
            'class' => 'name',
            'html'  => escape($var_key),
        )),
        tag('td', array(
            'class' => 'value',
            'title' => escape($var_val),
            'html'  => escape($var_val),
        )),
    );

    print tag('tr', str($cells));
}

?>

</table>

</div>