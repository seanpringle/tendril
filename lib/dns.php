<?php

function dns_reverse($ip, $map=null)
{
    $name = $ip;
    $ip = preg_replace('/:[0-9]+$/', '', $ip);

    if ($ip)
    {
        $name = is_array($map) && isset($map[$ip])
            ? $map[$ip] : cache::get('fqdn:'.$ip);
    }
    if (!$name)
    {
        $name = gethostbyaddr($ip);
        cache::set('fqdn:'.$ip, $name, 86400);
    }
    return $name;
}