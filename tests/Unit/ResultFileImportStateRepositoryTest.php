<?php

namespace Tests\Unit;

use App\Services\ResultFileImportStateRepository;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ResultFileImportStateRepositoryTest extends TestCase
{
    public function testBooleanPlaceholderUsesIntegerPlaceholder(): void {
        $method = new ReflectionMethod(ResultFileImportStateRepository::class, 'placeholder');

        $this->assertSame('%i', $method->invoke(new ResultFileImportStateRepository(), true));
        $this->assertSame('%i', $method->invoke(new ResultFileImportStateRepository(), false));
    }
}
