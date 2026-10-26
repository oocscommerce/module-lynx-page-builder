<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Model;

use Magento\Cms\Helper\Wysiwyg\Images as WysiwygImages;
use Magento\Cms\Model\Wysiwyg\Images\Storage;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\MediaStorage\Model\File\UploaderFactory;

/**
 * The media folder the editor picks images from: storing a file and making a folder, for the controllers and MCP alike
 */
class MediaStorage
{
    public const EXTENSIONS = ['jpg', 'jpeg', 'png'];

    /**
     * File driver, wysiwyg images, storage, uploader factory
     *
     * @param FileDriver $fileDriver
     * @param WysiwygImages $wysiwygImages
     * @param Storage $storage
     * @param UploaderFactory $uploaderFactory
     */
    public function __construct(
        private readonly FileDriver $fileDriver,
        private readonly WysiwygImages $wysiwygImages,
        private readonly Storage $storage,
        private readonly UploaderFactory $uploaderFactory,
    ) {
    }

    /**
     * The absolute path of an existing folder under the media root; empty names the root itself
     *
     * @param string $relative
     * @return string
     * @throws LocalizedException
     */
    public function directory(string $relative): string
    {
        $root = $this->root();
        $rel  = trim(str_replace(['..', '\\'], '', $relative), '/');
        $path = $this->fileDriver->getRealPath($rel ? $root . DIRECTORY_SEPARATOR . $rel : $root);
        if (!$path || !str_starts_with($path, $root)) {
            throw new LocalizedException(__('Invalid path'));
        }

        return $path;
    }

    /**
     * Make a folder; the name rules, forbidden folders and duplicates are the core's
     *
     * @param string $name
     * @param string $relative the parent
     * @return string The new folder, relative to the media root
     * @throws LocalizedException
     */
    public function createDirectory(string $name, string $relative): string
    {
        $path = $this->directory($relative);
        $this->storage->createDirectory($name, $path);

        return $this->prefix($path) . $name;
    }

    /**
     * Store an image into a folder, renaming it when the name is taken
     *
     * @param string|array $fileId a key of $_FILES, or the file's own array (name, tmp_name, ...)
     * @param string $relative
     * @return string The file, relative to the media root, as image settings take it
     * @throws LocalizedException
     */
    public function save(string|array $fileId, string $relative): string
    {
        $path = $this->directory($relative);
        $uploader = $this->uploaderFactory->create(['fileId' => $fileId]);
        $uploader->setAllowedExtensions(self::EXTENSIONS);
        $uploader->setAllowRenameFiles(true);
        $uploader->setFilesDispersion(false);
        $uploaded = $uploader->save($path);

        return $this->prefix($path) . $uploaded['file'];
    }

    /**
     * Root
     *
     * @return string
     * @throws LocalizedException
     */
    private function root(): string
    {
        $root = $this->fileDriver->getRealPath($this->wysiwygImages->getStorageRoot());
        if (!$root) {
            throw new LocalizedException(__('Storage root not found'));
        }

        return $root;
    }

    /**
     * What goes before a name inside the folder: its path relative to the root with a trailing slash, or nothing
     *
     * @param string $path
     * @return string
     */
    private function prefix(string $path): string
    {
        $relDir = ltrim(substr($path, \strlen($this->root())), DIRECTORY_SEPARATOR);

        return $relDir ? $relDir . '/' : '';
    }
}
