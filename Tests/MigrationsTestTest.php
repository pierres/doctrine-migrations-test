<?php

namespace DoctrineMigrationsTest\Tests;

use DoctrineMigrationsTest\MigrationsTest;
use PHPUnit\Framework\TestCase;

class MigrationsTestTest extends TestCase
{
    public function testInstantiation(): void
    {
        $migrationsTest = new MigrationsTest(MigrationsTest::class);

        $this->assertObjectHasProperty('client', $migrationsTest);
    }
}
