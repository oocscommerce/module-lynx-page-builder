<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Controller\Adminhtml\Design;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Forward;

/**
 * Creating and renaming are the same screen, so this hands over to it rather than being a screen of its own
 */
class NewAction extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'OOCS_LynxPageBuilder::manage';

    /**
     * Execute
     *
     * @return Forward
     */
    public function execute(): Forward
    {
        return $this->resultFactory->create('forward')->forward('edit');
    }
}
