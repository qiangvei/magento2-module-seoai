<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Block\Adminhtml\Audit;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Qiangvei\SeoAi\Helper\Config as SeoConfig;
use Qiangvei\SeoAi\Setup\Patch\Data\InstallAeoAttributes;

/**
 * Aggregates SEO/AEO health metrics for the audit report page (read-only):
 * counts of products missing meta_title / meta_description / FAQ content and
 * the presence of canonical handling.
 */
class Report extends Template
{
    private ProductCollectionFactory $productCollectionFactory;

    private SeoConfig $seoConfig;

    public function __construct(
        Context $context,
        ProductCollectionFactory $productCollectionFactory,
        SeoConfig $seoConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->productCollectionFactory = $productCollectionFactory;
        $this->seoConfig = $seoConfig;
    }

    /**
     * Total number of visible (simple+all) products counted.
     */
    public function getTotalProducts(): int
    {
        $collection = $this->productCollectionFactory->create();

        return (int) $collection->getSize();
    }

    /**
     * Count products missing a meta_title.
     */
    public function getMissingMetaTitleCount(): int
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToFilter(
            [
                ['attribute' => 'meta_title', 'null' => true],
                ['attribute' => 'meta_title', 'eq' => ''],
            ],
            '',
            'left'
        );

        return (int) $collection->getSize();
    }

    /**
     * Count products missing a meta_description.
     */
    public function getMissingMetaDescriptionCount(): int
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToFilter(
            [
                ['attribute' => 'meta_description', 'null' => true],
                ['attribute' => 'meta_description', 'eq' => ''],
            ],
            '',
            'left'
        );

        return (int) $collection->getSize();
    }

    /**
     * Count products missing FAQ content (seoai_faq attribute empty).
     */
    public function getMissingFaqCount(): int
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToFilter(
            [
                ['attribute' => InstallAeoAttributes::ATTR_FAQ, 'null' => true],
                ['attribute' => InstallAeoAttributes::ATTR_FAQ, 'eq' => ''],
            ],
            '',
            'left'
        );

        return (int) $collection->getSize();
    }

    /**
     * Whether canonical URL handling is enabled (informs a conflict note).
     */
    public function isCanonicalEnabled(): bool
    {
        return $this->seoConfig->isCanonicalEnabled();
    }
}
