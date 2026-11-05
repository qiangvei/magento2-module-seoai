<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Block\Schema;

use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Catalog\Model\Category;
use Magento\Framework\Registry;

/**
 * BreadcrumbList schema for the current category or product page.
 */
class Breadcrumb extends JsonLd
{
    private Registry $registry;
    private CatalogHelper $catalogHelper;
    private CollectionFactory $categoryCollectionFactory;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Serialize\Serializer\Json $serializer,
        \Qiangvei\SeoAi\Helper\Config $config,
        Registry $registry,
        CatalogHelper $catalogHelper,
        CollectionFactory $categoryCollectionFactory,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->catalogHelper = $catalogHelper;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        parent::__construct($context, $serializer, $config, $data);
    }

    protected function getGraph(): ?array
    {
        if (!$this->config->isBreadcrumbSchemaEnabled()) {
            return null;
        }

        $items = $this->buildTrail();
        // buildTrail() always includes the store home; a lone home entry means
        // there is no real breadcrumb trail, so suppress output.
        if (count($items) < 2) {
            return null;
        }

        $elementList = [];
        $position = 1;
        foreach ($items as $label => $url) {
            $elementList[] = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'name'     => $this->cleanText($label),
                'item'     => $url,
            ];
        }

        return [
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $elementList,
        ];
    }

    /**
     * @return array<string,string> label => url, ordered root -> leaf
     */
    private function buildTrail(): array
    {
        $store = $this->_storeManager->getStore();
        $baseUrl = rtrim($store->getBaseUrl(), '/') . '/';
        $homeLabel = $store->getFrontendName() ?: 'Home';

        $trail = [$homeLabel => $baseUrl];

        $product = $this->registry->registry('current_product');
        $category = $this->registry->registry('current_category');

        if (!$category instanceof Category && $product instanceof \Magento\Catalog\Model\Product) {
            $category = $product->getCategory();
        }

        if (!$category instanceof Category) {
            return $trail;
        }

        $pathIds = $category->getPathIds();
        if (count($pathIds) <= 1) {
            return $trail;
        }

        // Drop root (id 1) and store root category; we add the store home above.
        $pathIds = array_filter(
            $pathIds,
            static fn ($id) => (int) $id > 1 && (int) $id !== (int) $store->getRootCategoryId()
        );

        if ($pathIds === []) {
            return $trail;
        }

        // Note: addUrlRewrite() exists on the product collection but NOT on the
        // category collection; Category::getUrl() resolves the category URL
        // itself, so no rewrite table join is needed here.
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'url_key', 'url_path'])
            ->addAttributeToFilter('entity_id', ['in' => array_values($pathIds)]);

        $byId = [];
        foreach ($collection as $cat) {
            $byId[(int) $cat->getId()] = $cat;
        }

        // Keep path order.
        foreach ($pathIds as $id) {
            $cat = $byId[(int) $id] ?? null;
            if (!$cat instanceof Category) {
                continue;
            }
            $label = $this->cleanText($cat->getName());
            $url = $cat->getUrl();
            if ($label !== '' && $url) {
                $trail[$label] = $url;
            }
        }

        // Append product name as the leaf when on a product page.
        if ($product instanceof \Magento\Catalog\Model\Product) {
            $pname = $this->cleanText($product->getName());
            $purl = $product->getProductUrl();
            if ($pname !== '' && $purl) {
                $trail[$pname] = $purl;
            }
        }

        return $trail;
    }
}
