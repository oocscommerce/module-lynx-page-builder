<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use OOCS\LynxComponent\Helper\ThemeIdentity;
use OOCS\LynxComponent\Model\Registry;
use OOCS\LynxPageBuilder\Api\LayoutRepositoryInterface;
use OOCS\LynxPageBuilder\Controller\Adminhtml\Media\Folder;
use OOCS\LynxPageBuilder\Controller\Adminhtml\Media\Upload;
use OOCS\LynxPageBuilder\Helper\PreviewToken;
use OOCS\LynxPageBuilder\Model\BuilderTarget;
use OOCS\LynxPageBuilder\Model\Extensions;
use OOCS\LynxPageBuilder\Model\PreviewPagebuilder;
use OOCS\LynxPageBuilder\Model\PreviewRoute;
use OOCS\LynxPageBuilder\Model\ResourceModel\Design\CollectionFactory as DesignCollectionFactory;
use OOCS\LynxPageBuilder\Model\Share;
use OOCS\LynxPageBuilder\Model\ThemeSettings;

/**
 * Everything the Vue editor is handed at boot (see pagebuilder.phtml)
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 */
class PageBuilder extends Template
{
    private const MAX_ROUTE_LENGTH = 2048;

    /**
     * Context, component registry, preview token, preview route, target, builder pages, layout repository
     *
     * @param Context $context
     * @param Registry $componentRegistry
     * @param PreviewToken $previewToken
     * @param PreviewRoute $previewRoute
     * @param BuilderTarget $target
     * @param PreviewPagebuilder $previewPagebuilder
     * @param LayoutRepositoryInterface $layoutRepository
     * @param ThemeIdentity $themeIdentity
     * @param Extensions $extensions
     * @param ThemeSettings $themeSettings
     * @param DesignCollectionFactory $designCollectionFactory
     * @param SerializerInterface $serializer
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly Registry $componentRegistry,
        private readonly PreviewToken $previewToken,
        private readonly PreviewRoute $previewRoute,
        private readonly BuilderTarget $target,
        private readonly PreviewPagebuilder $previewPagebuilder,
        private readonly LayoutRepositoryInterface $layoutRepository,
        private readonly ThemeIdentity $themeIdentity,
        private readonly Extensions $extensions,
        private readonly ThemeSettings $themeSettings,
        private readonly DesignCollectionFactory $designCollectionFactory,
        private readonly SerializerInterface $serializer,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * The scopes the editor can be opened in, in the order and the shape the native store switcher shows them
     *
     * @return string
     */
    public function getScopeOptionsJson(): string
    {
        if ($this->_storeManager->isSingleStoreMode()) {
            return '[]';
        }

        $options = [[
            'label' => (string) __('All Store Views'),
            'value' => '',
            'url'   => $this->editorUrl(),
            'depth' => 0,
        ]];

        foreach ($this->_storeManager->getWebsites() as $website) {
            $options[] = [
                'label' => $website->getName(),
                'value' => ScopeInterface::SCOPE_WEBSITE . ':' . $website->getId(),
                'url'   => $this->editorUrl(['website' => $website->getId()]),
                'depth' => 0,
            ];

            foreach ($website->getGroups() as $group) {
                $options[] = [
                    'label'    => $group->getName(),
                    'value'    => '',
                    'url'      => '',
                    'depth'    => 1,
                    'disabled' => true,
                ];

                foreach ($group->getStores() as $store) {
                    $options[] = [
                        'label' => $store->getName(),
                        'value' => ScopeInterface::SCOPE_STORE . ':' . $store->getId(),
                        'url'   => $this->editorUrl(['store' => $store->getId()]),
                        'depth' => 2,
                    ];
                }
            }
        }

        return $this->serializer->serialize($options);
    }

    /**
     * The option above that is selected, in its own `<scope>:<id>` notation
     *
     * @return string
     */
    public function getCurrentScopeValue(): string
    {
        $scopeId = $this->target->getScopeId();
        if (!$scopeId) {
            return '';
        }

        $scope = $this->target->getScope() === ScopeInterface::SCOPE_STORES
            ? ScopeInterface::SCOPE_STORE
            : ScopeInterface::SCOPE_WEBSITE;

        return $scope . ':' . $scopeId;
    }

