#!/bin/bash

host="$1"
port="$2"

[ "$host" ] || exit 1
[ "$port" ] || exit 1

server=$(echo "$host.$port" | sed 's/\./_/g')

cat <<eod

select @server_id := id from servers where host = '${host}' and port = ${port};

update servers set enabled = 0 where host = '${host}' and port = ${port};

alter event ${server}_schema disable;
alter event ${server}_activity disable;
alter event ${server}_status disable;
alter event ${server}_variables disable;
alter event ${server}_usage disable;
alter event ${server}_privileges disable;

eod