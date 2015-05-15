
<style type="text/css">
#chart {
    border: 1px solid #ccc;
    min-height: 600px;
    -webkit-box-sizing: border-box;
    -moz-box-sizing: border-box;
    box-sizing: border-box;
}
#quick li {
    margin-bottom: 0.25em;
}
</style>

<form method="GET" class="search merge-down">
    <table cellspacing="0" cellpadding="0">
    <tr>
        <th>Host</th>
        <th>Variable</th>
        <th>Mode</th>
        <th>Group</th>
        <th></th>
    </tr>
    <tr>
        <td>
            <input list="hints" type="text" style="width: 20em;" name="hosts" value="<?= escape(pkg()->request('hosts')) ?>" placeholder="regex" />
        </td>
        <td>
            <input list="vars" style="width: 20em;" type="text" name="vars" value="<?= escape(pkg()->request('vars')) ?>" placeholder="regex" />
        </td>
        <td>
            <select name="mode">
                <option value="delta"<?= pkg()->request('mode') == 'delta' ? ' selected':'' ?>>delta</option>
                <option value="value"<?= pkg()->request('mode') == 'value' ? ' selected':'' ?>>value</option>
            </select>
        </td>
        <td>
            <input type="checkbox" name="vg" value="1" title="group vars" <?= pkg()->request('vg', 'bool', 0) ? 'checked': '' ?> />
        </td>
        <td>
            <input type="submit" value="Search" />
        </td>
    </tr>
    </table>
</form>

<?php if ($cols && $rows) { ?>

<script type="text/javascript">

google.setOnLoadCallback(drawChart);

function drawChart()
{
    var data = new google.visualization.DataTable();

    var cols = <?php print json_encode($cols); ?>;
    var rows = <?php print json_encode($rows); ?>;

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
        'height' : 600,
        'legend' : { 'position': 'top' },
        'chartArea' : { 'width': '86%', 'left': '10%' }
    };

    var chart = new google.visualization.AreaChart($('#chart').get(0));
    chart.draw(data, options);
}

</script>

<?php } ?>

<div id="chart"></div>

<ul id="quick">
    <li>
        <a href="?hosts=slave-per-master&vars=%5Equestions%24&mode=delta">slave-per-master ^questions$</a>
    </li>
    <li>
        <a href="?hosts=slave-per-master&vars=aborted_%28clients%7Cconnects%29&mode=delta">slave-per-master ^aborted_(clients|connects)</a>
    </li>
</ul>

<datalist id="vars">
<?php foreach ($status_vars as $var) { ?>
	<option value="<?= escape($var) ?>">
<?php } ?>
</datalist>

<datalist id="hints">
    <option value="masters">
    <option value="slaves:">
    <option value="family:">
    <option value="slave-per-master">
</datalist>