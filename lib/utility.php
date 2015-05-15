<?php

function todo()
{
    if (@$_ENV['debug'])
    {
        $msg = 'TODO';
        foreach (func_get_args() as $arg)
            $msg .= ' '.$arg;
        error_log($msg);
    }
}

function backtrace()
{
    ob_start();
    debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    error_log(ob_get_clean());
}

// few python-style functions

function map()
{
    return call_user_func_array('array_map', func_get_args());
}

function reduce($func, $array, $init=null)
{
    return array_reduce($array, $func, $init);
}

function filter($func, $array)
{
    return array_filter($array, $func);
}

function str($arg, $j='')
{
//    if (is_callable($arg))
//        $arg = $arg();
    if (is_array($arg))
        $arg = join($j, $arg);
    return strval($arg);
}

function bool($v)
{
    return $v ? true: false;
}

function keys($a)
{
    return is_array($a) ? array_keys($a): array( str($a) );
}

function vals($a)
{
    return is_array($a) ? array_values($a): array( str($a) );
}

function all($a)
{
    return reduce(function($a, $b) { return $a && $b; }, $a, true);
}

function any($a)
{
    return reduce(function($a, $b) { return $a || $b; }, $a, false);
}

function any_null($a)
{
    return reduce(function($a, $b) { return $a || is_null($b); }, $a, false);
}

function any_empty($a)
{
    return reduce(function($a, $b) { return $a || empty($b); }, $a, false);
}

/**
 * Retrieve a field from an array (say, $_REQUEST) and cast it to a certain type,
 * or fall back on the default value.
 * @param array data source, $_GET/POST/REQUEST or anything really
 * @param string field name in data source
 * @param string data type
 * @param mixed default value
 * @return mixed
 */
function expect($arr, $key, $type='string', $def=null)
{
    if (is_array($arr) && array_key_exists($key, $arr))
    {
        // type can be an array of types
        if (is_array($type))
        {
            $index = array_search($arr[$key], $type);
            return ($index !== false) ? $type[$index]: $def;
        }
        // type can be a string of types | separated
        $types = explode('|', strtolower($type));
        foreach ($types as $type)
        {
            // anything PHP thinks is true becomes proper true.
            if ($type == 'bool' || $type == 'boolean')
                return ($arr[$key]) ? true: false;
            if (is_numeric($arr[$key]))
            {
                if ($type == 'number')
                    return $arr[$key];
                if ($type == 'int' || $type == 'integer' || $type == 'signed')
                    return (int)$arr[$key];
                if ($type == 'float' || $type == 'decimal')
                    return (float)$arr[$key];
                if (($type == 'uint' || $type == 'unsigned') && intval($arr[$key]) >= 0)
                    return (int)$arr[$key];
                if (($type == 'pint' || $type == 'positive') && intval($arr[$key]) > 0)
                    return (int)$arr[$key];
            }
            if ($type == 'hex' && is_scalar($arr[$key]) && preg_match('/^(0x)?[0-9a-fA-F]+$/', $arr[$key]))
                return hexdec($arr[$key]);
            if (($type == 'str' || $type == 'string') && is_scalar($arr[$key]))
                return trim(sprintf("%s", $arr[$key]));
            if ($type == 'array' && is_array($arr[$key]))
                return $arr[$key];
            if ($type == 'object' && is_object($arr[$key]))
                return $arr[$key];
            if ($type == 'csv' && is_scalar($arr[$key]))
                return strlen($arr[$key]) ? preg_split('/\s*,\s*/', trim($arr[$key])) : array();
            if ($type == 'json' && is_scalar($arr[$key]) && strlen($arr[$key]))
                return json_decode($arr[$key], true);
            // type can be a callback function to filter a value
            if (is_string($type) && function_exists($type))
                return $type($arr[$key], $def);
        }
    }
    return $def;
}

/* General Utils */
/**
 * Human readable Unix timestamp
 */
