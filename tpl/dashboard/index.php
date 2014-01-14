
<script type="text/javascript">

    google.setOnLoadCallback(drawChart);

    function drawChart() {

        var data = new google.visualization.DataTable();

        var cols = <?= json_encode($cols) ?>;
        var rows = <?= json_encode($rows) ?>;

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
            'title'  : 'Com_kill',
            'width'  : 500,
            'height' : 200,
            'legend' : { 'position': 'top' },
            'chartArea' : { 'width': '90%', 'left': '10%' }
        };

        var chart = new google.visualization.AreaChart($('#com_kill').get(0));
        chart.draw(data, options);

    }

</script>


<div id="com_kill">
</div>