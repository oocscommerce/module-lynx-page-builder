<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Test\Unit\Helper;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManager\TMapFactory;
use OOCS\LynxComponent\Helper\Schema\Config;
use OOCS\LynxComponent\Model\Registry;
use OOCS\LynxPageBuilder\Helper\LayoutNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Layout checks before a save: components, zones, slot groups, settings and slots, and the stored shape
 */
class LayoutNormalizerTest extends TestCase
{
    /**
     * @var LayoutNormalizer
     */
    private LayoutNormalizer $normalizer;

    /**
     * A small registry: a group with a slot taking only text, a heading, a header-only logo, a tracker
     *
     * @return void
     */
    protected function setUp(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('get')->willReturn(['components' => [
            'default_group'   => [
                'component' => 'OOCS_LynxThemeComponent::Components/Group',
                'label'     => 'Group',
                'group'     => 'layout',
                'slots'     => ['default' => ['allowed_groups' => ['text']]],
            ],
            'default_heading' => [
                'component' => 'OOCS_LynxThemeComponent::Components/Heading',
                'label'     => 'Heading',
                'group'     => 'text',
                'settings'  => ['text' => ['type' => 'text'], 'level' => ['type' => 'select']],
            ],
            'default_logo'    => [
                'component' => 'OOCS_LynxThemeComponent::Components/Logo',
                'group'     => 'media',
                'zones'     => ['header' => 'header'],
            ],
            'tracker'         => [
                'component' => 'Vendor_Module::Components/Tracker',
                'group'     => 'utility',
                'zones'     => ['backstage' => 'backstage'],
            ],
            'checkout_only'   => [
                'component'    => 'Vendor_Module::Components/Only',
                'available_on' => ['checkout_index_index'],
            ],
        ]]);
        $this->normalizer = new LayoutNormalizer(
            new Registry($config, $this->createMock(TMapFactory::class))
        );
    }

    /**
     * Normalize one content node list
     *
     * @param array $nodes
     * @param string $zone
     * @return array
     */
    private function normalize(array $nodes, string $zone = 'content'): array
    {
        return $this->normalizer->normalize($nodes, $zone, 'cms_index_index');
    }

    /**
     * Assert the nodes are refused with a message containing the text
     *
     * @param array $nodes
     * @param string $message
     * @param string $zone
     * @return void
     */
    private function assertRefused(array $nodes, string $message, string $zone = 'content'): void
    {
        try {
            $this->normalize($nodes, $zone);
            $this->fail('The layout was accepted.');
        } catch (LocalizedException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }

    /**
     * A node is stored with its component key, label, visibility, props and slots
     *
     * @return void
     */
    public function testStoredShape(): void
    {
        $out = $this->normalize([[
            'name'  => 'default_group',
            'slots' => ['default' => [['name' => 'default_heading', 'props' => ['text' => 'Hi']]]],
        ]]);

        $this->assertSame([[
            'component' => 'OOCS_LynxThemeComponent/Components/Group',
            'name'      => 'default_group',
            'label'     => 'Group',
            'visible'   => true,
            'props'     => [],
            'slots'     => ['default' => [[
                'component' => 'OOCS_LynxThemeComponent/Components/Heading',
                'name'      => 'default_heading',
                'label'     => 'Heading',
                'visible'   => true,
                'props'     => ['text' => 'Hi'],
            ]]],
        ]], $out);
    }

    /**
     * The label, visibility and custom name the node brings are kept; an empty custom name is not
     *
     * @return void
     */
    public function testKeepsLabelVisibilityAndCustomName(): void
    {
        $out = $this->normalize([
            ['name' => 'default_heading', 'label' => 'Title', 'visible' => false, 'custom_name' => 'Hero title'],
            ['name' => 'default_heading', 'custom_name' => ''],
        ]);

        $this->assertSame('Title', $out[0]['label']);
        $this->assertFalse($out[0]['visible']);
        $this->assertSame('Hero title', $out[0]['custom_name']);
        $this->assertArrayNotHasKey('custom_name', $out[1]);
    }

    /**
     * A component without a label is labelled by its name
     *
     * @return void
     */
    public function testLabelFallsBackToName(): void
    {
        $this->assertSame('default_logo', $this->normalize([['name' => 'default_logo']], 'header')[0]['label']);
    }

    /**
     * A node without a component name is refused
     *
     * @return void
     */
    public function testNamelessNode(): void
    {
        $this->assertRefused([['props' => []]], 'names no component');
    }

    /**
     * An unregistered component, or one not offered on the page, is refused
     *
     * @return void
     */
    public function testUnknownComponent(): void
    {
        $this->assertRefused([['name' => 'nope']], 'No component is registered as "nope"');
        $this->assertRefused([['name' => 'checkout_only']], 'No component is registered as "checkout_only"');
    }

    /**
     * A component limited to other zones is refused
     *
     * @return void
     */
    public function testWrongZone(): void
    {
        $this->assertRefused([['name' => 'default_logo']], '"default_logo" cannot go in the content zone');
    }

    /**
     * Backstage takes only components that name it
     *
     * @return void
     */
    public function testBackstage(): void
    {
        $this->assertCount(1, $this->normalize([['name' => 'tracker']], 'backstage'));
        $this->assertRefused([['name' => 'default_heading']], 'cannot go in the backstage zone', 'backstage');
    }

    /**
     * A slot with allowed groups refuses components of other groups
     *
     * @return void
     */
    public function testSlotGroups(): void
    {
        $this->assertRefused(
            [['name' => 'default_group', 'slots' => ['default' => [['name' => 'default_group']]]]],
            'default_group.default takes only group text; "default_group" is layout'
        );
    }

    /**
     * Props that are not settings of the component are refused
     *
     * @return void
     */
    public function testUnknownSetting(): void
    {
        $this->assertRefused(
            [['name' => 'default_heading', 'props' => ['colour' => 'red']]],
            '"default_heading" has no setting colour. Its settings: text, level.'
        );
    }

    /**
     * Slots the component does not declare are refused
     *
     * @return void
     */
    public function testUnknownSlot(): void
    {
        $this->assertRefused(
            [['name' => 'default_heading', 'slots' => ['side' => []]]],
            '"default_heading" has no slot side. Its slots: none.'
        );
    }
}