    /**
     * The other designs, for "Copy page from design" in the tools menu
     *
     * @return string
     */
    public function getDesignOptionsJson(): string
    {
        // Copying a page between designs is a design's business; a layout is one layout wherever it is looked at
        if ($this->target->getContentSource() !== null) {
            return '[]';
        }

        $options = [];

        foreach ($this->designCollectionFactory->create()->setOrder('name', 'ASC') as $design) {
            if ((int) $design->getId() === $this->target->getDesignId()) {
                continue;
            }

            $options[] = [
                'label' => $design->getName(),
                'url'   => $this->getUrl('lynxpagebuilder/index/layout', [
                    'design' => (int) $design->getId(),
                    'page'   => $this->target->getPageCode(),
                ]),
            ];
        }

        return $this->serializer->serialize($options);
    }

    /**
     * The page switcher's list, as a tree; each URL keeps the scope being edited
     *
     * @return string
     */
    public function getPageOptionsJson(): string
    {
        // One page is being edited and there is nowhere to switch to
        if ($this->target->getContentSource() !== null) {
            return '[]';
        }

        $options = [];
        $groups  = [];

        foreach ($this->previewPagebuilder->getAll() as $code => $page) {
            $option = [
                'code'  => $code,
                // A sample-data page shows made-up data, not the store's own
                'label' => empty($page['sample_data'])
                    ? (string) __($page['label'])
                    : (string) __('%1 (Sample)', __($page['label'])),
                'url'   => $this->editorUrl(['page' => $code]),
            ];

            $group = (string) ($page['group'] ?? '');
            if ($group === '') {
                $options[] = $option;
                continue;
            }

            if (!isset($groups[$group])) {
                // Reserved by reference: the row keeps the position of the first page that named the group
                $options[] = ['label' => (string) __($group), 'children' => []];
                $groups[$group] = array_key_last($options);
            }

            $options[$groups[$group]]['children'][] = $option;
        }

        return $this->serializer->serialize(array_values($options));
    }

    /**
     * Get Current Page Code
     *
     * @return string
     */
    public function getCurrentPageCode(): string
    {
        return $this->target->getPageCode();
    }

    /**
     * Whether the design being built may be written to
     *
     * @return bool
     */
    public function canSave(): bool
    {
        // A read-only design is the shipped one, and nothing about it stops a layout being edited
        return $this->target->getContentSource() !== null || !$this->target->isDesignReadonly();
    }

    /**
     * The Theme tab: the field definitions (Model\ThemeSettings) and the values the design being built holds
     *
     * @return string
     */
    public function getThemeSettingsJson(): string
    {
        return $this->serializer->serialize([
            'fields' => $this->themeSettings->getFields(),
            'values' => $this->themeSettings->normalize($this->target->getDesign()->getSettings()),
        ]);
    }

    /**
     * Carries the page, so a save lands in the layout the editor was opened on
     *
     * @return string
     */
    public function getSaveUrl(): string
    {
        $source = $this->target->getContentSource();

        // A layout is saved by its own action: one zone onto one row, with none of what saving a design involves
        if ($source !== null) {
            return $this->getUrl('lynxpagebuilder/content/save', $source->getUrlParams());
        }

        return $this->getUrl('lynxpagebuilder/index/save', [
            'design' => $this->target->getDesignId(),
            'page'   => $this->target->getPageCode(),
        ]);
    }

    /**
     * The three zones the editor starts with
     *
     * @return string
     */
    public function getInitialLayoutJson(): string
    {
        // Header, footer and backstage are the design's on either way
        return $this->serializer->serialize([
            'header'  => $this->getStoredZone('header'),
            'content' => $this->target->getContentSource()?->getNodes()
                ?? $this->getStoredZone($this->target->getPagePath()),
            'footer'  => $this->getStoredZone('footer'),
            'backstage' => $this->getStoredZone('backstage'),
        ]);
    }

    /**
     * The zones this editor may change: what the client lists in the outline
     *
     * @return string
     */
    public function getEditableZonesJson(): string
    {
        return $this->serializer->serialize($this->target->getEditableZones());
    }

    /**
     * Whether the Theme tab is offered
     *
     * @return bool
     */
    public function canEditTheme(): bool
    {
        return $this->target->canEditTheme();
    }

    /**
     * The design being built, shown where the editor's title goes so the screen says which one is on it
     *
     * @return string
     */
    public function getPageTitle(): string
    {
        return $this->target->getContentSource()?->getLabel() ?? $this->target->getDesignName();
    }

