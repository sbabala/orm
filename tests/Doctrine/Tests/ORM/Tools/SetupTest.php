<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Tools;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\Psr6\CacheAdapter;
use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\ORM\Mapping\Driver\YamlDriver;
use Doctrine\ORM\Tools\Setup;
use Doctrine\Tests\OrmTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

use function count;
use function get_include_path;
use function method_exists;
use function set_include_path;
use function spl_autoload_functions;
use function spl_autoload_unregister;
use function sys_get_temp_dir;

class SetupTest extends OrmTestCase
{
    /** @var int */
    private $originalAutoloaderCount;

    /** @var string */
    private $originalIncludePath;

    protected function setUp(): void
    {
        $this->originalAutoloaderCount = count(spl_autoload_functions());
        $this->originalIncludePath     = get_include_path();
    }

    public function tearDown(): void
    {
        if (! $this->originalIncludePath) {
            return;
        }

        set_include_path($this->originalIncludePath);
        $loaders         = spl_autoload_functions();
        $numberOfLoaders = count($loaders);
        for ($i = 0; $i < $numberOfLoaders; $i++) {
            if ($i > $this->originalAutoloaderCount + 1) {
                spl_autoload_unregister($loaders[$i]);
            }
        }
    }

    public function testDirectoryAutoload(): void
    {
        Setup::registerAutoloadDirectory(__DIR__ . '/../../../../../vendor/doctrine/common/lib');

        $this->assertEquals($this->originalAutoloaderCount + 2, count(spl_autoload_functions()));
    }

    public function testAnnotationConfiguration(): void
    {
        $config = Setup::createAnnotationMetadataConfiguration([], true);

        $this->assertInstanceOf(Configuration::class, $config);
        $this->assertEquals(sys_get_temp_dir(), $config->getProxyDir());
        $this->assertEquals('DoctrineProxies', $config->getProxyNamespace());
        $this->assertInstanceOf(AnnotationDriver::class, $config->getMetadataDriverImpl());
    }

    public function testXMLConfiguration(): void
    {
        $config = Setup::createXMLMetadataConfiguration([], true);

        $this->assertInstanceOf(Configuration::class, $config);
        $this->assertInstanceOf(XmlDriver::class, $config->getMetadataDriverImpl());
    }

    public function testYAMLConfiguration(): void
    {
        $config = Setup::createYAMLMetadataConfiguration([], true);

        $this->assertInstanceOf(Configuration::class, $config);
        $this->assertInstanceOf(YamlDriver::class, $config->getMetadataDriverImpl());
    }

    /**
     * @group 5904
     */
    public function testCacheNamespaceShouldBeGeneratedWhenCacheIsGivenButHasNoNamespace(): void
    {
        $config = Setup::createConfiguration(false, '/foo', DoctrineProvider::wrap(new ArrayAdapter()));
        $cache  = $config->getMetadataCacheImpl();

        self::assertSame('dc2_1effb2475fcfba4f9e8b8a1dbc8f3caf_', $cache->getNamespace());
    }

    /**
     * @group 5904
     */
    public function testConfiguredCacheNamespaceShouldBeUsedAsPrefixOfGeneratedNamespace(): void
    {
        $originalCache = DoctrineProvider::wrap(new ArrayAdapter());
        $originalCache->setNamespace('foo');

        $config = Setup::createConfiguration(false, '/foo', $originalCache);
        $cache  = $config->getMetadataCacheImpl();
        self::assertSame($originalCache, $cache);
        self::assertSame('foo:dc2_1effb2475fcfba4f9e8b8a1dbc8f3caf_', $cache->getNamespace());
    }

    /**
     * @group DDC-1350
     */
    public function testConfigureProxyDir(): void
    {
        $config = Setup::createAnnotationMetadataConfiguration([], true, '/foo');
        $this->assertEquals('/foo', $config->getProxyDir());
    }

    /**
     * @group DDC-1350
     */
    public function testConfigureCache(): void
    {
        $adapter = new ArrayAdapter();
        $cache   = DoctrineProvider::wrap($adapter);
        $config  = Setup::createAnnotationMetadataConfiguration([], true, null, $cache);

        $this->assertSame($cache, $config->getResultCacheImpl());
        $this->assertSame($cache, $config->getQueryCacheImpl());

        if (method_exists(Configuration::class, 'getMetadataCache')) {
            $this->assertSame($adapter, $config->getMetadataCache()->getCache()->getPool());
        } else {
            $this->assertSame($cache, $config->getMetadataCacheImpl());
        }
    }

    /**
     * @group DDC-3190
     */
    public function testConfigureCacheCustomInstance(): void
    {
        $cache  = $this->createMock(Cache::class);
        $config = Setup::createConfiguration(true, null, $cache);

        $this->assertSame($cache, $config->getResultCacheImpl());
        $this->assertSame($cache, $config->getQueryCacheImpl());

        if (method_exists(Configuration::class, 'getMetadataCache')) {
            $this->assertInstanceOf(CacheAdapter::class, $config->getMetadataCache());
            $this->assertSame($cache, $config->getMetadataCache()->getCache());
        } else {
            $this->assertSame($cache, $config->getMetadataCacheImpl());
        }
    }
}
