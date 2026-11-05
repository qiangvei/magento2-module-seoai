<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Model\Meta;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Qiangvei\SeoAi\Helper\Config as SeoConfig;
use Qiangvei\SeoAi\Model\Meta\TemplateEngine;
use Qiangvei\SeoAi\Model\Meta\VariableResolver;

/**
 * Batch-generates product meta_title / meta_description from the configured
 * SEO templates and persists them store-scoped via saveAttribute() to avoid
 * a full product re-save.
 */
class BatchGenerator
{
    public const ATTR_META_TITLE = 'meta_title';
    public const ATTR_META_DESCRIPTION = 'meta_description';

    private SeoConfig $config;
    private TemplateEngine $templateEngine;
    private VariableResolver $variableResolver;
    private ProductCollectionFactory $productCollectionFactory;
    private ProductResource $productResource;
    private StoreManagerInterface $storeManager;

    public function __construct(
        SeoConfig $config,
        TemplateEngine $templateEngine,
        VariableResolver $variableResolver,
        ProductCollectionFactory $productCollectionFactory,
        ProductResource $productResource,
        StoreManagerInterface $storeManager
    ) {
        $this->config = $config;
        $this->templateEngine = $templateEngine;
        $this->variableResolver = $variableResolver;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productResource = $productResource;
        $this->storeManager = $storeManager;
    }

    /**
     * Generate and persist meta fields for the given products in a store scope.
     *
     * @param int[]  $productIds
     * @param int    $storeId    Target store view id (0 = default/admin scope).
     * @param bool   $overwrite  When false, skip products that already have a value.
     * @return int  Number of products that had at least one attribute written.
     */
    public function generateForProducts(array $productIds, int $storeId, bool $overwrite): int
    {
        $productIds = array_values(array_filter(array_map('intval', $productIds)));
        if ($productIds === []) {
            return 0;
        }

        $titleTemplate = $this->config->getProductMetaTitleTemplate((string) $storeId);
        $descTemplate = $this->config->getProductMetaDescTemplate((string) $storeId);

        if ($titleTemplate === '' && $descTemplate === '') {
            return 0;
        }

        $collection = $this->productCollectionFactory->create();
        $collection->addIdFilter($productIds);
        $collection->addAttributeToSelect(['name', 'sku', 'price', 'short_description', 'description', 'meta_title', 'meta_description']);

        $store = $this->resolveStore($storeId);

        $processed = 0;
        foreach ($collection as $product) {
            $variables = $this->variableResolver->fromProduct($product, $store);

            $wrote = false;

            if ($titleTemplate !== '') {
                $existing = (string) $product->getData(self::ATTR_META_TITLE);
                if ($overwrite || trim($existing) === '') {
                    $value = $this->templateEngine->renderTitle($titleTemplate, $variables, (string) $storeId);
                    $this->saveAttribute($product, self::ATTR_META_TITLE, $value, $storeId);
                    $wrote = true;
                }
            }

            if ($descTemplate !== '') {
                $existing = (string) $product->getData(self::ATTR_META_DESCRIPTION);
                if ($overwrite || trim($existing) === '') {
                    $value = $this->templateEngine->renderDescription($descTemplate, $variables);
                    $this->saveAttribute($product, self::ATTR_META_DESCRIPTION, $value, $storeId);
                    $wrote = true;
                }
            }

            if ($wrote) {
                $processed++;
            }
        }

        return $processed;
    }

    private function saveAttribute(ProductInterface $product, string $code, string $value, int $storeId): void
    {
        $product->setData($code, $value);

        // Write the store-scoped attribute value directly, avoiding a full save().
        if ($storeId > 0) {
            $product->setStoreId($storeId);
        }

        $this->productResource->saveAttribute($product, $code);
    }

    private function resolveStore(int $storeId): ?StoreInterface
    {
        if ($storeId <= 0) {
            return null;
        }

        try {
            return $this->storeManager->getStore($storeId);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
