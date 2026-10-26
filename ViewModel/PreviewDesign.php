<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\ViewModel;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use OOCS\LynxPageBuilder\Api\DesignRepositoryInterface;
use OOCS\LynxPageBuilder\Model\PreviewMode;

/**
 * The name of the design being previewed, for the pill to say which one it is
 */
class PreviewDesign implements ArgumentInterface
{
    /**
     * Preview, design repository
     *
     * @param PreviewMode $preview
     * @param DesignRepositoryInterface $designRepository
     */
    public function __construct(
        private readonly PreviewMode $preview,
        private readonly DesignRepositoryInterface $designRepository,
    ) {
    }

    /**
     * Get Name
     *
     * @return string
     */
    public function getName(): string
    {
        $designId = $this->preview->getDesignId();

        if (!$designId) {
            return '';
        }

        try {
            return (string) $this->designRepository->getById($designId)->getName();
        } catch (NoSuchEntityException) {
            return '';
        }
    }
}
