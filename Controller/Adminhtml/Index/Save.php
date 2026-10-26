<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use OOCS\LynxPageBuilder\Api\DesignRepositoryInterface;
use OOCS\LynxPageBuilder\Model\BuilderTarget;
use OOCS\LynxPageBuilder\Model\LayoutWriter;
use OOCS\LynxPageBuilder\Model\ThemeSettings;
use Psr\Log\LoggerInterface;

/**
 * Save layout action
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'OOCS_LynxPageBuilder::save';

    /**
     * Context, json, layout writer, target, result json factory, logger, design repository, theme settings
     *
     * @param Context $context
     * @param Json $json
     * @param LayoutWriter $layoutWriter
     * @param BuilderTarget $target
     * @param JsonFactory $resultJsonFactory
     * @param LoggerInterface $logger
     * @param DesignRepositoryInterface $designRepository
     * @param ThemeSettings $themeSettings
     */
    public function __construct(
        Context $context,
        private readonly Json $json,
        private readonly LayoutWriter $layoutWriter,
        private readonly BuilderTarget $target,
        private readonly JsonFactory $resultJsonFactory,
        private readonly LoggerInterface $logger,
        private readonly DesignRepositoryInterface $designRepository,
        private readonly ThemeSettings $themeSettings,
    ) {
        parent::__construct($context);
    }

    /**
     * Save action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Json $result */
        $resultJson = $this->resultJsonFactory->create();

        // The editor already hides this by disabling its Save button (Block\Adminhtml \PageBuilder::canSave)
        if ($this->target->isDesignReadonly()) {
            return $resultJson->setHttpResponseCode(403)->setData([
                'success' => false,
                // 403 rather than a 200 saying success: false, because the editor reads the status code
                'message' => __('This design is read-only. Duplicate it to make changes.'),
            ]);
        }

        $data = $this->getRequest()->getPostValue();

        // Everything the editor is told is said in this answer
        try {
            if ($data && !empty($data['layout'])) {
                $layout = $this->json->unserialize($data['layout']);

                if ($layout['header']) {
                    $this->saveLayout('header', $layout);
                }

                if ($layout['footer']) {
                    $this->saveLayout('footer', $layout);
                }

                if (!empty($layout['backstage'])) {
                    $this->saveLayout('backstage', $layout);
                }

                if ($layout['content']) {
                    // Under the page the editor was opened on, which is the action name the storefront reads that zone
                    $this->saveLayout('content', $layout, $this->target->getPagePath());
                }
            }

            // The Theme tab: settings of the design itself, not of a page
            if ($data && !empty($data['theme'])) {
                $design = $this->target->getDesign();
                $design->setSettings($this->themeSettings->normalize($this->json->unserialize($data['theme'])));
                $this->designRepository->save($design);
            }
        } catch (LocalizedException $e) {
            // Written to be read by whoever triggered it.
            return $resultJson->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            // Anything else says nothing useful to an admin and may say too much
            $this->logger->error('Lynx Page Builder: saving the layout failed', [
                'design'    => $this->target->getDesignId(),
                'page'      => $this->target->getPageCode(),
                'exception' => $e,
            ]);

            return $resultJson->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => __('The layout could not be saved. See the log for what went wrong.'),
            ]);
        }

        return $resultJson->setData([
            'success' => true,
            'message' => __('Layout saved.'),
        ]);
    }

    /**
     * One zone onto one row: the zone is the key the editor posted it under, the path is where it is stored
     *
     * @param string $zone
     * @param array $data
     * @param string|false $customPath
     * @return void
     * @throws LocalizedException
     */
    protected function saveLayout($zone, $data, $customPath = false)
    {
        // No session message: the editor saves over fetch and shows the outcome itself (TopBar's "Saved"/"Error")
        $this->layoutWriter->save($this->target->getDesignId(), (string) ($customPath ?: $zone), $data[$zone]);
    }
}
