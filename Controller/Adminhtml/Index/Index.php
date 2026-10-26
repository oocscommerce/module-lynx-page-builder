<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Csp\Api\CspAwareActionInterface;
use Magento\Csp\Model\Policy\FetchPolicy;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use OOCS\LynxPageBuilder\Model\Extensions;

/**
 * The PageBuilder editor page, with the CSP the editor needs.
 *
 * The editor loads its own scripts and styles (pagebuilder.phtml); the admin head ones are dropped, since without
 * RequireJS on this page any of them calling require() would only throw.
 */
class Index extends Action implements CspAwareActionInterface, HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'OOCS_LynxPageBuilder::manage';

    // Head assets of these types are dropped; the favicon and the rest stay
    private const DROPPED_TYPES = ['js', 'css'];

    /**
     * Context, page factory, extensions
     *
     * @param Context $context
     * @param PageFactory $pageFactory
     * @param Extensions $extensions
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly Extensions $extensions,
    ) {
        parent::__construct($context);
    }

    /**
     * Execute
     *
     * @return Page
     */
    public function execute(): Page
    {
        $page = $this->pageFactory->create();
        $config = $page->getConfig();
        $config->getTitle()->prepend(__('Lynx Page Builder'));

        // The head assets come from the layout, so it is built first; it is built once, rendering keeps the result
        $config->publicBuild();
        $assets = $config->getAssetCollection();
        foreach ($assets->getAll() as $identifier => $asset) {
            if (\in_array($asset->getContentType(), self::DROPPED_TYPES, true)) {
                $assets->remove($identifier);
            }
        }

        return $page;
    }

    /**
     * Lets the editor page call what its extensions call
     *
     * @param array $appliedPolicies
     * @return array
     */
    public function modifyCsp(array $appliedPolicies): array
    {
        if (empty($appliedPolicies['connect-src'])) {
            return $appliedPolicies;
        }

        foreach ($this->extensions->getActive() as $extension) {
            foreach ($extension->getConnectSources() as $host) {
                $appliedPolicies[] = new FetchPolicy('connect-src', false, [$host], ['https']);
            }
        }

        return $appliedPolicies;
    }
}
