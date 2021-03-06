<?php

/*
 * This file is part of Contao.
 *
 * Copyright (c) 2005-2017 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao\CoreBundle\Tests\Contao;

use Contao\Combiner;
use Contao\Config;
use Contao\CoreBundle\Tests\TestCase;
use Contao\System;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Tests the Combiner class.
 *
 * @author Martin Auswöger <martin@auswoeger.com>
 * @author Leo Feyer <https://github.com/leofeyer>
 *
 * @group contao3
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CombinerTest extends TestCase
{
    /**
     * @var string
     */
    private static $rootDir;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass()
    {
        self::$rootDir = __DIR__.'/../Fixtures/tmp';

        $fs = new Filesystem();
        $fs->mkdir(self::$rootDir);
        $fs->mkdir(self::$rootDir.'/assets');
        $fs->mkdir(self::$rootDir.'/assets/css');
        $fs->mkdir(self::$rootDir.'/system');
        $fs->mkdir(self::$rootDir.'/system/tmp');
        $fs->mkdir(self::$rootDir.'/web');
    }

    /**
     * {@inheritdoc}
     */
    public static function tearDownAfterClass()
    {
        $fs = new Filesystem();
        $fs->remove(self::$rootDir);
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        define('TL_ERROR', 'ERROR');
        define('TL_ROOT', self::$rootDir);
        define('TL_ASSETS_URL', '');

        $this->container = $this->mockContainerWithContaoScopes();
        $this->container->setParameter('contao.web_dir', self::$rootDir.'/web');

        System::setContainer($this->container);
    }

    /**
     * Tests the object instantiation.
     */
    public function testCanBeInstantiated()
    {
        $this->assertInstanceOf('Contao\Combiner', new Combiner());
    }

    /**
     * Tests the CSS combiner.
     */
    public function testCombinesCssFiles()
    {
        file_put_contents(static::$rootDir.'/file1.css', 'file1 { background: url("foo.bar") }');
        file_put_contents(static::$rootDir.'/web/file2.css', 'web/file2');
        file_put_contents(static::$rootDir.'/file3.css', 'file3');
        file_put_contents(static::$rootDir.'/web/file3.css', 'web/file3');

        $combiner = new Combiner();
        $combiner->add('file1.css');
        $combiner->addMultiple(['file2.css', 'file3.css']);

        $this->assertSame(
            [
                'file1.css',
                'file2.css|screen',
                'file3.css|screen',
            ],
            $combiner->getFileUrls()
        );

        $combinedFile = $combiner->getCombinedFile();

        $this->assertRegExp('/^assets\/css\/[a-z0-9]+\.css$/', $combinedFile);

        $this->assertSame(
            "file1 { background: url(\"../../foo.bar\") }\n@media screen{\nweb/file2\n}\n@media screen{\nfile3\n}\n",
            file_get_contents(static::$rootDir.'/'.$combinedFile)
        );

        Config::set('debugMode', true);

        $markup = $combiner->getCombinedFile();

        $this->assertSame(
            'file1.css"><link rel="stylesheet" href="file2.css" media="screen"><link rel="stylesheet" href="file3.css" media="screen',
            $markup
        );
    }

    /**
     * Tests fixing the paths in the CSS combiner.
     */
    public function testFixesTheFilePaths()
    {
        $class = new \ReflectionClass(Combiner::class);
        $method = $class->getMethod('fixPaths');
        $method->setAccessible(true);

        $css = <<<'EOF'
test1 { background: url(foo.bar) }
test2 { background: url("foo.bar") }
test3 { background: url('foo.bar') }
EOF;

        $expected = <<<'EOF'
test1 { background: url(../../foo.bar) }
test2 { background: url("../../foo.bar") }
test3 { background: url('../../foo.bar') }
EOF;

        $this->assertSame(
            $expected,
            $method->invokeArgs($class->newInstance(), [$css, ['name' => 'file.css']])
        );
    }

    /**
     * Tests if the CSS combiner handles special characters while fixing paths.
     */
    public function testHandlesSpecialCharactersWhileFixingTheFilePaths()
    {
        $class = new \ReflectionClass(Combiner::class);
        $method = $class->getMethod('fixPaths');
        $method->setAccessible(true);

        $css = <<<'EOF'
test1 { background: url(foo.bar) }
test2 { background: url("foo.bar") }
test3 { background: url('foo.bar') }
EOF;

        $expected = <<<'EOF'
test1 { background: url("../../\"test\"/foo.bar") }
test2 { background: url("../../\"test\"/foo.bar") }
test3 { background: url('../../"test"/foo.bar') }
EOF;

        $this->assertSame(
            $expected,
            $method->invokeArgs($class->newInstance(), [$css, ['name' => 'web/"test"/file.css']])
        );

        $expected = <<<'EOF'
test1 { background: url("../../'test'/foo.bar") }
test2 { background: url("../../'test'/foo.bar") }
test3 { background: url('../../\'test\'/foo.bar') }
EOF;

        $this->assertSame(
            $expected,
            $method->invokeArgs($class->newInstance(), [$css, ['name' => "web/'test'/file.css"]])
        );

        $expected = <<<'EOF'
test1 { background: url("../../(test)/foo.bar") }
test2 { background: url("../../(test)/foo.bar") }
test3 { background: url('../../(test)/foo.bar') }
EOF;

        $this->assertSame(
            $expected,
            $method->invokeArgs($class->newInstance(), [$css, ['name' => "web/(test)/file.css"]])
        );
    }

    /**
     * Checks that the CSS combiner ignores data URLs while fixing paths.
     */
    public function testIgnoresDataUrlsWhileFixingTheFilePaths()
    {
        $class = new \ReflectionClass(Combiner::class);
        $method = $class->getMethod('fixPaths');
        $method->setAccessible(true);

        $css = <<<'EOF'
test1 { background: url('data:image/svg+xml;utf8,<svg id="foo"></svg>') }
test2 { background: url("data:image/svg+xml;utf8,<svg id='foo'></svg>") }
EOF;

        $this->assertSame(
            $css,
            $method->invokeArgs($class->newInstance(), [$css, ['name' => 'file.css']])
        );
    }

    /**
     * Tests the SCSS Combiner.
     */
    public function testCombinesScssFiles()
    {
        file_put_contents(static::$rootDir.'/file1.scss', '$color: red; @import "file1_sub";');
        file_put_contents(static::$rootDir.'/file1_sub.scss', 'body { color: $color }');
        file_put_contents(static::$rootDir.'/file2.scss', 'body { color: green }');

        $combiner = new Combiner();
        $combiner->add('file1.scss');
        $combiner->add('file2.scss');

        $this->assertSame(
            [
                'assets/css/file1.scss.css',
                'assets/css/file2.scss.css',
            ],
            $combiner->getFileUrls()
        );

        $combinedFile = $combiner->getCombinedFile();

        $this->assertRegExp('/^assets\/css\/[a-z0-9]+\.css$/', $combinedFile);

        $this->assertSame(
            "body{color:red}\nbody{color:green}\n",
            file_get_contents(static::$rootDir.'/'.$combinedFile)
        );

        Config::set('debugMode', true);

        $markup = $combiner->getCombinedFile();

        $this->assertSame(
            'assets/css/file1.scss.css"><link rel="stylesheet" href="assets/css/file2.scss.css',
            $markup
        );
    }

    /**
     * Tests the JS Combiner.
     */
    public function testCombinesJsFiles()
    {
        file_put_contents(static::$rootDir.'/file1.js', 'file1();');
        file_put_contents(static::$rootDir.'/web/file2.js', 'file2();');

        $combiner = new Combiner();
        $combiner->add('file1.js');
        $combiner->add('file2.js');

        $this->assertSame(
            [
                'file1.js',
                'file2.js',
            ],
            $combiner->getFileUrls()
        );

        $combinedFile = $combiner->getCombinedFile();

        $this->assertRegExp('/^assets\/js\/[a-z0-9]+\.js$/', $combinedFile);

        $this->assertSame(
            "file1();\nfile2();\n",
            file_get_contents(static::$rootDir.'/'.$combinedFile)
        );

        Config::set('debugMode', true);

        $markup = $combiner->getCombinedFile();

        $this->assertSame(
            'file1.js"></script><script src="file2.js',
            $markup
        );
    }
}
