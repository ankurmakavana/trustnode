<?php

namespace Tests\Unit;

use App\Services\Scan\Scanners\SastScanner;
use PHPUnit\Framework\TestCase;

class SastContextAnalyzerTest extends TestCase
{
    private SastScanner $scanner;

    protected function setUp(): void
    {
        $this->scanner = new SastScanner();
    }

    public function test_sql_injection_true_positive(): void
    {
        $content = <<<'PHP'
<?php
$id = $_GET['id'];
$query = "SELECT * FROM users WHERE id = " . $id;
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertNotEmpty($findings);
        $this->assertContains('SEC-SAST-SQLI', array_map(fn ($f) => $f->scannerRuleId, $findings));
    }

    public function test_sql_cast_variant_keeps_finding_with_sanitizer_context(): void
    {
        $content = <<<'PHP'
<?php
$id = (int) $_GET['id'];
$query = "SELECT * FROM users WHERE id = " . $id;
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertNotEmpty($findings);
        $finding = $findings[0];
        $this->assertStringContainsString('sanitizer detected', $finding->technicalDetails ?? '');
    }

    public function test_command_injection_true_positive(): void
    {
        $content = <<<'PHP'
<?php
$name = $_GET['name'];
shell_exec("echo " . $name);
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertNotEmpty($findings);
        $this->assertContains('SEC-SAST-CMD', array_map(fn ($f) => $f->scannerRuleId, $findings));
    }

    public function test_command_sanitizer_reduces_but_does_not_hide(): void
    {
        $content = <<<'PHP'
<?php
$name = escapeshellarg($_GET['name']);
shell_exec("echo " . $name);
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertNotEmpty($findings);
        $finding = $findings[0];
        $this->assertStringContainsString('sanitizer detected', $finding->technicalDetails ?? '');
    }

    public function test_eval_true_positive(): void
    {
        $content = <<<'PHP'
<?php
$code = $_POST['code'];
eval($code);
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertNotEmpty($findings);
        $this->assertContains('SEC-SAST-EVAL', array_map(fn ($f) => $f->scannerRuleId, $findings));
    }

    public function test_path_traversal_true_positive(): void
    {
        $content = <<<'PHP'
<?php
$file = $_GET['file'];
file_get_contents($file);
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertNotEmpty($findings);
        $this->assertContains('SEC-SAST-PATH', array_map(fn ($f) => $f->scannerRuleId, $findings));
    }

    public function test_path_basename_variant_is_contextualized(): void
    {
        $content = <<<'PHP'
<?php
$file = basename($_GET['file']);
file_get_contents($file);
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertNotEmpty($findings);
        $finding = $findings[0];
        $this->assertStringContainsString('sanitizer detected', $finding->technicalDetails ?? '');
    }

    public function test_constant_sql_is_not_flagged_as_user_controlled(): void
    {
        $content = <<<'PHP'
<?php
$query = "SELECT * FROM users WHERE id = 10";
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertEmpty($findings);
    }

    public function test_multiline_sql_flow_is_detected(): void
    {
        $content = <<<'PHP'
<?php
$id = $_GET['id'];

$query =
    "SELECT * FROM users WHERE id = "
    . $id;
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertNotEmpty($findings);
        $this->assertContains('SEC-SAST-SQLI', array_map(fn ($f) => $f->scannerRuleId, $findings));
    }

    public function test_existing_pattern_fallback_still_detects_when_source_is_uncertain(): void
    {
        $content = <<<'PHP'
<?php
$x = $input;
$query = "SELECT * FROM users WHERE id = " . $x;
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertNotEmpty($findings);
        $this->assertContains('SEC-SAST-SQLI', array_map(fn ($f) => $f->scannerRuleId, $findings));
    }

    public function test_function_scope_isolation_blocks_taint_leak_between_unrelated_functions(): void
    {
        $content = <<<'PHP'
<?php
function first() {
    $id = $_GET['id'];
}

function second() {
    $query = "SELECT * FROM users WHERE id = " . $id;
}
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertEmpty($findings);
    }

    public function test_same_function_propagation_remains_active(): void
    {
        $content = <<<'PHP'
<?php
function first() {
    $id = $_GET['id'];
    $query = "SELECT * FROM users WHERE id = " . $id;
}
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertNotEmpty($findings);
        $this->assertContains('SEC-SAST-SQLI', array_map(fn ($f) => $f->scannerRuleId, $findings));
    }

    public function test_same_variable_name_in_different_functions_is_isolated(): void
    {
        $content = <<<'PHP'
<?php
function first() {
    $id = $_GET['id'];
}

function second() {
    $id = 42;
    $query = "SELECT * FROM users WHERE id = " . $id;
}
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertEmpty($findings);
    }

    // P0.2 Implementation Tests

    public function test_two_hop_source_flow(): void
    {
        $content = <<<'PHP'
<?php
$id = $_GET['id'];
$value = $id;
$query = "SELECT * FROM users WHERE id = " . $value;
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertNotEmpty($findings);
        $finding = $findings[0];
        $this->assertStringContainsString('source -> sink without sanitizer (user-controlled input)', $finding->technicalDetails ?? '');
    }

