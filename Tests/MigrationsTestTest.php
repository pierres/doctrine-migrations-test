<?php

namespace DoctrineMigrationsTest\Tests;

use DoctrineMigrationsTest\MigrationsTest;
use PHPUnit\Framework\TestCase;

class MigrationsTestTest extends TestCase
{
    public function testInstanciation()
    {
        $this->assertInstanceOf(MigrationsTest::class, new MigrationsTest());
    }
}
