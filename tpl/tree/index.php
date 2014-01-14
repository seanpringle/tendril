<style>

#charts table {
    border-collapse: separate !important;
}

#charts > div {
    margin-bottom: 3em;
}

.google-visualization-orgchart-node {
    font-size: 100%;
}

.google-visualization-orgchart-node .stats {
    font-size: 80%;
}

.google-visualization-orgchart-node > a {
    text-decoration: none;
}

</style>


<script type="text/javascript">

google.setOnLoadCallback(drawChart);

function drawChart()
{
    <?php
    foreach ($clusters as $cluster)
    {
        ?>

        (function() {
            var data = new google.visualization.DataTable();

            data.addColumn('string', 'Host');
            data.addColumn('string', 'Master');

            data.addRows(
                <?php print json_encode($cluster) ?>
            );

            var options = {
                allowHtml: true
            };

            var div = $('<div></div>');
            $('#charts').append(div);

            var chart = new google.visualization.OrgChart(div.get(0));
            chart.draw(data, options);

        }());

    <?php
    }
    ?>
}

</script>

<div id="charts">
</div>

