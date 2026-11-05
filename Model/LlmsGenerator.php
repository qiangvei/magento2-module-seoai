<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Model;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Qiangvei\SeoAi\Api\LlmsGeneratorInterface;
use Qiangvei\SeoAi\Helper\Config;
use Throwable;

/**
 * Builds llms.txt / llms-full.txt documents following the llms.txt convention.
 *
 * Structure:
 *   # Store Name
 *   > summary
 *   Optional descriptive paragraph(s).
 *   ## Category Name
 *   - [Product Name](url): short description
 *   (full mode)     Indented full description paragraph per product.
 *   ## Uncategorized  (products not attached to any enabled category)
 *
 * Performance: only the attributes required for rendering are selected and the
 * product collection is capped (full: 200, summary: 500).
 */
class LlmsGenerator implements LlmsGeneratorInterface
{
    private const LIMIT_FULL = 200;

    private const LIMIT_SUMMARY = 500;

    /** Product attributes selected for the summary (lightweight) listing. */
    private const SUMMARY_ATTRIBUTES = ['name', 'short_description', 'url_key', 'status', 'visibility'];

    /** Product attributes selected when full descriptions are requested. */
    private const FULL_ATTRIBUTES = ['name', 'short_description', 'description', 'url_key', 'status', 'visibility'];

    private Config $config;

    private ProductCollectionFactory $productCollectionFactory;

    private CategoryCollectionFactory $categoryCollectionFactory;

    private CategoryRepositoryInterface $categoryRepository;

    private StoreManagerInterface $storeManager;

    private UrlInterface $url;

    public function __construct(
        Config $config,
        ProductCollectionFactory $productCollectionFactory,
        CategoryCollectionFactory $categoryCollectionFactory,
        CategoryRepositoryInterface $categoryRepository,
        StoreManagerInterface $storeManager,
        UrlInterface $url
    ) {
        $this->config = $config;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->categoryRepository = $categoryRepository;
        $this->storeManager = $storeManager;
        $this->url = $url;
    }

    /**
     * @inheritdoc
     */
    public function generate(bool $full): string
    {
        $store = $this->storeManager->getStore();
        $storeId = (int) $store->getId();
        $storeCode = (string) $store->getCode();

        $lines = [
            '# ' . $this->getStoreName($storeId),
            '',
        ];

        $summary = trim($this->config->getLlmsSummary($storeCode));
        if ($summary !== '') {
            foreach (preg_split('/\R+/', $summary) ?: [] as $line) {
                $line = trim($line);
                $lines[] = $line !== '' ? '> ' . $line : '>';
            }
            $lines[] = '';
        }

        $products = $this->loadProducts($full, $storeId);
        if ($products === []) {
            $lines[] = '_No products available._';

            return $this->finalize($lines);
        }

        $grouped = $this->groupProductsByCategory($products, $storeId);

        foreach ($grouped as $groupName => $items) {
            $lines[] = '## ' . $groupName;
            $lines[] = '';

            foreach ($items as $item) {
                $lines[] = sprintf(
                    '- [%s](%s): %s',
                    $item['name'],
                    $item['url'],
                    $item['short']
                );

                if ($full && $item['description'] !== '') {
                    // Indented paragraph per llms.txt convention so the full
                    // description stays attached to its list entry.
                    foreach (preg_split('/\R+/', $item['description']) ?: [] as $descLine) {
                        $descLine = trim($descLine);
                        if ($descLine !== '') {
                            $lines[] = '  ' . $descLine;
                        }
                    }
                    $lines[] = '';
                }
            }

            if (!$full || $groupName === array_key_last($grouped)) {
                $lines[] = '';
            }
        }

        return $this->finalize($lines);
    }

    /**
     * Load a capped, store-scoped collection of enabled, visible products.
     *
     * @return array<int, ProductInterface>
     */
    private function loadProducts(bool $full, int $storeId): array
    {
        $limit = $full ? self::LIMIT_FULL : self::LIMIT_SUMMARY;

        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect($full ? self::FULL_ATTRIBUTES : self::SUMMARY_ATTRIBUTES);
        $collection->addAttributeToFilter('status', ['eq' => ProductStatus::STATUS_ENABLED]);
        $collection->addAttributeToFilter('visibility', ['neq' => Visibility::VISIBILITY_NOT_VISIBLE]);
        $collection->addUrlRewrite();
        $collection->addCategoryIds();
        $collection->addAttributeToSort('name', 'ASC');
        $collection->setPageSize($limit);

        return array_values($collection->getItems());
    }

