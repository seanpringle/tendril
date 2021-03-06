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
        white-space: nowrap;
    }

    #charts div.lag, #charts div.qps, #charts div.ver {
        font-size: smaller;
    }

    #charts div.lagging {
        color: red;
    }

    .google-visualization-orgchart-node {
        font-size: 100%;
        box-shadow: 2px 2px 2px #666;
        border-radius: 5px;
        padding: 0.5em;
        background: #eee;
        border: 1px solid #666;
    }

    .google-visualization-orgchart-node .stats {
        font-size: 80%;
    }

    .google-visualization-orgchart-node > a {
        text-decoration: none;
        color: blue;
    }

    .google-visualization-orgchart-node > a:visited {
        color: blue;
    }

    .google-visualization-orgchart-node > a.disabled {
        color: #999;
    }

    .google-visualization-orgchart-node > a.lagging {
        color: red;
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

<div id="charts" class="panel">
</div>

