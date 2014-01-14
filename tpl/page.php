<!DOCTYPE HTML>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>tendril</title>
    <script type="text/javascript" src="http://code.jquery.com/jquery-1.9.1.js"></script>
    <script type="text/javascript" src="https://www.google.com/jsapi"></script>
    <script type="text/javascript" src='https://www.google.com/jsapi?autoload={"modules":[{"name":"visualization","version":"1","packages":["corechart","table","orgchart"]}]}'></script>
    <link href="/normalize.css" rel="stylesheet" type="text/css" />
    <link href='http://fonts.googleapis.com/css?family=Droid+Serif:400,700|Droid+Sans:400,700' rel='stylesheet' type='text/css'>
    <?= pkg()->head() ?>
<style>

html {
    width: 100%;
}

body {
    font-family: "Droid Sans", Arial;
    width: 98%;
    padding: 0 1%;
    min-width: 1024px;
    margin: 0 auto;
}

body > header {
    padding: 0.5em 1em;
    background: #eee;
    border: 1px solid #999;
    border-top: 0;
}

body > section {
    padding: 1em 0;
}

body > section:first-child {
    margin-top: 0;
}

body > footer {
    padding: 1em;
}

body > section > form {
    margin: 0 0 0.75em 0;
}

body > section > table {
    border: 1px solid #999;
    border-right: 0;
    border-left: 0;
    margin: 0 0 0.75em 0;
}

body > section > table th {
    font-weight: normal;
    text-align: left;
    background: #eee;
    border-bottom: 1px solid #999;
}

body > section > table td {
    border-bottom: 1px solid #eee;
}

body > section > table th:first-child {
    border-left: 1px solid #999;
}

body > section > table td:first-child {
    border-left: 1px solid #999;
}

body > section > table th:last-child {
    border-right: 1px solid #999;
}

body > section > table td:last-child {
    border-right: 1px solid #999;
}

body > section > table tr:last-child td {
    border-bottom: 0;
}

body > section > table th,
body > section > table td {
    padding: 0.25em 0.5em;
}

body > section > *:first-child {
    margin-top: 0;
}

nav a {
    text-decoration: none;
}

nav a:after {
    color: #ccc;
    content: ' | ';
}

nav a:first-child:before {
    color: #ccc;
    content: '[ ';
}

nav a:last-child:after {
    content: ' ]';
}

h1 {
    font-size: 150%;
}

h2 {
    font-size: 125%;
}

h3 {
    font-size: 112%;
}

header h1 {
    margin: 0;
}

header nav {
    display: block;
    float: right;
    font-size: 125%;
}

p.note {

}

form.search input[type=text] {
    width: 8em;
}

</style>

</head>

<body>

<header>

    <nav>
        <a href="/host">Hosts</a>
        <a href="/activity?research=0&labsusers=0">Activity</a>
        <a href="/tree">Tree</a>
        <a href="/chart">Chart</a>
        <a href="/report">Report</a>
    </nav>

    <h1>tendril</h1>

</header>

<section role="main">

<?= pkg()->page() ?>

</section>

<footer>

</footer>

</body>
</html>