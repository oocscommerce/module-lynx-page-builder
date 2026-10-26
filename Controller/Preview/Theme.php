<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Controller\Preview;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\Serializer\Json;
use OOCS\LynxPageBuilder\Model\PreviewMode;
use OOCS\LynxPageBuilder\Model\ThemeSettings;

/**
 * /lynx/preview/theme
 */
class Theme implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * Request, json, json factory, preview, theme settings
     *
     * @param Request $request
     * @param Json $json
     * @param JsonFactory $jsonFactory
     * @param PreviewMode $preview
     * @param ThemeSettings $themeSettings
     */
    public function __construct(
        private readonly Request $request,
        private readonly Json $json,
        private readonly JsonFactory $jsonFactory,
        private readonly PreviewMode $preview,
        private readonly ThemeSettings $themeSettings,
    ) {
    }

    /**
     * Execute
     *
     * @return JsonResult
     */
    public function execute(): JsonResult
    {
        $result = $this->jsonFactory->create();

        if (!$this->preview->isActive()) {
            return $result->setHttpResponseCode(403)->setData([]);
        }

        try {
            $body = $this->json->unserialize((string) $this->request->getContent());
            $theme = \is_array($body) && \is_string($body['theme'] ?? null)
                ? $this->json->unserialize($body['theme'])
                : null;
        } catch (\InvalidArgumentException) {
            $theme = null;
        }

        if (!\is_array($theme)) {
            return $result->setHttpResponseCode(400)->setData([]);
        }

        return $result->setData(['css' => $this->themeSettings->toCss($theme)]);
    }

    /**
     * Create Csrf Validation Exception
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Validate For Csrf
     *
     * @param RequestInterface $request
     * @return bool|null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
