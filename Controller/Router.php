<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Controller;

use Magento\Customer\Model\Visitor;
use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\Action\Redirect;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Store\Model\StoreManagerInterface;
use OOCS\LynxComponent\Helper\PreviewSample;
use OOCS\LynxPageBuilder\Controller\Preview\Sample;
use OOCS\LynxPageBuilder\Helper\PreviewToken;
use OOCS\LynxPageBuilder\Model\PreviewDraft;
use OOCS\LynxPageBuilder\Model\PreviewMode;
use OOCS\LynxPageBuilder\Model\PreviewPagebuilder;

/**
 * Serves the PageBuilder preview: /lynx_pagebuilder/<route> renders <route> with the layout the editor posted instead
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Router implements RouterInterface
{
    private const PREFIX = 'lynx_pagebuilder/';

    /** Starts preview mode and redirects to the real route: the editor's "Open in new tab" */
    public const PARAM_OPEN = '__lynx_open';

    /** Renders the route with the saved layouts, a CMS page's or category's own even when not switched on */
    public const PARAM_PREVIEW = '__lynx_preview';

    /**
     * Action factory, draft, preview, token, visitor, response, store manager, preview sample flag, editor pages
     *
     * @param ActionFactory $actionFactory
     * @param PreviewDraft $draft
     * @param PreviewMode $preview
     * @param PreviewToken $token
     * @param Visitor $visitor
     * @param ResponseInterface $response
     * @param StoreManagerInterface $storeManager
     * @param PreviewSample $previewSample
     * @param PreviewPagebuilder $previewPagebuilder
     */
    public function __construct(
        private readonly ActionFactory $actionFactory,
        private readonly PreviewDraft $draft,
        private readonly PreviewMode $preview,
        private readonly PreviewToken $token,
        private readonly Visitor $visitor,
        private readonly ResponseInterface $response,
        private readonly StoreManagerInterface $storeManager,
        private readonly PreviewSample $previewSample,
        private readonly PreviewPagebuilder $previewPagebuilder,
    ) {
    }

    /**
     * Match
     *
     * @param RequestInterface $request
     * @return ActionInterface|null
     */
    public function match(RequestInterface $request): ?ActionInterface
    {
        $path = ltrim((string) $request->getPathInfo(), '/');

        if (!str_starts_with($path, self::PREFIX)) {
            return null;
        }

        // A URL without a valid signature is a URL this router does not claim
        $token = (string) $request->getParam('token', '');

        if ($this->token->read($token) === null) {
            return null;
        }

        if ($request->getParam(self::PARAM_OPEN)) {
            $this->preview->start($token);

            return $this->redirectToRoute($request, substr($path, \strlen(self::PREFIX)));
        }

        if ($request->getParam(self::PARAM_PREVIEW)) {
            $this->draft->markSaved();
        } else {
            $this->draft->capture($request);
        }

        // The editor posts the draft on every keystroke, and Visitor writes customer_visitor on every POST
        $this->visitor->setSkipRequestLogging(true);

        // Which design the browser is previewing, for the storefront pages the frame reaches by following a link
        $this->preview->start($token);

        $route = substr($path, \strlen(self::PREFIX));
        $request->setPathInfo('/' . $route);

        $parts = array_pad(explode('/', trim($route, '/')), 3, 'index');

        // The page's own controller would turn the editor away (no order just placed): render its layout with samples
        if ($this->isSampleData(implode('/', \array_slice($parts, 0, 3)))) {
            return $this->sampleAction($request, $parts);
        }

        // Forward rather than the action for the new path: it puts the request back through the router list
        return $this->actionFactory->create(Forward::class);
    }

    /**
     * Whether the editor page this route previews is built from sample data instead of its own controller
     *
     * @param string $route
     * @return bool
     */
    private function isSampleData(string $route): bool
    {
        foreach ($this->previewPagebuilder->getAll() as $page) {
            if (($page['route'] ?? '') === $route && !empty($page['sample_data'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * The sample action, dispatched under the previewed page's name so its layout handle is the one loaded
     *
     * @param RequestInterface $request
     * @param string[] $parts
     * @return ActionInterface
     */
    private function sampleAction(RequestInterface $request, array $parts): ActionInterface
    {
        [$front, $controller, $action] = $parts;

        $request->setModuleName($front)
            ->setRouteName($front)
            ->setControllerName($controller)
            ->setActionName($action);
        $request->setDispatched(true);

        $this->previewSample->start();

        return $this->actionFactory->create(Sample::class);
    }

    /**
     * The route on this store, with the query it came with less what the preview route adds
     *
     * @param RequestInterface $request
     * @param string $route
     * @return ActionInterface
     */
    private function redirectToRoute(RequestInterface $request, string $route): ActionInterface
    {
        $query = (array) $request->getQuery()->toArray();
        unset($query['token'], $query[self::PARAM_OPEN]);

        $url = $this->storeManager->getStore()->getBaseUrl() . ltrim($route, '/')
            . ($query ? '?' . http_build_query($query) : '');

        $this->response->setRedirect($url);
        $request->setDispatched(true);

        return $this->actionFactory->create(Redirect::class);
    }
}
