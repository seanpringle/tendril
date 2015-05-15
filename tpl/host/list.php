
<style>
nav {
    display: inline-block;
    float: right;
}
input[name=host] {
    width: 20em !important;
}
</style>

<form method="GET" class="search">
    <table cellspacing="0" cellpadding="0">
    <tr>
        <th>Host</th>
        <th></th>
        <th></th>
    </tr>
    <tr>
        <td>
            <input type="text" name="host" list="hints" value="<?= escape(pkg()->request('host')) ?>" placeholder="regex" />
        </td>
        <td>
            <input type="submit" value="Search" />
        </td>
        <td>
            <nav>
                <a href="http://ganglia.wikimedia.org/latest/?r=day&cs=&ce=&m=cpu_report&s=by+name&c=MySQL+eqiad&h=&host_regex=&max_graphs=0&tab=m&vn=&hide-hf=false&sh=1&z=small&hc=4">ganglia</a>
            </nav>
        </td>
    </tr>
    </table>
</form>


<?php include 'table.php'; ?>

<datalist id="hints">
    <option value="masters">
    <option value="slaves:">
    <option value="family:">
    <option value="slave-per-master">
</datalist>