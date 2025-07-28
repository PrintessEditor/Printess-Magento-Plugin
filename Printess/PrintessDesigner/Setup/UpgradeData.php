<?php

namespace Printess\PrintessDesigner\Setup;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

/**
 * @codeCoverageIgnore
 */
class UpgradeData implements UpgradeDataInterface
{
    /**
     * Eav setup factory
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * Init
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(EavSetupFactory $eavSetupFactory)
    {
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Validate_Exception
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $eavSetup = $this->eavSetupFactory->create();

        if(version_compare($context->getVersion(), '1.0.2', '<')) {
            // Printess Output Format
            $eavSetup->addAttribute(
                Product::ENTITY,
                'printess_output_format',
                [
                    'group' => 'Printess',
                    'type' => 'varchar',
                    'backend' => '',
                    'frontend' => '',
                    'label' => 'Output Format',
                    'input' => 'text',
                    'class' => '',
                    'source' => 'Printess\PrintessDesigner\Model\Config\Source\OutputFormat',
                    'global' => ScopedAttributeInterface::SCOPE_STORE,
                    'visible' => true,
                    'required' => false,
                    'user_defined' => false,
                    'default' => '',
                    'searchable' => false,
                    'filterable' => false,
                    'comparable' => false,
                    'visible_on_front' => false,
                    'used_in_product_listing' => false,
                    'unique' => false,
                    'apply_to' => ''
                ]
            );
        }

        $eavSetup->addAttribute(
            Product::ENTITY,
            'printess_hide_prices_in_editor',
            [
                'group' => 'Printess',
                'type' => 'int',
                'backend' => '',
                'frontend' => '',
                'label' => 'Hide price in editor',
                'input' => 'boolean',
                'class' => '',
                'source' => 'Magento\Eav\Model\Entity\Attribute\Source\Boolean',
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'visible' => true,
                'required' => false,
                'user_defined' => false,
                'default' => '',
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'unique' => false,
                'apply_to' => ''
            ]
        );

        $eavSetup->addAttribute(
            Product::ENTITY,
            'printess_legal_text',
            [
                'group' => 'Printess',
                'type' => 'varchar',
                'backend' => '',
                'frontend' => '',
                'label' => 'Legal price info',
                'input' => 'text',
                'class' => '',
                'source' => '',
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'visible' => true,
                'required' => false,
                'user_defined' => true,
                'default' => '',
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => true,
                'used_in_product_listing' => true,
                'unique' => false,
                'apply_to' => ''
            ]
        );

        if(version_compare($context->getVersion(), '2.0.2', '<')) {
            $eavSetup->removeAttribute(Product::ENTITY, 'printess_ui_version');
        }

        $eavSetup->addAttribute(
            Product::ENTITY,
            'printess_ui_version',
            [
                'group' => 'Printess',
                'type' => 'varchar',
                'backend' => '',
                'frontend' => '',
                'label' => 'Ui Version',
                'input' => 'select',
                'class' => '',
                'source' => 'Printess\PrintessDesigner\Model\Config\Source\ProductUiVersion',
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'visible' => true,
                'required' => false,
                'user_defined' => false,
                'default' => '',
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'unique' => false,
                'apply_to' => ''
            ]
        );
    }
}
