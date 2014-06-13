<!DOCTYPE HTML>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>tendril</title>
    <script type="text/javascript" src="https://code.jquery.com/jquery-1.9.1.js"></script>
    <script type="text/javascript" src="https://www.google.com/jsapi"></script>
    <script type="text/javascript" src='https://www.google.com/jsapi?autoload={"modules":[{"name":"visualization","version":"1","packages":["corechart","table","orgchart"]}]}'></script>
    <link href="/normalize.css" rel="stylesheet" type="text/css" />
    <link href='http://fonts.googleapis.com/css?family=Droid+Serif:400,700|Droid+Sans:400,700' rel='stylesheet' type='text/css'>
    <?= pkg()->head() ?>
<style>

html {
    width: 100%;
    background: #eee;
}

body {
    font-family: "Droid Sans", Arial;
    width: 100%;
    padding: 0;
    min-width: 1024px;
    margin: 0;
    background: #eee;
}

body > header {
    padding: 0.5em 1em;
    background: #000;
    color: #fff;
}

body > header a {
    color: #fff;
}

body > section {
    padding: 1em;
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

body > section > p {
    margin: 0 0 0.75em 0;
}

body > section > table {
    background: #fff;
    border: 1px solid #ccc;
    border-right: 0;
    border-left: 0;
    margin: 0 0 0.75em 0;
}

body > section > table th {
    font-weight: normal;
    text-align: left;
    font-weight: bold;
    border-bottom: 1px solid #ccc;
}

body > section > table td {
    border-bottom: 1px solid #eee;
}

body > section > table th:first-child {
    border-left: 1px solid #ccc;
}

body > section > table td:first-child {
    border-left: 1px solid #ccc;
}

body > section > table th:last-child {
    border-right: 1px solid #ccc;
}

body > section > table td:last-child {
    border-right: 1px solid #ccc;
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

h1 img {
    height: 1.25em;
    vertical-align: text-bottom;
}

h2 {
    font-size: 125%;
}

h3 {
    font-size: 112%;
}

body > header h1 {
    margin: 0;
}

body > header h1 a {
    text-decoration: none;
}

body > header nav {
    display: block;
    float: right;
    font-size: 150%;
}

body > header nav a {
    color: #ddd;
}

body > header nav a:after {
    color: #888;
    content: ' • ';
    margin: 0 0.25em;
}

body > header nav a:first-child:before {
    content: '';
}

body > header nav a:last-child:after {
    content: '';
    margin: 0 0;
}

body > header nav a:hover {
    color: #fff;
}

textarea {
    -webkit-box-sizing: border-box;
    -moz-box-sizing: border-box;
    box-sizing: border-box;
    width: 100%;
}

body > section > p.note {
    border: 1px solid #ccc;
    padding: 0.35em 0.5em;
    background: ivory;
}

body > section > form.search {
    border: 1px solid #ccc;
    background: aliceblue;
}

body > section form.search input[type=text] {
    width: 10em;
}

body > section > form.search table {
}

body > section > form.search table tr th {
    font-weight: normal;
    text-align: left;
    padding: 0.5em 0.25em 0 0;
    white-space: nowrap;
    font-style: italic;
    font-size: smaller;
}

body > section > form.search table tr th:first-child {
    padding-left: 0.6em;
}

body > section > form.search table tr th:last-child {
    padding-right: 0.6em;
}

body > section > form.search table tr td {
    font-weight: normal;
    text-align: left;
    padding: 0.25em 0.25em 0.25em 0;
    white-space: nowrap;
}

body > section > form.search table tr td input[type=text] {
    display: inline-block;
    margin-left: 0;
}

body > section > form.search table tr td:first-child {
    padding-left: 0.35em;
}

body > section > form.search table tr td:last-child {
    padding-right: 0.35em;
    width: 100%;
}

body > section > form.search table tr:last-child td {
    padding-bottom: 0.5em;
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

    <h1>
        <img src="/logo.svg" />
        <a href="/">Tendril</a>
    </h1>

</header>

<section role="main">

<?= pkg()->page() ?>

</section>

<footer>

</footer>

</body>
</html>