#!/bin/bash
docker inspect trustnode-php-1 --format '{{range .Mounts}}{{println .Type .Source "->" .Destination}}{{end}}'
