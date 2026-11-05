<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Setup\Patch\Data;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

/**
 * Installs the AEO content attributes used by the module:
 *
 *  - seoai_faq    : JSON array [{"q":"...","a":"..."}] for FAQPage schema + visible FAQ block.
 *  - seoai_howto  : JSON array [{"name":"...","text":"...","url":"..."}] for HowTo schema.
 *
 * Stored as catalog product / category EAV text attributes (no extra tables), per design.
 */
class InstallAeoAttributes implements DataPatchInterface, PatchRevertableInterface
{
    public const ATTR_FAQ = 'seoai_faq';
    public const ATTR_HOWTO = 'seoai_howto';

    private ModuleDataSetupInterface $moduleDataSetup;
    private EavSetupFactory $eavSetupFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $this->installAttribute($eavSetup, Product::ENTITY, self::ATTR_FAQ, 'SEO AI: FAQ (JSON)');
        $this->installAttribute($eavSetup, Product::ENTITY, self::ATTR_HOWTO, 'SEO AI: HowTo (JSON)');
        $this->installAttribute($eavSetup, Category::ENTITY, self::ATTR_FAQ, 'SEO AI: FAQ (JSON)');

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function revert(): self
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        foreach ([Product::ENTITY, Category::ENTITY] as $entityType) {
            foreach ([self::ATTR_FAQ, self::ATTR_HOWTO] as $code) {
                $eavSetup->removeAttribute($entityType, $code);
            }
        }

        return $this;
    }

    private function installAttribute(
        EavSetup $eavSetup,
        string $entityType,
        string $code,
        string $label
    ): void {
        $eavSetup->addAttribute($entityType, $code, [
            'group' => 'SEO & AI',
            'type' => 'text',
            'backend' => '',
            'frontend' => '',
            'label' => $label,
            'input' => 'textarea',
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
            'visible_on_front' => false,
            'used_in_product_listing' => false,
            'unique' => false,
            'note' => 'JSON. FAQ example: [{"q":"Question?","a":"Answer."}] HowTo example: [{"name":"Step","text":"...","url":""}]',
        ]);
    }
}