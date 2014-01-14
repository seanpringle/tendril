
<form method="GET" class="search">

	hosts
	<input type="text" name="hosts" value="<?= escape(pkg()->request('hosts')) ?>" placeholder="regex" />
	vars
	<input list="vars" style="width: 20em;" type="text" name="vars" value="<?= escape(pkg()->request('vars')) ?>" placeholder="regex" />
	mode
	<select name="mode">
		<option value="delta"<?= pkg()->request('mode') == 'delta' ? ' selected':'' ?>>delta</option>
		<option value="value"<?= pkg()->request('mode') == 'value' ? ' selected':'' ?>>value</option>
	</select>

	<input type="submit" value="Search" />
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

<div id="chart"></div>

<?php } ?>

<ul>
	<li><a href="?hosts=masters&vars=questions&mode=delta">Masters, Questions</a></li>
</ul>

<datalist id="vars">
<?php foreach ($status_vars as $var) { ?>
	<option value="<?= escape($var) ?>">
<?php } ?>
</datalist>