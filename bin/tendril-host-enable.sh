#!/bin/bash

host="$1"
port="$2"

[ "$host" ] || exit 1
[ "$port" ] || exit 1

server=$(echo "$host.$port" | sed 's/\./_/g')

cat <<eod

select @server_id := id from servers where host = '${host}' and port = ${port};

update servers set enabled = 1 where host = '${host}' and port = ${port};

alter event ${server}_schema enable;
alter event ${server}_activity enable;
alter event ${server}_status enable;
alter event ${server}_variables enable;
alter event ${server}_usage enable;
alter event ${server}_privileges enable;

eod