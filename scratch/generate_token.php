<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::firstOrCreate(['email'=>'system@trustnode.local'], ['name'=>'System CLI', 'password'=>bcrypt('secret'), 'role_id'=>1]);
echo $user->createToken('CLI Token')->plainTextToken;
