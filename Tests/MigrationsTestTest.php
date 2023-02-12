<?php

namespace DoctrineMigrationsTest\Tests;

use DoctrineMigrationsTest\MigrationsTest;
use PHPUnit\Framework\TestCase;

class MigrationsTestTest extends TestCase
{
    public function testInstantiation(): void
    {
        $this->assertInstanceOf(MigrationsTest::class, new MigrationsTest(MigrationsTest::class));
    }
}
