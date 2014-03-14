
<style>
form {
    display: inline-block;
}
nav {
    display: inline-block;
    float: right;
}
</style>

<form method="GET">
	<strong>host</strong>
	<input type="text" name="host" value="<?= escape(pkg()->request('host')) ?>" />
	<input type="submit" value="Search" />
</form>

<nav>
    <a href="http://ganglia.wikimedia.org/latest/?r=day&cs=&ce=&m=cpu_report&s=by+name&c=MySQL+eqiad&h=&host_regex=&max_graphs=0&tab=m&vn=&hide-hf=false&sh=1&z=small&hc=4">ganglia</a>
</nav>

<?php include 'table.php'; ?>