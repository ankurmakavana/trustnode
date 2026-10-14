<?php

namespace TrustNode\Cli\Tests;

use PHPUnit\Framework\TestCase;
use TrustNode\Cli\Services\LocalDirectoryPackager;
use ZipArchive;

class LocalDirectoryPackagerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tn_test_pkg_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public function test_packages_eligible_files_and_excludes_sensitive(): void
    {
        // 1. Create source files
        file_put_contents($this->tempDir . '/app.php', '<?php echo "hello";');
        file_put_contents($this->tempDir . '/service.js', 'console.log("service");');
        
        // 2. Create excluded sensitive files
        file_put_contents($this->tempDir . '/.env', 'APP_KEY=secret');
        file_put_contents($this->tempDir . '/server.key', 'PRIVATE_KEY_DATA');
        file_put_contents($this->tempDir . '/id_rsa', 'SSH_KEY_DATA');

        // 3. Create excluded directories
        mkdir($this->tempDir . '/node_modules', 0755, true);
        file_put_contents($this->tempDir . '/node_modules/package.json', '{}');

        mkdir($this->tempDir . '/.git', 0755, true);
        file_put_contents($this->tempDir . '/.git/config', 'gitconfig');

        $packager = new LocalDirectoryPackager();
        $zipOut = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_out_' . uniqid() . '.zip';

        $summary = $packager->package($this->tempDir, $zipOut);

        $this->assertTrue(file_exists($zipOut));
        $this->assertEquals(2, $summary['files_count']);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipOut));
        
        // Assert only eligible files are packaged
        $this->assertNotFalse($zip->locateName('app.php'));
        $this->assertNotFalse($zip->locateName('service.js'));

        // Assert sensitive and dependencies are NOT packaged
        $this->assertFalse($zip->locateName('.env'));
        $this->assertFalse($zip->locateName('server.key'));
        $this->assertFalse($zip->locateName('id_rsa'));
        $this->assertFalse($zip->locateName('node_modules/package.json'));
        $this->assertFalse($zip->locateName('.git/config'));

        $zip->close();
        @unlink($zipOut);
    }

    public function test_eligibility_filter_rules(): void
    {
        $packager = new LocalDirectoryPackager();

        $this->assertTrue($packager->isEligible('src/index.php', 100));
        $this->assertTrue($packager->isEligible('frontend/app.jsx', 500));

        // Traversal rejection
        $this->assertFalse($packager->isEligible('../outside.php', 100));
        $this->assertFalse($packager->isEligible('/root/app.php', 100));

        // Directory rejection
        $this->assertFalse($packager->isEligible('.git/HEAD', 50));
        $this->assertFalse($packager->isEligible('vendor/autoload.php', 50));
        $this->assertFalse($packager->isEligible('node_modules/express/index.js', 50));

        // Secrets rejection
        $this->assertFalse($packager->isEligible('.env', 50));
        $this->assertFalse($packager->isEligible('.env.local', 50));
        $this->assertFalse($packager->isEligible('certs/server.pem', 50));
        $this->assertFalse($packager->isEligible('certs/key.key', 50));
        $this->assertFalse($packager->isEligible('id_rsa', 50));

        // Binary files rejection
        $this->assertFalse($packager->isEligible('assets/logo.png', 50));
        $this->assertFalse($packager->isEligible('bin/app.exe', 50));
        $this->assertFalse($packager->isEligible('database.sqlite', 50));
    }
}
