<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Ui\Component\Listing\MassAction;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Ui\Component\Action;

/**
 * The Delete mass action of the layout grid, with the design carried on its URL
 */
class DeleteLayouts extends Action
{
    /**
     * Context, request, components, data
     *
     * @param ContextInterface $context
     * @param RequestInterface $request
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        private readonly RequestInterface $request,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $components, $data);
    }

    /**
     * Not prepare(): MassAction::prepare() collects each action's configuration before any child is prepared
     *
     * @return array
     */
    public function getConfiguration()
    {
        $config = parent::getConfiguration();
        $config['url'] = $this->getContext()->getUrl(
            'lynxpagebuilder/layout/massDelete',
            ['design_id' => (int) $this->request->getParam('design_id')]
        );

        return $config;
    }
}
