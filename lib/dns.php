<?php

function dns_reverse($ip)
{
    $name = $ip;
    $ip = preg_replace('/:[0-9]+$/', '', $ip);

    if ($ip && (!($name = cache::get('fqdn:'.$ip)) || !$name))
    {
        $name = gethostbyaddr($ip);
        cache::set('fqdn:'.$ip, $name, 86400);
    }
    return $name;
}