<!DOCTYPE HTML>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>tendril</title>
    <script type="text/javascript" src="/jquery-1.9.1.min.js"></script>
    <script type="text/javascript" src="https://www.google.com/jsapi"></script>
    <script type="text/javascript" src='https://www.google.com/jsapi?autoload={"modules":[{"name":"visualization","version":"1","packages":["corechart","table","orgchart"]}]}'></script>
    <link href="/normalize.css" rel="stylesheet" type="text/css" />
    <?= pkg()->head() ?>
<style>

html {
    width: 100%;
    background: #eee;
}

body {
    font-family: Arial, sans-serif;
    width: 100%;
    padding: 0;
    min-width: 1024px;
    margin: 0;
    background: #eee;
    -webkit-text-size-adjust: none;
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

textarea {
    -webkit-box-sizing: border-box;
    -moz-box-sizing: border-box;
    box-sizing: border-box;
    width: 100%;
}

#page-header {
    padding: 0.5em 1em;
    background: #000;
    color: #fff;
}

#page-header a {
    color: #fff;
}

#page-header h1 {
    margin: 0;
}

#page-header h1 a {
    text-decoration: none;
}

#page-header nav {
    display: block;
    float: right;
    font-size: 150%;
}

#page-header nav a {
    color: #ddd;
}

#page-header nav a:after {
    color: #888;
    content: ' • ';
    margin: 0 0.25em;
}

#page-header nav a:first-child:before {
    content: '';
}

#page-header nav a:last-child:after {
    content: '';
    margin: 0 0;
}

#page-header nav a:hover {
    color: #fff;
}

#page-content {
    padding: 1em;
}

#page-content:first-child {
    margin-top: 0;
}

#page-content > form {
    background: white;
    margin: 0 0 0.75em 0;
    border: 1px solid rgba(0,0,0,0.2);
}

#page-content > .panel {
    background: white;
    margin: 0 0 0.75em 0;
    border: 1px solid rgba(0,0,0,0.2);
}

#page-content > .note {
    background: ivory;
    margin: 0 0 0.75em 0;
    border: 1px solid rgba(0,0,0,0.2);
    padding: 0.35em 0.5em;
}

#page-content > table {
    background: white;
    margin: 0 0 0.75em 0;
    border: 1px solid rgba(0,0,0,0.2);
}

#page-content > table th {
    font-weight: normal;
    text-align: left;
    font-weight: bold;
    border-bottom: 1px solid rgba(0,0,0,0.2);
}

#page-content > table td {
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

#page-content > table tr:last-child td {
    border-bottom: 0;
}

#page-content > table th,
#page-content > table td {
    padding: 0.25em 0.5em;
}

#page-content > form.search {
    background: aliceblue;
}

#page-content > form.search input[type=text] {
    width: 10em;
}

#page-content > form.search table tr th {
    font-weight: normal;
    text-align: left;
    padding: 0.5em 0.25em 0 0;
    white-space: nowrap;
    font-style: italic;
    font-size: smaller;
}

#page-content > form.search table tr th:first-child {
    padding-left: 0.6em;
}

#page-content > form.search table tr th:last-child {
    padding-right: 0.6em;
}

#page-content > form.search table tr td {
    font-weight: normal;
    text-align: left;
    padding: 0.25em 0.25em 0.25em 0;
    white-space: nowrap;
}

#page-content > form.search table tr td input[type=text] {
    display: inline-block;
    margin-left: 0;
}

#page-content > form.search table tr td:first-child {
    padding-left: 0.35em;
}

#page-content > form.search table tr td:last-child {
    padding-right: 0.35em;
    width: 100%;
}

#page-content > form.search table tr:last-child td {
    padding-bottom: 0.5em;
}

#page-content > *:first-child {
    margin-top: 0;
}

#page-content > .merge-down {
    margin-bottom: 0;
    border-bottom: 0;
}

#page-footer {
    padding: 1em;
}

</style>

</head>

<body>

<header id="page-header">

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

<section role="main" id="page-content">

<?= pkg()->page() ?>

</section>

<footer id="page-footer">

</footer>

</body>
</html>