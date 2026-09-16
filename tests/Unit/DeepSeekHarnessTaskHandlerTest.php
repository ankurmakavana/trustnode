<?php

namespace Tests\Unit;

use App\Contracts\DeepSeekHarnessAdapterInterface;
use App\Contracts\DeepSeekHarnessExecutionResultInterface;
use App\Handlers\DeepSeekHarnessTaskHandler;
use Tests\TestCase;

class DeepSeekHarnessTaskHandlerTest extends TestCase
{
    public function test_handler_supports_deepseek_harness_execution_type()
    {
        $adapter = $this->createMock(DeepSeekHarnessAdapterInterface::class);
        $handler = new DeepSeekHarnessTaskHandler($adapter);
        
        $this->assertTrue($handler->supports('deepseek_harness_execution'));
        $this->assertFalse($handler->supports('other_type'));
    }

    public function test_handler_executes_successfully()
    {
        $adapter = $this->createMock(DeepSeekHarnessAdapterInterface::class);
        $result = $this->createMock(DeepSeekHarnessExecutionResultInterface::class);
        $result->method('isSuccess')->willReturn(true);
        $result->method('getOutput')->willReturn([]);
        
        $adapter->expects($this->once())
            ->method('execute')
            ->willReturn($result);
            
        $handler = new DeepSeekHarnessTaskHandler($adapter);
        
        // Should not throw exception
        $handler->handle(['id' => '1', 'type' => 'deepseek_harness_execution']);
        $this->assertTrue(true);
    }

    public function test_handler_throws_runtime_exception_on_failure()
    {
        $adapter = $this->createMock(DeepSeekHarnessAdapterInterface::class);
        $result = $this->createMock(DeepSeekHarnessExecutionResultInterface::class);
        $result->method('isSuccess')->willReturn(false);
        $result->method('isCancelled')->willReturn(false);
        $result->method('getError')->willReturn('harness error');
        
        $adapter->expects($this->once())
            ->method('execute')
            ->willReturn($result);
            
        $handler = new DeepSeekHarnessTaskHandler($adapter);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Harness execution failed: harness error');
        
        $handler->handle(['id' => '1', 'type' => 'deepseek_harness_execution']);
    }
}
