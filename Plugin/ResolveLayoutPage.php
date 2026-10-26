<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Plugin;

use Magento\Framework\App\Request\Http;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface as EventManager;
use OOCS\LynxComponent\Helper\ThemeIdentity;
use OOCS\LynxComponent\Model\LayoutResolver;
use OOCS\LynxInertia\Service\InertiaService;
use OOCS\LynxPageBuilder\Api\LayoutRepositoryInterface;
use OOCS\LynxPageBuilder\Helper\Data as Helper;
use OOCS\LynxPageBuilder\Model\PreviewMode;

/**
 * Supplies the page for actions no controller declared one for
 */
class ResolveLayoutPage
{
    /**
     * Layout resolver, layout repository, request, event manager, helper, preview
     *
     * @param LayoutResolver $layoutResolver
     * @param LayoutRepositoryInterface $layoutRepository
     * @param Http $request
     * @param EventManager $eventManager
     * @param Helper $helper
     * @param PreviewMode $preview
     */
    public function __construct(
        private readonly LayoutResolver $layoutResolver,
        private readonly LayoutRepositoryInterface $layoutRepository,
        private readonly Http $request,
        private readonly EventManager $eventManager,
        private readonly Helper $helper,
        private readonly PreviewMode $preview,
    ) {
    }

    /**
     * Before Resolve Page
     *
     * @param InertiaService $subject
     * @param array|null $only
     * @param string|null $partialComponent
     * @return array{0: ?array, 1: ?string}
     */
    public function beforeResolvePage(
        InertiaService $subject,
        ?array $only = null,
        ?string $partialComponent = null
    ): array {
        // A controller already said what this request answers with
        if ($subject->hasPage()) {
            return [$only, $partialComponent];
        }

        // The design's CSS is in <head>; only a preview draft travels as a prop
        $subject->setPage(
            ThemeIdentity::PAGE_COMPONENT,
            ['layout' => $this->resolveZones($subject, $only)],
            $this->request->getRequestUri()
        );

        return [$only, $partialComponent];
    }

    /**
     * Resolve Zones
     *
     * @param InertiaService $subject
     * @param array|null $only
     * @return array<string, mixed>
     */
    private function resolveZones(InertiaService $subject, ?array $only): array
    {
        // Header, footer and backstage hold the same layout on every page of a store view
        $fullLayout = !$this->request->getHeader('X-Inertia') || !empty($only);

        // Which design this store runs
        $designId = $this->preview->getDesignId() ?: $this->helper->getActiveDesignId();

        $action    = $this->request->getFullActionName();
        $layout    = [];

        // Before anything is read
        $transport = new DataObject(['layout' => $layout]);
        $this->eventManager->dispatch('lynx_pagebuilder_layout_resolve_before', [
            'action'    => $action,
            'transport' => $transport,
        ]);
        $layout = $transport->getData('layout');

        if ($fullLayout) {
            // The same three on every page of a store view
            foreach (['header', 'footer', 'backstage'] as $zone) {
                if (!\array_key_exists($zone, $layout)) {
                    $layout[$zone] = $this->layoutRepository->getLayout($designId, $zone);
                }
            }
        }

        // The content of this page, under its action name, unless it was supplied above.
        if (!\array_key_exists('content', $layout)) {
            $layout['content'] = $this->layoutRepository->getLayout($designId, $action);
        }

        // After, for what has to see the layouts rather than replace them
        $transport = new DataObject(['layout' => $layout]);
        $this->eventManager->dispatch('lynx_pagebuilder_layout_resolve_after', [
            'action'    => $action,
            'transport' => $transport,
        ]);
        $layout = $transport->getData('layout');

        return $this->layoutResolver->resolve(
            $layout,
            true,
            // An optional prop is shared without being announced, so only a request naming it brings it back
            fn (string $key, callable $cb, string $group, bool $optional) => $subject->share(
                $key,
                $optional ? $subject->optional($cb) : $subject->defer($cb, $group)
            ),
            $action
        );
    }
}
