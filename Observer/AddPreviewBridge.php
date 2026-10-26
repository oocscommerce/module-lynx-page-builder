<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\UrlInterface;
use OOCS\LynxPageBuilder\Api\LayoutRepositoryInterface;
use OOCS\LynxPageBuilder\Helper\Data as Helper;
use OOCS\LynxPageBuilder\Model\PreviewMode;

/**
 * Puts the preview bridge
 */
class AddPreviewBridge implements ObserverInterface
{
    private const BRIDGE_COMPONENT = 'OOCS_LynxPageBuilder/Components/PreviewBridge';

    /**
     * Preview, helper, layout repository, event manager, url builder
     *
     * @param PreviewMode $preview
     * @param Helper $helper
     * @param LayoutRepositoryInterface $layoutRepository
     * @param EventManager $eventManager
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly PreviewMode $preview,
        private readonly Helper $helper,
        private readonly LayoutRepositoryInterface $layoutRepository,
        private readonly EventManager $eventManager,
        private readonly UrlInterface $urlBuilder,
    ) {
    }

    /**
     * Execute
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        if (!$this->preview->isActive()) {
            return;
        }

        $transport = $observer->getEvent()->getData('transport');
        $layout = $transport->getData('layout');

        $path = $this->layoutRepository->getContentPath();
        $target = new DataObject(['target' => $path !== null ? ['path' => $path] : []]);
        $this->eventManager->dispatch('lynx_pagebuilder_preview_target', [
            'action' => $observer->getEvent()->getData('action'),
            'transport' => $target,
        ]);

        $layout['content'] = array_merge(
            [[
                'component' => self::BRIDGE_COMPONENT,
                'props' => [
                    'admin_origin' => $this->helper->getAdminOrigin(),
                    'target' => (array) $target->getData('target'),
                    'theme_url' => $this->urlBuilder->getUrl('lynx/preview/theme'),
                ],
            ]],
            $layout['content'] ?? []
        );

        $transport->setData('layout', $layout);
    }
}
