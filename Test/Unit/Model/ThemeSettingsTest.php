<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Test\Unit\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use OOCS\LynxComponent\Model\Cache\Type\ComponentCache;
use OOCS\LynxPageBuilder\Api\DesignRepositoryInterface;
use OOCS\LynxPageBuilder\Model\Design;
use OOCS\LynxPageBuilder\Model\ThemeSettings;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Design settings: values over defaults within their limits, the CSS they turn into, and its cache
 */
class ThemeSettingsTest extends TestCase
{
    private const FIELDS = [
        'primary' => ['type' => 'color', 'default' => '#2954ff', 'var' => '--color-primary'],
        'radius'  => ['type' => 'number', 'default' => 8, 'min' => 0, 'max' => 32, 'unit' => 'px', 'var' => '--radius'],
        'font'    => [
            'type'    => 'select',
            'default' => 'system',
            'options' => [['value' => 'system'], ['value' => 'serif']],
            'values'  => ['system' => 'ui-sans-serif', 'serif' => 'Georgia, serif'],
            'var'     => '--font',
        ],
        'size'    => ['type' => 'number', 'default' => 16, 'unit' => 'px', 'html' => 'font-size'],
        'css'     => ['type' => 'textarea', 'default' => '', 'raw' => true],
    ];

    /**
     * @var DesignRepositoryInterface&MockObject
     */
    private DesignRepositoryInterface $repository;

    /**
     * @var ComponentCache&MockObject
     */
    private ComponentCache $cache;

    /**
     * @var ThemeSettings
     */
    private ThemeSettings $settings;

    /**
     * Settings over the fields above
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->repository = $this->createMock(DesignRepositoryInterface::class);
        $this->cache = $this->createMock(ComponentCache::class);
        $this->settings = new ThemeSettings($this->repository, $this->cache, self::FIELDS);
    }

    /**
     * Nothing saved gives every default
     *
     * @return void
     */
    public function testDefaults(): void
    {
        $expected = ['primary' => '#2954ff', 'radius' => 8, 'font' => 'system', 'size' => 16, 'css' => ''];

        $this->assertSame($expected, $this->settings->normalize([]));
        $this->assertSame($expected, $this->settings->getDefaults());
    }

    /**
     * Numbers are cast and kept within min and max
     *
     * @return void
     */
    public function testNumbersClamped(): void
    {
        $this->assertSame(32, $this->settings->normalize(['radius' => '99'])['radius']);
        $this->assertSame(0, $this->settings->normalize(['radius' => -4])['radius']);
        $this->assertSame(12, $this->settings->normalize(['radius' => '12'])['radius']);
    }

    /**
     * A select value outside its options falls back to the default
     *
     * @return void
     */
    public function testSelectOutsideOptions(): void
    {
        $this->assertSame('serif', $this->settings->normalize(['font' => 'serif'])['font']);
        $this->assertSame('system', $this->settings->normalize(['font' => 'comic'])['font']);
    }

    /**
     * Text is trimmed, an empty one takes the default; a raw field stays empty
     *
     * @return void
     */
    public function testTextTrimmedAndEmptyTakesDefault(): void
    {
        $values = $this->settings->normalize(['primary' => '  ', 'css' => '  ']);

        $this->assertSame('#2954ff', $values['primary']);
        $this->assertSame('', $values['css']);
        $this->assertSame('#f00', $this->settings->normalize(['primary' => ' #f00 '])['primary']);
    }

    /**
     * Variables on :root with units and mapped values, html properties, then the raw CSS escaped
     *
     * @return void
     */
    public function testCss(): void
    {
        $css = $this->settings->toCss(['font' => 'serif', 'css' => '.a{color:red}</style>']);

        $this->assertSame(
            ':root{--color-primary:#2954ff;--radius:8px;--font:Georgia, serif}html{font-size:16px}'
            . "\n.a{color:red}<\/style>",
            $css
        );
    }

    /**
     * No raw CSS and no html field leaves only :root
     *
     * @return void
     */
    public function testCssWithoutHtmlOrRaw(): void
    {
        $settings = new ThemeSettings($this->repository, $this->cache, ['primary' => self::FIELDS['primary']]);

        $this->assertSame(':root{--color-primary:#2954ff}', $settings->toCss([]));
    }

    /**
     * A cached stylesheet is used as is
     *
     * @return void
     */
    public function testCssFromCache(): void
    {
        $this->cache->method('load')->with('lynx_theme_3')->willReturn(':root{}');
        $this->repository->expects($this->never())->method('getById');

        $this->assertSame(':root{}', $this->settings->getCss(3));
    }

    /**
     * A cache miss builds the stylesheet from the design once, and caches it with the design tag
     *
     * @return void
     */
    public function testCssBuiltAndCached(): void
    {
        $design = $this->createMock(Design::class);
        $design->method('getSettings')->willReturn(['radius' => 4]);
        $this->cache->method('load')->willReturn(false);
        $this->repository->expects($this->once())->method('getById')->with(3)->willReturn($design);
        $this->cache->expects($this->once())->method('save')
            ->with($this->stringContains('--radius:4px'), 'lynx_theme_3', [Design::CACHE_TAG]);

        $this->settings->getCss(3);
        $this->assertStringContainsString('--radius:4px', $this->settings->getCss(3));
    }

    /**
     * A missing design gets the default stylesheet
     *
     * @return void
     */
    public function testCssOfMissingDesign(): void
    {
        $this->cache->method('load')->willReturn(false);
        $this->repository->method('getById')->willThrowException(new NoSuchEntityException());

        $this->assertStringContainsString('--radius:8px', $this->settings->getCss(99));
    }
}