    /**
     * The components the editor offers, which is the ones the page being built accepts
     *
     * @return string
     * @see \OOCS\LynxComponent\Model\Registry::getComponents()
     */
    public function getAvailableComponentsJson(): string
    {
        return $this->serializer->serialize(
            $this->componentRegistry->getComponents($this->target->getPagePath())
        );
    }

    /**
     * Every registered component name, for the outline to mark a node whose module is gone
     *
     * @return string
     */
    public function getRegisteredComponentsJson(): string
    {
        return $this->serializer->serialize($this->componentRegistry->getNames());
    }

    /**
     * The share dialog (TopBar's "Share preview"): where to list, make and revoke links
     *
     * @return string
     */
    public function getShareJson(): string
    {
        $params = ['design' => $this->target->getDesignId()];

        return $this->serializer->serialize([
            'allowed'   => $this->_authorization->isAllowed('OOCS_LynxPageBuilder::share'),
            'listUrl'   => $this->getUrl('lynxpagebuilder/share/index', $params),
            'createUrl' => $this->getUrl('lynxpagebuilder/share/create', $params),
            'revokeUrl' => $this->getUrl('lynxpagebuilder/share/revoke', $params),
            'hours'     => Share::HOURS,
        ]);
    }

    /**
     * Where Exit leaves to: the editor hides the admin menu, so it hands the way out itself
     *
     * @return string
     */
    public function getExitUrl(): string
    {
        // Back where the editor was opened from, which for a layout is its own form.
        return $this->target->getContentSource()?->getExitUrl()
            ?? $this->getUrl('lynxpagebuilder/design/index');
    }

    /**
     * Get Media Contents Url
     *
     * @return string
     */
    public function getMediaContentsUrl(): string
    {
        return $this->getUrl('lynxpagebuilder/media/contents');
    }

    /**
     * Where the editor uploads images; empty when the admin lacks the permission, and the editor hides the controls
     *
     * @return string
     */
    public function getMediaUploadUrl(): string
    {
        return $this->_authorization->isAllowed(Upload::ADMIN_RESOURCE)
            ? $this->getUrl('lynxpagebuilder/media/upload')
            : '';
    }

    /**
     * Where the editor creates media folders; empty when the admin lacks the permission
     *
     * @return string
     */
    public function getMediaFolderUrl(): string
    {
        return $this->_authorization->isAllowed(Folder::ADMIN_RESOURCE)
            ? $this->getUrl('lynxpagebuilder/media/folder')
            : '';
    }

    /**
     * Base of the preview URLs: the route being previewed goes on the end
     *
     * @return string
     * @see \OOCS\LynxPageBuilder\Controller\Router
     */
    public function getPageBuilderPreviewUrl(): string
    {
        return rtrim($this->target->getStore()->getBaseUrl(), '/') . '/lynx_pagebuilder/';
    }

    /**
     * The route the iframe opens on: the page the switcher is on
     *
     * @return string
     */
    public function getPreviewRoute(): string
    {
        $route = ltrim(trim((string) $this->getRequest()->getParam('preview_route', '')), '/');

        $valid = $route !== '' && !str_contains($route, '://') && !str_contains($route, '\\');

        if ($valid && \strlen($route) <= self::MAX_ROUTE_LENGTH) {
            return $route;
        }

        return $this->previewRoute->get();
    }

    /**
     * Every page the editor can be pointed at, flat, with the path its layout is stored under
     *
     * @return string
     */
    public function getFramePagesJson(): string
    {
        $pages = [];

        foreach ($this->previewPagebuilder->getAll() as $code => $page) {
            $pages[] = [
                'code'  => (string) $code,
                'path'  => (string) ($page['path'] ?? ''),
                'label' => (string) __($page['label']),
            ];
        }

        return $this->serializer->serialize($pages);
    }

    /**
     * The editor's URL for the design and scope being edited, naming no page and no layout
     *
     * @return string
     */
    public function getFrameEditorUrl(): string
    {
        $params = ['design' => $this->target->getDesignId()];

        $scopeId = $this->target->getScopeId();
        if ($scopeId) {
            $params[$this->target->getScope() === ScopeInterface::SCOPE_STORES ? 'store' : 'website'] = $scopeId;
        }

        return $this->getUrl('lynxpagebuilder/index/index', $params);
    }

