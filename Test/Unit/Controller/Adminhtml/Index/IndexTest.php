<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Test\Unit\Controller\Adminhtml\Index;

use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Asset\AssetInterface;
use Magento\Framework\View\Asset\GroupedCollection;
use Magento\Framework\View\Page\Config;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use OOCS\LynxPageBuilder\Controller\Adminhtml\Index\Index;
use OOCS\LynxPageBuilder\Model\Extensions;
use PHPUnit\Framework\TestCase;

/**
 * The editor page keeps none of the admin head scripts and styles
 */
class IndexTest extends TestCase
{
    /**
     * Scripts and styles go once the layout is built; the favicon stays
     *
     * @return void
     */
    public function testDropsHeadScriptsAndStylesAfterTheLayoutIsBuilt(): void
    {
        $assets = $this->createMock(GroupedCollection::class);
        $assets->method('getAll')->willReturn([
            'requirejs/require.js' => $this->asset('js'),
            'Vendor_Module::js/help.js' => $this->asset('js'),
            'css/styles.css' => $this->asset('css'),
            'Magento_Theme::favicon.ico' => $this->asset('ico'),
        ]);
        $removed = [];
        $assets->method('remove')->willReturnCallback(function (string $identifier) use (&$removed): void {
            $removed[] = $identifier;
        });

        $steps = [];
        $config = $this->createMock(Config::class);
        $config->method('getTitle')->willReturn($this->createMock(Title::class));
        $config->method('publicBuild')->willReturnCallback(function () use (&$steps): void {
            $steps[] = 'build';
        });
        $config->method('getAssetCollection')->willReturnCallback(function () use (&$steps, $assets) {
            $steps[] = 'assets';
            return $assets;
        });

        $page = $this->createMock(Page::class);
        $page->method('getConfig')->willReturn($config);
        $pageFactory = $this->createMock(PageFactory::class);
        $pageFactory->method('create')->willReturn($page);

        $controller = new Index(
            $this->createMock(Context::class),
            $pageFactory,
            $this->createMock(Extensions::class)
        );

        $this->assertSame($page, $controller->execute());
        $this->assertSame(['build', 'assets'], $steps);
        $this->assertSame(['requirejs/require.js', 'Vendor_Module::js/help.js', 'css/styles.css'], $removed);
    }

    /**
     * An asset of this content type
     *
     * @param string $type
     * @return AssetInterface
     */
    private function asset(string $type): AssetInterface
    {
        $asset = $this->createMock(AssetInterface::class);
        $asset->method('getContentType')->willReturn($type);
        return $asset;
    }
}
