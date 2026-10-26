<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Model\Config\Source;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\OptionSourceInterface;
use OOCS\LynxPageBuilder\Model\ResourceModel\Design\CollectionFactory;

/**
 * The designs a design may fall back to, for the Parent Designs rows of its form
 */
class ParentDesign implements OptionSourceInterface
{
    /**
     * Collection factory, request
     *
     * @param CollectionFactory $collectionFactory
     * @param RequestInterface $request
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly RequestInterface $request,
    ) {
    }

    /**
     * To Option Array
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $self = (int) $this->request->getParam('id');
        $collection = $this->collectionFactory->create();
        $collection->setOrder('name', 'ASC');

        $options = [];

        foreach ($collection as $design) {
            if ((int) $design->getId() === $self) {
                continue;
            }

            $options[] = [
                'value' => (int) $design->getId(),
                'label' => $design->getName(),
            ];
        }

        return $options;
    }
}
