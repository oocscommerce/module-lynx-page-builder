<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Setup\Patch\Data;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use OOCS\LynxPageBuilder\Model\Design;
use OOCS\LynxPageBuilder\Model\ThemeSettings;

/**
 * The design an install starts with
 */
class CreateDefaultDesign implements DataPatchInterface
{
    private const TABLE = 'lynx_pagebuilder_design';

    /**
     * Module data setup, theme settings, serializer
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param ThemeSettings $themeSettings
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly ThemeSettings $themeSettings,
        private readonly SerializerInterface $serializer,
    ) {
    }

    /**
     * Apply
     *
     * @return self
     */
    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table      = $this->moduleDataSetup->getTable(self::TABLE);

        // Insert only when it is missing: an install that already has design 1 keeps the name the admin gave it
        $exists = $connection->fetchOne(
            $connection->select()->from($table, 'id')->where('id = ?', Design::DEFAULT_ID)
        );

        if (!$exists) {
            // The shipped design uses the field defaults
            $settings = $this->themeSettings->getDefaults();
            $settings['width_site'] = '1280';

            $connection->insert($table, [
                'id'       => Design::DEFAULT_ID,
                'name'     => 'Default',
                'readonly' => 1,
                'settings' => $this->serializer->serialize($settings),
            ]);
        }

        return $this;
    }

    /**
     * Get Dependencies
     *
     * @return array
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * Get Aliases
     *
     * @return array
     */
    public function getAliases(): array
    {
        return [];
    }
}