    public function test_three_hop_source_flow(): void
    {
        $content = <<<'PHP'
<?php
$id = $_GET['id'];
$value = $id;
$final = $value;
$query = "SELECT * FROM users WHERE id = " . $final;
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertNotEmpty($findings);
        $finding = $findings[0];
        $this->assertStringContainsString('source -> sink without sanitizer (user-controlled input)', $finding->technicalDetails ?? '');
    }

    public function test_command_multi_hop_flow(): void
    {
        $content = <<<'PHP'
<?php
$name = $_GET['name'];
$value = $name;
shell_exec("echo " . $value);
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertNotEmpty($findings);
        $finding = $findings[0];
        $this->assertStringContainsString('source -> sink without sanitizer (user-controlled input)', $finding->technicalDetails ?? '');
    }

    public function test_sanitizer_propagation(): void
    {
        $content = <<<'PHP'
<?php
$name = $_GET['name'];
$safe = escapeshellarg($name);
$value = $safe;
shell_exec("echo " . $value);
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertNotEmpty($findings);
        $finding = $findings[0];
        $this->assertStringContainsString('source -> sink with sanitizer detected (escapeshellarg)', $finding->technicalDetails ?? '');
    }

    public function test_constant_then_source(): void
    {
        $content = <<<'PHP'
<?php
$value = "constant";
$value = $_GET['id'];
$query = "SELECT * FROM users WHERE id = " . $value;
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertNotEmpty($findings);
        $finding = $findings[0];
        $this->assertStringContainsString('source -> sink without sanitizer (user-controlled input)', $finding->technicalDetails ?? '');
    }

    public function test_unknown_origin(): void
    {
        $content = <<<'PHP'
<?php
$input = externalFunction();
$value = $input;
$query = "SELECT * FROM users WHERE id = " . $value;
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertNotEmpty($findings);
        $finding = $findings[0];
        $this->assertTrue(
            str_contains($finding->technicalDetails ?? '', 'constant expression without user-controlled source') ||
            str_contains($finding->technicalDetails ?? '', 'fallback pattern match kept because source origin could not be safely established')
        );
    }

    public function test_five_hop_limit(): void
    {
        $content = <<<'PHP'
<?php
$one = $_GET['id'];
$two = $one;
$three = $two;
$four = $three;
$five = $four;
$six = $five;
$query = "SELECT * FROM users WHERE id = " . $six;
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertNotEmpty($findings);
        $finding = $findings[0];
        $this->assertStringContainsString('source -> sink without sanitizer (user-controlled input)', $finding->technicalDetails ?? '');
    }

    public function test_six_hop_limit(): void
    {
        $content = <<<'PHP'
<?php
$one = $_GET['id'];
$two = $one;
$three = $two;
$four = $three;
$five = $four;
$six = $five;
$seven = $six;
$query = "SELECT * FROM users WHERE id = " . $seven;
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertNotEmpty($findings);
        $finding = $findings[0];
        // Should either have source context (if within limit) or fallback behavior
        $this->assertTrue(
            str_contains($finding->technicalDetails ?? '', 'source -> sink without sanitizer (user-controlled input)') ||
            str_contains($finding->technicalDetails ?? '', 'constant expression without user-controlled source') ||
            str_contains($finding->technicalDetails ?? '', 'fallback pattern match kept because source origin could not be safely established')
        );
    }

    // P0.3-B.1 Implementation Tests - Array Property Support

    public function test_array_property_source_to_sink(): void
    {
        $content = <<<'PHP'
<?php
$user['id'] = $_GET['id'];
$query = "SELECT * FROM users WHERE id = " . $user['id'];
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertNotEmpty($findings);
        $this->assertContains('SEC-SAST-SQLI', array_map(fn ($f) => $f->scannerRuleId, $findings));
    }

    public function test_array_property_safe_constant_not_flagged(): void
    {
        $content = <<<'PHP'
<?php
$user['id'] = 42;
$query = "SELECT * FROM users WHERE id = " . $user['id'];
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertEmpty($findings);
    }

    public function test_array_property_to_scalar_propagation(): void
    {
        $content = <<<'PHP'
<?php
$user['id'] = $_GET['id'];
$id = $user['id'];
$query = "SELECT * FROM users WHERE id = " . $id;
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertNotEmpty($findings);
        $finding = $findings[0];
        $this->assertStringContainsString('source -> sink without sanitizer (user-controlled input)', $finding->technicalDetails ?? '');
    }

    public function test_different_array_keys_remain_independent(): void
    {
        $content = <<<'PHP'
<?php
$user['id'] = $_GET['id'];
$user['name'] = "safe";
$query = "SELECT * FROM users WHERE name = " . $user['name'];
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertEmpty($findings);
    }

    public function test_array_property_reassignment_latest_wins(): void
    {
        $content = <<<'PHP'
<?php
$user['id'] = $_GET['id'];
$user['id'] = 42;
$query = "SELECT * FROM users WHERE id = " . $user['id'];
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertEmpty($findings);
    }

    public function test_array_property_function_scope_isolation(): void
    {
        $content = <<<'PHP'
<?php
function first() {
    $user['id'] = $_GET['id'];
}

function second() {
    $user['id'] = 42;
    $query = "SELECT * FROM users WHERE id = " . $user['id'];
}
PHP;

        $findings = $this->scanner->scan($content, explode("\n", $content), 'app/TestController.php', 'https://example.com/repo');

        $this->assertEmpty($findings);
    }
}