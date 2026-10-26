<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Controller\Adminhtml\Media;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Cms\Helper\Wysiwyg\Images as WysiwygImages;
use Magento\Cms\Model\Wysiwyg\Images\Storage;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Lists a media folder for the editor's image picker.
 */
class Contents extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_Cms::media_gallery';

    /**
     * Context, json factory, file driver, wysiwyg images, storage, store manager
     *
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param FileDriver $fileDriver
     * @param WysiwygImages $wysiwygImages
     * @param Storage $storage
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly FileDriver $fileDriver,
        private readonly WysiwygImages $wysiwygImages,
        private readonly Storage $storage,
        private readonly StoreManagerInterface $storeManager,
    ) {
        parent::__construct($context);
    }

    /**
     * Execute
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->jsonFactory->create();

        try {
            $root = $this->fileDriver->getRealPath($this->wysiwygImages->getStorageRoot());
            if (!$root) {
                return $result->setData(['success' => false, 'error' => 'Storage root not found']);
            }

            $rel  = trim(
                str_replace(['..', '\\'], '', (string) $this->getRequest()->getParam('path', '')),
                '/'
            );
            $path = $this->fileDriver->getRealPath($rel ? $root . DIRECTORY_SEPARATOR . $rel : $root);

            if (!$path || !str_starts_with($path, $root)) {
                return $result->setData(['success' => false, 'error' => 'Invalid path']);
            }

            $mediaUrl = rtrim(
                $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
                '/'
            );
            $relDir  = ltrim(substr($path, \strlen($root)), DIRECTORY_SEPARATOR);
            $urlBase = $mediaUrl . '/' . ($relDir ? $relDir . '/' : '');

            $parent = null;
            if ($relDir !== '') {
                $up     = $this->fileDriver->getParentDirectory($relDir);
                $parent = $up === '.' ? '' : $up;
            }

            return $result->setData([
                'success'   => true,
                'pathId'    => $relDir,
                'pathLabel' => $relDir ?: '/',
                'parent'    => $parent,
                'dirs'      => $this->dirs($path, $relDir),
                'files'     => $this->files($path, $relDir, $urlBase),
            ]);
        } catch (\Exception $e) {
            return $result->setData(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * The subfolders of a folder, as name and id
     *
     * @param string $path
     * @param string $relDir
     * @return array<int,array<string,string>>
     */
    private function dirs(string $path, string $relDir): array
    {
        $dirs = [];

        foreach ($this->storage->getDirsCollection($path) as $item) {
            $basename = $item->getBasename();
            $dirs[]   = ['name' => $basename, 'id' => $relDir ? $relDir . '/' . $basename : $basename];
        }

        return $dirs;
    }

    /**
     * The images of a folder, with their URL and media path
     *
     * @param string $path
     * @param string $relDir
     * @param string $urlBase
     * @return array<int,array<string,string>>
     */
    private function files(string $path, string $relDir, string $urlBase): array
    {
        $files = [];

        foreach ($this->storage->getFilesCollection($path, 'image') as $item) {
            $basename = $item->getBasename();
            $url      = $urlBase . $basename;
            $filePath = ($relDir ? $relDir . '/' : '') . $basename;
            $files[]  = ['name' => $basename, 'url' => $url, 'thumb' => $url, 'path' => $filePath];
        }

        return $files;
    }
}
