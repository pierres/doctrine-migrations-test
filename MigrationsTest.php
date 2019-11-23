<?php

namespace DoctrineMigrationsTest;

use Doctrine\Common\Persistence\ObjectRepository;
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

    public function testAllMigrationsUp()
    {
        $this->migrateDatabase('latest');
        $this->validateDatabase();
    }

    /**
     * @param string $version
     */
    private function migrateDatabase(string $version)
    {
        $this->runCommand(new ArrayInput([
            'command' => 'doctrine:migrations:migrate',
            'version' => $version,
            '--no-interaction' => true
        ]));
    }

    private function validateDatabase()
    {
        $this->runCommand(new ArrayInput([
            'command' => 'doctrine:schema:validate'
        ]));
    }

    public function testAllMigrationsDown()
    {
        $this->createDatabaseSchema();
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

    private function addAllMigrationVersions()
    {
        $this->runCommand(new ArrayInput([
            'command' => 'doctrine:migrations:version',
            '--add' => true,
            '--all' => true,
            '--no-interaction' => true
        ]));
    }

    /**
     * @param string $version
     * @dataProvider provideAvailableVersions
     */
    public function testMigration(string $version)
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
        static::$booted = false;
    }

    /**
     * @return array
     */
    public function provideAvailableVersions(): array
    {
        $files = glob(__DIR__ . '/../../../src/Migrations/Version*.php');
        $this->assertIsArray($files);
        asort($files);
        $versions = [];

        foreach ($files as $file) {
            $versions[] = [preg_replace('/^.*Version(\d+)\.php$/', '$1', $file)];
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