function datetime_casual($stamp, $offset=0, $future=true)
{
    if (is_null($stamp) || intval($stamp) == 0) return 'never';
    if (!is_numeric($stamp)) $stamp = strtotime($stamp);

    $seen = $stamp + $offset;
    $time = time() + $offset;
    if (!$future) $seen = min($time, $seen);
    $diff = abs($time - $seen);

    if ($diff < 10)
        return 'just now';
    if ($diff < 60)
        return sprintf($time > $seen ? '%d second%s ago': 'in %d second%s', max(1, floor($diff)), max(1, floor($diff))>1 ? 's':'');
    if ($diff < 3600)
        return sprintf($time > $seen ? '%d minute%s ago': 'in %d minute%s', max(1, floor($diff/60)), max(1, floor($diff/60))>1 ? 's':'');
    if ($diff < 86400)
        return sprintf($time > $seen ? '%d hour%s ago': 'in %d hour%s', max(1, floor($diff/3600)), max(1, floor($diff/3600))>1 ? 's':'');

    return date_casual($stamp, $offset, $future);
}

/**
 * Human readable Unix timestamp
 */
function date_casual($stamp, $offset=0, $future=true)
{
    if (is_null($stamp) || intval($stamp) == 0) return 'never';
    if (!is_numeric($stamp)) $stamp = strtotime($stamp);

    $seen = $stamp + $offset;
    $time = time() + $offset;
    if (!$future) $seen = min($time, $seen);

    if (date('Y-m-d', $seen) == date('Y-m-d', ($time+86401)))
        return 'tomorrow';
    if (date('Y-m-d', $seen) == date('Y-m-d', $time))
        return 'today';
    if (date('Y-m-d', $seen) == date('Y-m-d', ($time-86401)))
        return 'yesterday';

    $diff = max(86401, abs($seen - $time));
    if ($diff < 86400*365)
    {
        if ($diff/86400 < 7)
            return sprintf($time > $seen ? '%d day%s ago': 'in %d day%s', max(1, floor($diff/86400)), max(1, floor($diff/86400))>1 ? 's':'');
        if ($diff/86400 < 30)
            return sprintf($time > $seen ? '%d week%s ago': 'in %d week%s', max(1, floor($diff/86400/7)), max(1, floor($diff/86400/7))>1 ? 's':'');
        if ($diff/86400 < 90)
            return sprintf($time > $seen ? '%d month%s ago': 'in %d month%s', max(1, floor($diff/86400/30)), max(1, floor($diff/86400/30))>1 ? 's':'');
    }
    return date('M Y', $seen);
}

function number_th($number)
{
    $number = intval($number);
    if ($number > 10 && $number < 20)
        return $number.'th';
    $digit = $number % 10;
    if ($digit == 1) return $number.'st';
    if ($digit == 2) return $number.'nd';
    if ($digit == 3) return $number.'rd';
    return $number.'th';
}

function duration_casual($secs, $plural=false)
{
    if ($secs > 86400) return sprintf('%d day%s', floor($secs/86400), $plural && floor($secs/86400) > 1 ? 's':'');
    if ($secs > 3600) return sprintf('%d hour%s', floor($secs/3600), $plural && floor($secs/3600) > 1 ? 's':'');
    if ($secs > 60) return sprintf('%d min%s', floor($secs/60), $plural && floor($secs/60) > 1 ? 's':'');
    return sprintf('%d sec%s', floor($secs), $plural && floor($secs) > 1 ? 's':'');
}

function duration_short($secs)
{
    $string = duration_casual($secs);
    $string = preg_replace('/ days?/',  'd', $string);
    $string = preg_replace('/ hours?/', 'h', $string);
    $string = preg_replace('/ mins?/',  'm', $string);
    $string = preg_replace('/ secs?/',  's', $string);
    return $string;
}

function text_as_url($text)
{
    $text = preg_replace('/[^a-z0-9]/i', '-', $text);
    $text = preg_replace('/[-]+/', '-', $text);
    return $text;
}

