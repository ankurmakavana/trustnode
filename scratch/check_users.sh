#!/usr/bin/env bash
set -e

# Write a PHP snippet to a temp file inside the container
docker exec trustnode-php-1 bash -c "cat > /tmp/check_users.php << 'PHPEOF'
<?php
\$users = \Illuminate\Support\Facades\DB::table('users')->select('id','email','name')->get();
foreach (\$users as \$u) {
    echo \$u->id . ' | ' . \$u->email . ' | ' . \$u->name . PHP_EOL;
}
PHPEOF
cd /var/www/html && php artisan tinker --execute=\"require '/tmp/check_users.php';\""

