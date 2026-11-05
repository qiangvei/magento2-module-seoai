<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Model\Meta;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Category;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\StoreManagerInterface;
use Qiangvei\SeoAi\Helper\Config;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Extracts template variables ({{name}}, {{price}}, ...) from a product or
 * category for use with the TemplateEngine.
 */
class VariableResolver
{
    /** Product attribute codes commonly used for brand, in priority order. */
    private const BRAND_ATTRIBUTES = ['brand', 'manufacturer', 'vendor'];

    private Config $config;
    private PriceCurrencyInterface $priceCurrency;
    private StoreManagerInterface $storeManager;

    public function __construct(
        Config $config,
        PriceCurrencyInterface $priceCurrency,
        StoreManagerInterface $storeManager
    ) {
        $this->config = $config;
        $this->priceCurrency = $priceCurrency;
        $this->storeManager = $storeManager;
    }

    /**
     * Build the variable array for a product.
     *
     * @return array<string, string>
     */
    public function fromProduct(Product $p, ?StoreInterface $store = null): array
    {
        $storeCode = $store ? (string) $store->getId() : null;

        $categoryName = '';
        $category = $this->resolveNearestCategory($p);
        if ($category !== null) {
            $categoryName = (string) $category->getName();
        }

        $shortDescription = $this->attr($p, 'short_description');
        $description = $this->attr($p, 'description');
        $metaDescription = $this->attr($p, 'meta_description');

        $variables = [
            'name'              => (string) $p->getName(),
            'price'             => $this->formatPrice($p, $store),
            'sku'               => (string) $p->getSku(),
            'brand'             => $this->resolveBrand($p, $storeCode),
            'category'          => $categoryName,
            'short_description' => $shortDescription,
            'description'       => $description,
            'meta_description'  => $metaDescription,
            'store_name'        => $this->resolveStoreName($store),
        ];

        // Prefer an explicit short description when description is empty so a
        // {{description}}-based template still renders something useful.
        if ($variables['description'] === '') {
            $variables['description'] = $shortDescription;
        }

        return $variables;
    }

    /**
     * Build the variable array for a category.
     *
     * @return array<string, string>
     */
    public function fromCategory(Category $c): array
    {
        $description = (string) ($c->getData('description') ?? '');

        return [
            'name'              => (string) $c->getName(),
            'price'             => '',
            'sku'               => '',
            'brand'             => '',
            'category'          => (string) $c->getName(),
            'short_description' => $description,
            'description'       => $description,
            'meta_description'  => (string) ($c->getData('meta_description') ?? ''),
            'store_name'        => $this->resolveStoreName(null),
        ];
    }

    private function attr(Product $p, string $code): string
    {
        $value = $p->getData($code);

        return $value !== null ? (string) $value : '';
    }

    private function resolveBrand(Product $p, ?string $store): string
    {
        foreach (self::BRAND_ATTRIBUTES as $code) {
            $attribute = $p->getAttribute($code);
            if ($attribute === null) {
                continue;
            }

            $value = $p->getData($code);
            if ($value === null || $value === '') {
                continue;
            }

            // Resolve option IDs (select/multiselect) to their labels.
            $frontend = $attribute->getFrontend();
            if ($frontend !== null) {
                $label = (string) $frontend->getValue($p);
                if ($label !== '') {
                    return $label;
                }
            }

            if (is_array($value)) {
                $value = implode(', ', array_map('strval', $value));
            }

            return (string) $value;
        }

        return $this->config->getDefaultBrand($store);
    }

    private function resolveNearestCategory(Product $p): ?Category
    {
        $categoryCollection = $p->getCategoryCollection();
        if ($categoryCollection === null) {
            return null;
        }

        $best = null;
        $bestLevel = -1;

        foreach ($categoryCollection as $category) {
            /** @var Category $category */
            $level = (int) $category->getLevel();
            if ($level > $bestLevel) {
                $bestLevel = $level;
                $best = $category;
            }
        }

        return $best;
    }

    private function formatPrice(Product $p, ?StoreInterface $store): string
    {
        $price = $p->getFinalPrice();
        if ($price === null) {
            $price = $p->getPrice();
        }

        if ($price === null) {
            return '';
        }

        $currencyCode = null;
        if ($store !== null && method_exists($store, 'getCurrentCurrency')) {
            $currency = $store->getCurrentCurrency();
            if ($currency !== null) {
                $currencyCode = $currency->getCurrencyCode();
            }
        }

        try {
            return $this->priceCurrency->format(
                (float) $price,
                true,
                PriceCurrencyInterface::DEFAULT_PRECISION,
                null,
                $currencyCode
            );
        } catch (\Throwable $e) {
            return (string) $price;
        }
    }

    private function resolveStoreName(?StoreInterface $store): string
    {
        if ($store !== null) {
            return (string) $store->getName();
        }

        try {
            return (string) $this->storeManager->getStore()->getName();
        } catch (\Throwable $e) {
            return '';
        }
    }
}
