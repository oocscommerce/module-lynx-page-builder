<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Controller\Share;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use OOCS\LynxInertia\Service\InertiaService;
use OOCS\LynxPageBuilder\Helper\Data as Helper;
use OOCS\LynxPageBuilder\Model\ResourceModel\Share as ShareResource;
use OOCS\LynxPageBuilder\Model\ThemeSettings;

/**
 * Where a share link lands: the password form, as an Inertia page of this module (Pages/Share)
 */
class Open implements HttpGetActionInterface
{
    /**
     * Request, inertia service, page factory, forward factory, url, share resource, theme settings, helper
     *
     * @param RequestInterface $request
     * @param InertiaService $inertiaService
     * @param PageFactory $pageFactory
     * @param ForwardFactory $forwardFactory
     * @param UrlInterface $url
     * @param ShareResource $shareResource
     * @param ThemeSettings $themeSettings
     * @param Helper $helper
     * @param DateTime $dateTime
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly InertiaService $inertiaService,
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly UrlInterface $url,
        private readonly ShareResource $shareResource,
        private readonly ThemeSettings $themeSettings,
        private readonly Helper $helper,
        private readonly DateTime $dateTime,
    ) {
    }

    /**
     * Execute
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $key = (string) $this->request->getParam('key', '');
        // No key at all is a URL that was never a share link → 404
        if ($key === '') {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $share = $this->shareResource->findByToken($key);
        $now = $this->dateTime->gmtTimestamp();
        $alive = $share !== null && $this->dateTime->gmtTimestamp($share['expires_at']) >= $now;

        // Locked only makes sense for a live link; an unknown key just shows the form.
        $locked = $alive && $share['locked_until'] !== null
            && $this->dateTime->gmtTimestamp($share['locked_until']) > $now;

        $this->inertiaService->setPage(
            'OOCS_LynxPageBuilder::Share',
            [
                'title'     => __('Design preview'),
                'intro'     => __('Enter the password to view this preview.'),
                'label'     => __('Password'),
                'submit'    => __('Open preview'),
                'invalidMsg' => __('The password is not valid.'),
                // The last unlock attempt failed (Unlock redirects with ?err=1)
                'failed'    => $this->request->getParam('err') !== null,
                'locked'    => $locked,
                'lockedMsg' => __('Too many wrong passwords. This link is locked for a few minutes.'),
                'unlockUrl' => $this->url->getUrl('lynx/share/unlock'),
                'shareKey'  => $key,
                // The live design's theme settings, so the form is drawn in the store's own type and colours
                'theme'     => $this->themeSettings->getCss($this->helper->getActiveDesignId()),
            ],
            (string) $this->request->getRequestUri()
        );

        $page = $this->pageFactory->create();

        $page->getConfig()->setRobots('NOINDEX,NOFOLLOW');

        return $page;
    }
}
