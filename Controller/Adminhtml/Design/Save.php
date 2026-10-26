<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Controller\Adminhtml\Design;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use OOCS\LynxPageBuilder\Api\DesignRepositoryInterface;
use OOCS\LynxPageBuilder\Model\DesignFactory;
use OOCS\LynxPageBuilder\Model\ThemeSettings;

/**
 * Saves a design from its form, parents included.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'OOCS_LynxPageBuilder::manage';

    /**
     * Context, design factory, design repository, theme settings
     *
     * @param Context $context
     * @param DesignFactory $designFactory
     * @param DesignRepositoryInterface $designRepository
     * @param ThemeSettings $themeSettings
     */
    public function __construct(
        Context $context,
        private readonly DesignFactory $designFactory,
        private readonly DesignRepositoryInterface $designRepository,
        private readonly ThemeSettings $themeSettings,
    ) {
        parent::__construct($context);
    }

    /**
     * Execute
     *
     * @return Redirect
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create();
        $data     = (array) $this->getRequest()->getPostValue();

        if (!$data) {
            return $redirect->setPath('*/*/index');
        }

        $id   = (int) ($data['id'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));

        try {
            if ($name === '') {
                throw new LocalizedException(__('A design needs a name.'));
            }

            try {
                $design = $id ? $this->designRepository->getById($id) : $this->designFactory->create();
            } catch (NoSuchEntityException) {
                throw new LocalizedException(__('This design no longer exists.'));
            }

            $design->setData('name', $name);
            $design->setData('description', trim((string) ($data['description'] ?? '')));
            $design->setParents($this->parentsFrom($data, $id));
            // The theme settings are edited in the page builder, not here
            if (!$design->getId()) {
                $design->setSettings($this->themeSettings->getDefaults());
            }
            $this->designRepository->save($design);

            $this->messageManager->addSuccessMessage(__('You saved the design.'));

            if ($this->getRequest()->getParam('back')) {
                return $redirect->setPath('*/*/edit', ['id' => $design->getId()]);
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $redirect->setPath('*/*/edit', $id ? ['id' => $id] : []);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Could not save the design.'));

            return $redirect->setPath('*/*/edit', $id ? ['id' => $id] : []);
        }

        return $redirect->setPath('*/*/index');
    }

    /**
     * The parent ids the form posted, in the order its rows were dragged into
     *
     * @param array $data
     * @param int $self
     * @return int[]
     */
    private function parentsFrom(array $data, int $self): array
    {
        $rows = \is_array($data['parents'] ?? null) ? $data['parents'] : [];

        usort($rows, static fn ($a, $b): int => (int) ($a['position'] ?? 0) <=> (int) ($b['position'] ?? 0));

        $parents = [];
        foreach ($rows as $row) {
            $id = (int) ($row['design'] ?? 0);
            if ($id > 0 && $id !== $self && !\in_array($id, $parents, true)) {
                $parents[] = $id;
            }
        }

        return $parents;
    }
}
