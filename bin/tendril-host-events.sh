#!/bin/bash

config="$1"
section="$2"

[ "$config"  ] || exit 1
[ "$section" ] || exit 1

mysql="mysql -h db1044 -P 3306 --skip-column-names tendril"

for id in $($mysql -e "select id from servers"); do

    host=$($mysql -e "select host from servers where id = ${id}")
    port=$($mysql -e "select port from servers where id = ${id}")

    echo "$host:$port"

    ./tendril-host-add.sh $host $port $config $section | $mysql
    sleep 1s

done
