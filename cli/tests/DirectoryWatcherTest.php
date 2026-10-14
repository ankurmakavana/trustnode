<?php

namespace Tests\Unit;

require_once __DIR__ . '/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use TrustNode\Cli\Services\DirectoryWatcher;

class DirectoryWatcherTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tn_watcher_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    public function test_snapshot_detects_eligible_files_and_ignores_secrets(): void
    {
        $watcher = new DirectoryWatcher();

        // Create eligible file
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'index.php', '<?php echo "hello";');
        // Create secret file (.env) - must be ignored
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . '.env', 'SECRET=password');
        // Create private key - must be ignored
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'server.key', 'PRIVATE KEY');

        $snapshot = $watcher->getSnapshot($this->tempDir);

        $this->assertArrayHasKey('index.php', $snapshot);
        $this->assertArrayNotHasKey('.env', $snapshot);
        $this->assertArrayNotHasKey('server.key', $snapshot);
    }

    public function test_diff_detects_added_modified_and_removed_files(): void
    {
        $watcher = new DirectoryWatcher();

        $fileA = $this->tempDir . DIRECTORY_SEPARATOR . 'a.txt';
        $fileB = $this->tempDir . DIRECTORY_SEPARATOR . 'b.txt';
        file_put_contents($fileA, 'initial');
        file_put_contents($fileB, 'keep');

        $snap1 = $watcher->getSnapshot($this->tempDir);

        // Modify fileA, delete fileB, add fileC
        sleep(1); // Ensure mtime changes
        file_put_contents($fileA, 'modified content');
        @unlink($fileB);
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'c.txt', 'new');

        $snap2 = $watcher->getSnapshot($this->tempDir);

        $diff = $watcher->diff($snap1, $snap2);

        $this->assertTrue($diff['changed']);
        $this->assertEquals(1, $diff['added']);
        $this->assertEquals(1, $diff['modified']);
        $this->assertEquals(1, $diff['removed']);
    }
}
