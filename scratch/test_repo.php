<?php
use App\Models\Repository;
use App\Models\Scan;

$repoCount = Repository::count();
$scanCount = Scan::where('type', 'repository')->count();

echo "Repositories in DB: " . $repoCount . PHP_EOL;
echo "Repository scans in DB: " . $scanCount . PHP_EOL;

// Check migrations ran
$tables = \Illuminate\Support\Facades\DB::select("SHOW TABLES LIKE 'repositories'");
echo "Repositories table exists: " . (count($tables) > 0 ? 'YES' : 'NO') . PHP_EOL;

// Check GitHubProvider validate
$githubProvider = app(\App\Services\Repository\GitHubProvider::class);
$valid = $githubProvider->validateAccess('https://github.com/octocat/Spoon-Knife', null);
echo "GitHub validate (octocat/Spoon-Knife): " . ($valid ? 'VALID' : 'INVALID') . PHP_EOL;
