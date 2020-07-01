<?php

namespace DoctrineMigrationsTest;

use Doctrine\Persistence\ObjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class MigrationsTest extends WebTestCase
{
    /** @var KernelBrowser */
    protected static $client;

    /**
     * @param string $className
     * @return ObjectRepository
     */
    protected static function getRepository(string $className): ObjectRepository
    {
        return static::getEntityManager()->getRepository($className);
    }

    /**
     * @return EntityManagerInterface
     */
    protected static function getEntityManager(): EntityManagerInterface
    {
        $container = static::getClient()->getContainer();
        static::assertNotNull($container);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        static::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        return $entityManager;
    }

    /**
     * @return KernelBrowser
     */
    protected static function getClient(): KernelBrowser
    {
        return static::$client;
    }

    public function setUp(): void
    {
        static::$client = static::createClient();
        if ($this->isPersistentDatabase()) {
            $this->dropDatabase();
            $this->createDatabase();
        }
    }

    /**
     * @return bool
     */
    protected static function isPersistentDatabase(): bool
    {
        $params = static::getEntityManager()->getConnection()->getParams();
        return !empty($params['path']) || !empty($params['dbname']);
    }

    protected static function dropDatabase(): void
    {
        static::runCommand(new ArrayInput([
            'command' => 'doctrine:database:drop',
            '--force' => true,
            '--if-exists' => true,
            '--quiet' => true
        ]));
    }

    /**
     * @param ArrayInput $input
     */
    protected static function runCommand(ArrayInput $input): void
    {
        $application = new Application(static::getClient()->getKernel());
        $application->setAutoExit(false);

        $output = new BufferedOutput();
        $result = $application->run($input, $output);

        $outputResult = $output->fetch();
        static::assertEmpty($outputResult, $outputResult);
        static::assertEquals(0, $result, sprintf('Command %s failed', $input));
    }

    protected static function createDatabase(): void
    {
        static::runCommand(new ArrayInput([
            'command' => 'doctrine:database:create'
        ]));
    }

    public function testAllMigrationsUp(): void
    {
        $this->migrateDatabase('latest');
        $this->validateDatabase();
    }

    /**
     * @param string $version
     */
    private function migrateDatabase(string $version): void
    {
        $i = new ArrayInput([
            'command' => 'doctrine:migrations:migrate',
            '--allow-no-migration' => true,
            '--no-interaction' => true,
            'version' => $version
        ]);
        $this->runCommand($i);
    }

    private function validateDatabase(): void
    {
        $this->runCommand(new ArrayInput([
            'command' => 'doctrine:schema:validate'
        ]));
    }

    public function testAllMigrationsDown(): void
    {
        $this->createDatabaseSchema();
        $this->syncMetadataStorage();
        $this->validateDatabase();
        $this->addAllMigrationVersions();
        $this->migrateDatabase('first');
        $this->assertEquals(
            ['migration_versions'],
            $this->getEntityManager()->getConnection()->getSchemaManager()->listTableNames()
        );
    }

    protected static function createDatabaseSchema(): void
    {
        static::runCommand(new ArrayInput([
            'command' => 'doctrine:schema:create',
            '--quiet' => true
        ]));
    }

    private function addAllMigrationVersions(): void
    {
        $this->runCommand(new ArrayInput([
            'command' => 'doctrine:migrations:version',
            '--add' => true,
            '--all' => true,
            '--no-interaction' => true
        ]));
    }

    private function syncMetadataStorage(): void
    {
        $this->runCommand(new ArrayInput([
            'command' => 'doctrine:migrations:sync-metadata-storage',
            '--no-interaction' => true
        ]));
    }

    /**
     * @param string $version
     * @dataProvider provideAvailableVersions
     */
    public function testMigration(string $version): void
    {
        $this->migrateDatabase($version);
        static::$client = static::recreateClient();
        $this->migrateDatabase('prev');
        static::$client = static::recreateClient();
        $this->migrateDatabase('next');
    }

    /**
     * @return KernelBrowser
     */
    private static function recreateClient(): KernelBrowser
    {
        static::shutdownKernel();
        return static::createClient();
    }

    private static function shutdownKernel(): void
    {
        static::$client->getKernel()->shutdown();
        if (isset(static::$booted)) {
            static::$booted = false;
        }
    }

    /**
     * @return array<array>
     */
    public function provideAvailableVersions(): array
    {
        $files = glob(__DIR__ . '/../../../src/Migrations/*.php');
        $this->assertIsArray($files);
        asort($files);
        $versions = [];

        foreach ($files as $file) {
            $versions[] = [preg_replace('#^/.+/([^/]+)\.php$#', 'DoctrineMigrations\\\$1', $file)];
        }

        return $versions;
    }

    protected function tearDown(): void
    {
        if (static::isPersistentDatabase()) {
            static::dropDatabase();
        }
        static::shutdownKernel();
        parent::tearDown();
    }
}
