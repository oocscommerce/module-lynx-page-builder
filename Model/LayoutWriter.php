<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use OOCS\LynxPageBuilder\Api\LayoutRepositoryInterface;
use OOCS\LynxPageBuilder\Model\ResourceModel\Layout as LayoutResource;

/**
 * Writes one zone layout of a design, for the editor's Save and the MCP tool alike
 */
class LayoutWriter
{
    /**
     * Json, layout factory, layout repository, layout resource
     *
     * @param Json $json
     * @param LayoutFactory $layoutFactory
     * @param LayoutRepositoryInterface $layoutRepository
     * @param LayoutResource $layoutResource
     */
    public function __construct(
        private readonly Json $json,
        private readonly LayoutFactory $layoutFactory,
        private readonly LayoutRepositoryInterface $layoutRepository,
        private readonly LayoutResource $layoutResource,
    ) {
    }

    /**
     * Store the nodes under a path, skipping a write that changes nothing
     *
     * @param int $designId
     * @param string $path
     * @param array $nodes
     * @return void
     * @throws LocalizedException
     */
    public function save(int $designId, string $path, array $nodes): void
    {
        // The editor posts every zone on every save
        if ($nodes == $this->layoutRepository->getLayout($designId, $path)) {
            return;
        }

        $model = $this->layoutFactory->create();
        $rowId = $this->layoutResource->getRowId($designId, $path);
        if ($rowId) {
            $model->setId($rowId);
        }
        $model->setData('design_id', $designId)
            ->setPath($path)
            ->setValue($this->json->serialize($nodes));

        try {
            $this->layoutRepository->save($model);
        } catch (\Exception $e) {
            throw new LocalizedException(__($e->getMessage()));
        }
    }
}