function browser_detect($agent=null)
{
    $agent = strtolower($agent ? $agent : $_SERVER['HTTP_USER_AGENT']);

    if (preg_match_all('/chrome\/([0-9]+\.[0-9]+)/', $agent, $matches))
        return 'chrome '.$matches[1][0];

    if (preg_match_all('/firefox\/([0-9]+\.[0-9]+)/', $agent, $matches))
        return 'firefox '.$matches[1][0];

    if (preg_match_all('/thunderbird\/([0-9]+\.[0-9]+)/', $agent, $matches))
        return 'thunderbird '.$matches[1][0];

    if (preg_match_all('/opera\/([0-9]+\.[0-9]+)/', $agent, $matches))
        return 'opera '.$matches[1][0];

    if (preg_match_all('/msie ([0-9]+\.[0-9]+)/', $agent, $matches))
        return 'msie '.$matches[1][0];

    if (preg_match_all('/safari\/([0-9]+\.[0-9]+)/', $agent, $matches) && preg_match('/(macintosh|ipad|iphone)/', $agent))
        return 'safari '.$matches[1][0];

    if (preg_match_all('/webkit\/([0-9]+\.[0-9]+)/', $agent, $matches))
        return 'webkit '.$matches[1][0];

    return 'unknown 1.0';
}

function encrypt($text, $salt)
{
    return trim(
        mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $salt, $text, MCRYPT_MODE_ECB,
            mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND)
        )
    );
}

function decrypt($text, $salt)
{
    return trim(
        mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $salt, $text, MCRYPT_MODE_ECB,
            mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND)
        )
    );
}

function escape($str)
{
    $charsets = array(
        'UTF-8',
        'ISO-8859-1',
        'ISO-8859-15',
        'GB2312',
        'BIG5',
        'BIG5-HKSCS',
        'Shift_JIS',
        'EUC-JP',
        'KOI8-R',
        'ISO-8859-5',
        'cp1251',
        'cp1252',
        'MacRoman',
    );

    $test = false;
    foreach ($charsets as $charset)
    {
        if ($test === false) $test = @iconv($charset, 'UTF-8//TRANSLIT', $str);
        if ($test !== false) { $str = $test; break; }
    }

    $flags = ENT_QUOTES;
    if (defined('ENT_SUBSTITUTE')) $flags |= ENT_SUBSTITUTE; // php 5.4
    if (defined('ENT_HTML5'))      $flags |= ENT_HTML5;      // php 5.4

    return htmlentities($str, $flags, 'UTF-8');
}

function find_ipv4($str)
{
    $ips = array();
    if (preg_match_all('/[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+/', $str, $matches))
    {
        foreach ($matches as $match)
            $ips[] = $match[0];
    }
    return $ips;
}

function truncate($str, $len, $ext='...')
{
    $len -= strlen($ext);
    return strlen($str) > $len ? substr($str, 0, $len) . $ext : $str;
}

function redirect($url)
{
    if (@$_ENV['debug'])
    {
        error_log("redirect: $url\n");
        printf('<a href="%s">redirect: %s</a>', $url, escape($url));
        printf('<pre>%s</pre>', print_r($_REQUEST, true));
    }
    else
    {
        header('Location: '. $url);
    }
    die;
}

function url($url, $vars)
{
    foreach ($vars as $key => $val)
        $vars[$key] = is_null($val) ? null: urlencode($val);

    if (strpos($url, '?') !== false)
    {
        list ($url, $get) = explode('?', $url);

        foreach (explode('&', $get) as $pair)
        {
            $key = $pair; $val = null;
            if (strpos($key, '=') !== false)
                list ($key, $val) = explode('=', $key);
            if (!isset($vars[$key]))
                $vars[$key] = $val;
        }
    }

    $get = array();
    foreach ($vars as $key => $val)
    {
        $pair = array($key);
        if (!is_null($val)) $pair[] = $val;
        $get[] = join('=', $pair);
    }

    return sprintf('%s?%s', $url, join('&', $get));
}