    /**
     * Group products under their (anchor-aware) category names.
     *
     * Products attached to no in-store category fall under "Uncategorized".
     *
     * @param array<int, ProductInterface> $products
     * @return array<string, array<int, array{name:string,url:string,short:string,description:string}>>
     */
    private function groupProductsByCategory(array $products, int $storeId): array
    {
        $categoryNames = $this->loadCategoryNames($products, $storeId);
        $grouped = [];

        foreach ($products as $product) {
            $entry = $this->buildEntry($product);
            $categoryIds = (array) $product->getCategoryIds();
            $assigned = false;

            foreach ($categoryIds as $categoryId) {
                $categoryId = (int) $categoryId;
                $name = $categoryNames[$categoryId] ?? null;
                if ($name === null || $name === '') {
                    continue;
                }
                $grouped[$name][] = $entry;
                $assigned = true;
            }

            if (!$assigned) {
                $grouped['Uncategorized'][] = $entry;
            }
        }

        ksort($grouped, SORT_STRING | SORT_FLAG_CASE);
        // Keep Uncategorized last for a cleaner document.
        if (isset($grouped['Uncategorized'])) {
            $uncategorized = $grouped['Uncategorized'];
            unset($grouped['Uncategorized']);
            $grouped['Uncategorized'] = $uncategorized;
        }

        return $grouped;
    }

    /**
     * Resolve display names for every category referenced by the products.
     *
     * @return array<int, string>
     */
    private function loadCategoryNames(array $products, int $storeId): array
    {
        $ids = [];
        foreach ($products as $product) {
            foreach ((array) $product->getCategoryIds() as $id) {
                $ids[(int) $id] = true;
            }
        }
        $ids = array_keys($ids);
        if ($ids === []) {
            return [];
        }

        $collection = $this->categoryCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect('name');
        $collection->addAttributeToFilter('is_active', ['eq' => 1]);
        $collection->addAttributeToFilter('entity_id', ['in' => $ids]);
        $collection->addAttributeToFilter('level', ['gt' => 1]);

        $names = [];
        foreach ($collection->getItems() as $category) {
            $names[(int) $category->getId()] = (string) $category->getName();
        }

        return $names;
    }

    /**
     * Build the markdown list entry data for a single product.
     *
     * @return array{name:string,url:string,short:string,description:string}
     */
    private function buildEntry(ProductInterface $product): array
    {
        $name = trim((string) $product->getName());
        $url = $this->buildProductUrl($product);
        $short = $this->toPlainText((string) $product->getShortDescription(), 180);
        $description = $this->toPlainText((string) $product->getDescription(), 0);

        return [
            'name'        => $name !== '' ? $name : (string) $product->getSku(),
            'url'         => $url,
            'short'       => $short,
            'description' => $description,
        ];
    }

    /**
     * Resolve the storefront URL for a product, preferring its rewrite.
     */
    private function buildProductUrl(ProductInterface $product): string
    {
        try {
            $url = (string) $product->getProductUrl();
            if ($url !== '') {
                return $url;
            }
        } catch (Throwable) {
            // fall through to path-based URL
        }

        $urlKey = (string) ($product->getUrlKey() ?: $product->getSku());

        return (string) $this->url->getUrl($urlKey);
    }

    /**
     * Strip HTML/markdown noise and collapse whitespace.
     *
     * @param int $maxChars 0 disables truncation.
     */
    private function toPlainText(string $html, int $maxChars): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\s*\R\s*/', ' ', $text) ?? $text;
        $text = trim($text);

        if ($maxChars > 0 && mb_strlen($text) > $maxChars) {
            $text = rtrim(mb_substr($text, 0, $maxChars - 1));
            $text .= "\u{2026}"; // ellipsis
        }

        return $text;
    }

    private function getStoreName(int $storeId): string
    {
        try {
            $name = trim((string) $this->storeManager->getStore($storeId)->getName());
            if ($name !== '') {
                return $name;
            }
        } catch (NoSuchEntityException) {
            // fall through
        }

        return 'Store';
    }

    /**
     * Normalize the trailing newlines of the document.
     *
     * @param array<int, string> $lines
     */
    private function finalize(array $lines): string
    {
        $body = implode("\n", $lines);
        $body = rtrim($body);

        return $body !== '' ? $body . "\n" : '';
    }
}
