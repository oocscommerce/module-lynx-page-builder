<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use OOCS\LynxPageBuilder\Api\ContentSourceInterface;
use OOCS\LynxPageBuilder\Api\DesignRepositoryInterface;

/**
 * What the editor is aimed at, read from the admin URL: which design is being built, which of its pages
 */
class BuilderTarget
{
    /**
     * @var Design|null
     */
    private ?Design $design = null;

    /**
     * @var ContentSourceInterface|null
     */
    private ?ContentSourceInterface $source = null;

    /**
     * @var bool
     */
    private bool $sourceResolved = false;

    /**
     * Request, store manager, builder pages, design repository, content sources
     *
     * @param RequestInterface $request
     * @param StoreManagerInterface $storeManager
     * @param PreviewPagebuilder $previewPagebuilder
     * @param DesignRepositoryInterface $designRepository
     * @param ContentSourceInterface[] $contentSources
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly PreviewPagebuilder $previewPagebuilder,
        private readonly DesignRepositoryInterface $designRepository,
        private readonly array $contentSources = [],
    ) {
    }

    /**
     * The single layout this editor was opened on, or null when it was opened on a design's page the ordinary way
     *
     * @return ContentSourceInterface|null
     */
    public function getContentSource(): ?ContentSourceInterface
    {
        if ($this->sourceResolved) {
            return $this->source;
        }

        $this->sourceResolved = true;

        foreach ($this->contentSources as $source) {
            if ($source->isActive()) {
                $this->source = $source;
                break;
            }
        }

        return $this->source;
    }

    /**
     * The zones this editor may change
     *
     * @return string[]
     */
    public function getEditableZones(): array
    {
        return $this->getContentSource() !== null
            ? ['content']
            : ['header', 'content', 'footer', 'backstage'];
    }

    /**
     * Whether the design's theme settings may be changed from here
     *
     * @return bool
     */
    public function canEditTheme(): bool
    {
        return $this->getContentSource() === null;
    }

    /**
     * Get Design Id
     *
     * @return int
     */
    public function getDesignId(): int
    {
        return (int) $this->getDesign()->getId();
    }

    /**
     * Get Design Name
     *
     * @return string
     */
    public function getDesignName(): string
    {
        return (string) $this->getDesign()->getName();
    }

    /**
     * Get Page Code
     *
     * @return string
     */
    public function getPageCode(): string
    {
        $code = (string) $this->request->getParam('page', '');

        return $this->previewPagebuilder->get($code) ? $code : $this->previewPagebuilder->getDefaultCode();
    }

    /**
     * Where the content zone of the page being built is stored: the full action name of that page
     *
     * @return string
     */
    public function getPagePath(): string
    {
        $source = $this->getContentSource();

        if ($source !== null) {
            return $source->getPagePath();
        }

        return (string) ($this->previewPagebuilder->get($this->getPageCode())['path'] ?? '');
    }

    /**
     * Get Scope
     *
     * @return string
     */
    public function getScope(): string
    {
        if ((int) $this->request->getParam('store', 0)) {
            return ScopeInterface::SCOPE_STORES;
        }

        return (int) $this->request->getParam('website', 0)
            ? ScopeInterface::SCOPE_WEBSITES
            : 'default';
    }

    /**
     * Get Scope Id
     *
     * @return int
     */
    public function getScopeId(): int
    {
        return (int) ($this->request->getParam('store', 0) ?: $this->request->getParam('website', 0));
    }

    /**
     * The store view the preview renders as: the one being looked at, the default one of the website being looked at
     *
     * @return StoreInterface
     */
    public function getStore(): StoreInterface
    {
        $scopeId = $this->getScopeId();

        if ($this->getScope() === ScopeInterface::SCOPE_STORES) {
            return $this->storeManager->getStore($scopeId);
        }

        if ($this->getScope() === ScopeInterface::SCOPE_WEBSITES) {
            return $this->storeManager->getWebsite($scopeId)->getDefaultStore();
        }

        return $this->storeManager->getDefaultStoreView() ?? $this->storeManager->getStore();
    }

    /**
     * Is Design Readonly
     *
     * @return bool
     */
    public function isDesignReadonly(): bool
    {
        return $this->getDesign()->isReadonly();
    }

    /**
     * The design being built, loaded once for the request
     *
     * @return Design
     */
    public function getDesign(): Design
    {
        if ($this->design !== null) {
            return $this->design;
        }

        try {
            $design = $this->designRepository->getById((int) $this->request->getParam('design', Design::DEFAULT_ID));
        } catch (NoSuchEntityException) {
            $design = $this->designRepository->getById(Design::DEFAULT_ID);
        }

        return $this->design = $design;
    }
}
