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
use OOCS\LynxPageBuilder\Model\PreviewDraft;
use OOCS\LynxPageBuilder\Model\ThemeSettings;

/**
 * Builds the page for a preview request out of the draft the editor posted, instead of the layout the store has saved
 */
class PreviewLayoutPage
{
    /**
     * Layout resolver, draft, request, event manager, theme settings
     *
     * @param LayoutResolver $layoutResolver
     * @param PreviewDraft $draft
     * @param Http $request
     * @param EventManager $eventManager
     * @param ThemeSettings $themeSettings
     */
    public function __construct(
        private readonly LayoutResolver $layoutResolver,
        private readonly PreviewDraft $draft,
        private readonly Http $request,
        private readonly EventManager $eventManager,
        private readonly ThemeSettings $themeSettings,
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
        if (!$this->draft->isPreview() || $subject->hasPage()) {
            return [$only, $partialComponent];
        }

        $zones = [
            'header'  => $this->draft->getZone('header'),
            'content' => $this->draft->getZone('content'),
            'footer'  => $this->draft->getZone('footer'),
            'backstage' => $this->draft->getZone('backstage'),
        ];

        // The same two events ResolveLayoutPage dispatches, so a page that renders without a header and a footer
        $action = $this->request->getFullActionName();

        foreach (['lynx_pagebuilder_layout_resolve_before', 'lynx_pagebuilder_layout_resolve_after'] as $event) {
            $transport = new DataObject(['layout' => $zones]);
            $this->eventManager->dispatch($event, ['action' => $action, 'transport' => $transport]);
            $zones = $transport->getData('layout');
        }

        $props = [
            'layout' => $this->layoutResolver->resolve(
                $zones,
                false,
                fn (string $key, callable $cb, string $group, bool $optional) => $subject->share(
                    $key,
                    $optional ? $subject->optional($cb) : $subject->defer($cb, $group)
                )
            ),
            // Tells DynamicNode to mark every node with data-lynx-id and data-lynx-label
            'preview' => true,
        ];

        // Unsaved theme, rendered in the body over the saved one in <head>
        if ($this->draft->getTheme() !== null) {
            $props['theme'] = $this->themeSettings->toCss($this->draft->getTheme());
        }

        $subject->setPage(ThemeIdentity::PAGE_COMPONENT, $props, $this->request->getRequestUri());

        return [$only, $partialComponent];
    }
}
