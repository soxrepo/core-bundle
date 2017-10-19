<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * Copyright (c) 2005-2017 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao\CoreBundle\Tests\Translation;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Contao\CoreBundle\Translation\Translator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatorInterface;

class TranslatorTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $originalTranslator = $this->createMock(TranslatorInterface::class);
        $framework = $this->createMock(ContaoFrameworkInterface::class);
        $translator = new Translator($originalTranslator, $framework);

        $this->assertInstanceOf('Contao\CoreBundle\Translation\Translator', $translator);
        $this->assertInstanceOf('Symfony\Component\Translation\TranslatorInterface', $translator);
    }

    public function testForwardsTheMethodCallsToTheDecoratedTranslator(): void
    {
        $originalTranslator = $this->createMock(TranslatorInterface::class);

        $originalTranslator
            ->expects($this->once())
            ->method('trans')
            ->with('id', ['param' => 'value'], 'domain', 'en')
            ->willReturn('trans')
        ;

        $originalTranslator
            ->expects($this->once())
            ->method('transChoice')
            ->with('id', 3, ['param' => 'value'], 'domain', 'en')
            ->willReturn('transChoice')
        ;

        $originalTranslator
            ->expects($this->once())
            ->method('setLocale')
            ->with('en')
        ;

        $originalTranslator
            ->expects($this->once())
            ->method('getLocale')
            ->willReturn('en')
        ;

        $framework = $this->createMock(ContaoFrameworkInterface::class);

        $framework
            ->expects($this->never())
            ->method('initialize')
        ;

        $translator = new Translator($originalTranslator, $framework);

        $this->assertSame('trans', $translator->trans('id', ['param' => 'value'], 'domain', 'en'));
        $this->assertSame('transChoice', $translator->transChoice('id', 3, ['param' => 'value'], 'domain', 'en'));

        $translator->setLocale('en');

        $this->assertSame('en', $translator->getLocale());
    }

    public function testReadsFromTheGlobalLanguageArray(): void
    {
        $systemAdapter = $this->createMock(Adapter::class);

        $systemAdapter
            ->expects($this->atLeastOnce())
            ->method('__call')
            ->with('loadLanguageFile', ['default'])
        ;

        $framework = $this->createMock(ContaoFrameworkInterface::class);

        $framework
            ->expects($this->atLeastOnce())
            ->method('initialize')
        ;

        $framework
            ->expects($this->atLeastOnce())
            ->method('getAdapter')
            ->willReturn($systemAdapter)
        ;

        $translator = new Translator($this->createMock(TranslatorInterface::class), $framework);

        $this->assertSame('MSC.foo', $translator->trans('MSC.foo', [], 'contao_default'));

        $GLOBALS['TL_LANG']['MSC']['foo'] = 'bar';

        $this->assertSame('bar', $translator->trans('MSC.foo', [], 'contao_default'));
        $this->assertSame('MSC.foo.bar', $translator->trans('MSC.foo.bar', [], 'contao_default'));

        $GLOBALS['TL_LANG']['MSC']['foo'] = 'bar %s baz %s';

        $this->assertSame('bar foo1 baz foo2', $translator->trans('MSC.foo', ['foo1', 'foo2'], 'contao_default'));

        $GLOBALS['TL_LANG']['MSC']['foo.bar\\baz'] = 'foo';

        $this->assertSame('foo', $translator->trans('MSC.foo\.bar\\\\baz', [], 'contao_default'));
        $this->assertSame('foo', $translator->trans('MSC.foo\.bar\baz', [], 'contao_default'));

        $GLOBALS['TL_LANG']['MSC']['foo\\']['bar\\baz.'] = 'foo';

        $this->assertSame('foo', $translator->trans('MSC.foo\\\\.bar\baz\.', [], 'contao_default'));
        $this->assertSame('MSC.foo\.bar\baz\.', $translator->trans('MSC.foo\.bar\baz\.', [], 'contao_default'));

        unset(
            $GLOBALS['TL_LANG']['MSC']['foo'],
            $GLOBALS['TL_LANG']['MSC']['foo.bar\\baz'],
            $GLOBALS['TL_LANG']['MSC']['foo\\']['bar\\baz.']
        );
    }
}