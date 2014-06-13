<style>

#charts {
    background: #fff;
    border: 1px solid #ccc;
    padding: 1em;
}

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

.google-visualization-orgchart-node > a.disabled {
    color: #999;
}

.google-visualization-orgchart-node > a.lagging {
    color: red;
}

</style>

<p class="note" style="text-align: center;">
    Generated <em>only</em> from the replication state reported by each server.<br>
    If this doesn't match up with puppet and <a href="http://noc.wikimedia.org/dbtree/">dbtree</a>,
    immediately do a sanity check!
</p>

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