    /**
     * What the editor is on, in the terms a frame reports its page by: a layout's own URL params, or the page code
     *
     * @return string
     */
    public function getCurrentTargetJson(): string
    {
        $source = $this->target->getContentSource();

        return $this->serializer->serialize(
            $source !== null ? ['params' => $source->getUrlParams()] : ['page' => $this->target->getPageCode()]
        );
    }

    /**
     * Said in the editor when the store view the preview renders as does not run a Lynx theme
     *
     * @return string
     */
    public function getThemeNotice(): string
    {
        $store   = $this->target->getStore();
        $storeId = (int) $store->getId();

        if ($this->themeIdentity->isLynxForStore($storeId)) {
            return '';
        }

        $themeCode = $this->themeIdentity->getCodeForStore($storeId);

        // No code at all when the store view points at a theme that no longer loads
        return (string) ($themeCode
            ? __(
                'Store view "%1" runs the %2 theme, not Lynx: the preview shows it as it is, without this layout.',
                $store->getName(),
                $themeCode
            )
            : __(
                'Store view "%1" does not run a Lynx theme: the preview shows it as it is, without this layout.',
                $store->getName()
            ));
    }

    /**
     * Goes on every preview URL as ___store: a cookie may be withheld from a frame of another origin
     *
     * @return string
     */
    public function getPreviewStoreCode(): string
    {
        return (string) $this->target->getStore()->getCode();
    }

    /**
     * Signs the preview URL, and carries the design being built inside it
     *
     * @return string
     */
    public function getPageBuilderPreviewToken(): string
    {
        return $this->previewToken->generate($this->target->getDesignId());
    }

    /**
     * Get Media Base Url
     *
     * @return string
     */
    public function getMediaBaseUrl(): string
    {
        return rtrim(
            $this->_storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
            '/'
        );
    }

    /**
     * The extensions the editor loads after it mounts: for each
     *
     * @return string
     */
    public function getExtensionsJson(): string
    {
        $extensions = [];
        foreach ($this->extensions->getActive() as $extension) {
            $extensions[] = [
                'script' => $extension->getScriptUrl(),
                'config' => $extension->getConfig(),
            ];
        }

        return $this->serializer->serialize($extensions);
    }

    /**
     * The one copy of Vue on the page
     *
     * @return string
     */
    public function getVueUrl(): string
    {
        return $this->getViewFileUrl('OOCS_LynxPageBuilder::js/pagebuilder/lynx-vue.min.js');
    }

    /**
     * The module extensions import the editor's stores from
     *
     * @return string
     */
    public function getApiUrl(): string
    {
        return $this->getViewFileUrl('OOCS_LynxPageBuilder::js/pagebuilder/lynx-pagebuilder-api.min.js');
    }

    /**
     * Named after the design being built and the page inside it, so a folder of exports says what each file is
     *
     * @return string
     */
    public function getExportFilename(): string
    {
        if ($this->target->getContentSource() !== null) {
            return 'lynx_' . $this->target->getPagePath() . '.json';
        }

        return 'lynx_' . $this->target->getDesignId() . '_' . $this->target->getPageCode() . '.json';
    }

    /**
     * A URL back to the editor, carrying what the given parameters do not say: the scope and the page it is on now
     *
     * @param array $params
     * @return string
     */
    private function editorUrl(array $params = []): string
    {
        $current = [
            'design' => $this->target->getDesignId(),
            'page'   => $this->target->getPageCode(),
        ];

        $current += $this->target->getContentSource()?->getUrlParams() ?? [];

        $scopeId = $this->target->getScopeId();
        if ($scopeId) {
            $key = $this->target->getScope() === ScopeInterface::SCOPE_STORES ? 'store' : 'website';
            $current[$key] = $scopeId;
        }

        // A scope in $params replaces the current one, and the other key has to go with it
        if (isset($params['store']) || isset($params['website'])) {
            unset($current['store'], $current['website']);
        }

        return $this->getUrl('lynxpagebuilder/index/index', $params + $current);
    }

    /**
     * Get Stored Zone
     *
     * @param string $path
     * @return array
     */
    private function getStoredZone(string $path): array
    {
        // Already the decoded nodes, and an empty list when the design holds no document.
        return $this->layoutRepository->getLayout($this->target->getDesignId(), $path);
    }
}