function tag($tag, $txt='', $vars= array())
{
    if (is_array($txt))
    {
        $vars = $txt;
        $txt = '';
    }

    if (is_string($vars))
    {
        $vars = array( 'class' => $vars );
    }

    if (isset($vars['html']))
    {
        $txt = str($vars['html']);
        unset($vars['html']);
    }

    if (expect($vars, 'class', 'array'))
    {
        $vars['class'] = join(' ', $vars['class']);
    }

    if (expect($vars, 'style', 'array'))
    {
        $vars['style'] = map(
            function() {
                return sprintf('%s: %s;', $a, $b);
            },
            keys($vars['style']),
            vals($vars['style'])
        );
    }

    $attr = join(' ', map(
        function($key, $val) {
            return is_null($val) ? $key: sprintf('%s="%s"', $key, $val);
        },
        keys($vars),
        vals($vars)
    ));

    if (preg_match('/^(br|hr|input|link|meta|img)$/', $tag))
        return sprintf('<%s %s/>', $tag, $attr);

    $eol = preg_match('/^(div|p|h[12345]|section|article|header|footer)$/', $tag)
        ? "\n": '';

    $txt = str($txt);

    return sprintf('<%s %s>%s%s%s</%s>%s', $tag, $attr, $eol, $txt, $eol, $tag, $eol);
}


function span_since($stamp, $vars= array())
{
    $str = datetime_casual($stamp);
    if ($str == 'just now')
    {
        $number = '';
        $period = 'now';
    }
    else
    if (preg_match('/(tomorrow|today|yesterday)/', $str))
    {
        $number = '';
        $period = $str;
    }
    else
    if (preg_match('/(second|minute|hour|day|month|year)/', $str))
    {
        list ($number, $period) = explode(' ', $str);
    }
    else
    {
        $number = $str;
        $period = 'date';
    }
    return tag('span', array(
        'class' => 'since '.$period,
        'title' => date('Y-m-d H:i:s', is_numeric($stamp) ? $stamp: strtotime($stamp)),
        'html' => $number,
    ));
}


function lorem_ipsum($words=50)
{
    $text = preg_split('/\s+/', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec congue arcu eget ipsum iaculis ornare.'
        .' Nulla semper, tortor sed dapibus laoreet, ante tortor feugiat risus, id placerat nunc mi quis enim.'
        .' Fusce sollicitudin diam a tellus molestie pellentesque. Nam et massa et enim malesuada vestibulum ac in arcu.'
        .' Praesent ipsum metus, pharetra vel aliquam eget, auctor a lorem. Suspendisse non dui sit amet augue mattis'
        .' mattis et id sapien. Duis vulputate scelerisque pulvinar. Vestibulum porttitor vehicula varius. Sed sagittis'
        .' rhoncus consequat. Ut fringilla quam id arcu rutrum pulvinar. Vivamus nec tortor vel augue interdum sagittis.'
        .' Donec venenatis, ipsum eu tincidunt interdum, mauris velit cursus odio, in facilisis elit turpis quis dui.'
        .' Proin volutpat ornare fermentum. Nulla quam enim, molestie at tristique nec, iaculis sed ipsum. Etiam suscipit,'
        .' dolor id malesuada lacinia, neque quam tempus urna, quis aliquam nunc diam tincidunt est. Nam ultricies mi eget'
        .' ipsum eleifend ut eleifend orci elementum.');
    return join(' ', array_slice($text, 0, $words));
}

function l() {
    return func_get_args();
}

function a() {
    $array = array(); $args = func_get_args();
    for ($i = 0; $i < count($args); $i += 2)
        $array[$args[$i]] = isset($args[$i+1]) ? $args[$i+1]: null;
    return $array;
}

function suffix($s)
{
    return tag('span', array( 'class' => 'suffix', 'html' => escape($s)));
}

function dns_reverse($ip, $map=null)
{
    $name = $ip;
    $ip = preg_replace('/:[0-9]+$/', '', $ip);

    if ($ip)
    {
        $name = is_array($map) && isset($map[$ip])
            ? $map[$ip] : Cache::get('fqdn:'.$ip);
    }
    if (!$name)
    {
        $name = gethostbyaddr($ip);
        Cache::set('fqdn:'.$ip, $name, 86400);
    }
    return $name;
}