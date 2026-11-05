<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Block\Schema;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Framework\Registry;

/**
 * Product schema.org/Product node for the current product page.
 *
 * Note: this class is intentionally named Product (Qiangvei\SeoAi\Block\Schema\Product).
 * The Magento\Catalog\Model\Product model is imported under the alias
 * CatalogProduct to avoid a class-name collision within this namespace.
 */
class Product extends JsonLd
{
    private Registry $registry;
    private Image $imageHelper;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Serialize\Serializer\Json $serializer,
        \Qiangvei\SeoAi\Helper\Config $config,
        Registry $registry,
        Image $imageHelper,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->imageHelper = $imageHelper;
        parent::__construct($context, $serializer, $config, $data);
    }

    protected function getGraph(): ?array
    {
        if (!$this->config->isProductSchemaEnabled()) {
            return null;
        }

        $product = $this->registry->registry('current_product');
        if (!$product instanceof CatalogProduct) {
            return null;
        }

        $store = $this->_storeManager->getStore();
        $storeCode = $store->getCode();
        $product->setStoreId($store->getId());

        $name = $this->cleanText($product->getName());
        if ($name === '') {
            return null;
        }

        $imageUrls = $this->getProductImages($product);

        $brand = $this->cleanText(
            $this->resolveBrand($product, $storeCode)
        );

        $graph = [
            '@type'        => 'Product',
            'name'         => $name,
            'sku'          => (string) $product->getSku(),
            'description'  => $this->cleanText(
                $product->getShortDescription() ?: $product->getDescription()
            ),
        ];

        if ($imageUrls !== []) {
            $graph['image'] = $imageUrls;
        }
        if ($brand !== '') {
            $graph['brand'] = ['@type' => 'Brand', 'name' => $brand];
        }

        $offers = $this->buildOffers($product, $storeCode);
        if ($offers !== null) {
            $graph['offers'] = $offers;
        }

        
        try {
            
                $summaryCollectionFactory = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Review\Model\ResourceModel\Review\Summary\CollectionFactory::class);
                
                $summaryCollection = $summaryCollectionFactory->create()
                    ->addStoreFilter($store->getId())
                    ->addFieldToFilter('entity_pk_value', $product->getId())
                    ->load();
                
                $summaryData = $summaryCollection->getFirstItem();
                
                if ($summaryData && $summaryData->getReviewsCount() > 0) {
                    $ratingSum = (int)$summaryData->getRatingSummary();
                    $reviewCount = (int)$summaryData->getReviewsCount();
                    if ($reviewCount > 0) {
                        // Magento 评分是 0-100，Schema 期望 1-5
                        $ratingValue = round(($ratingSum / 20), 1);
                        $graph['aggregateRating'] = [
                            '@type'       => 'AggregateRating',
                            'ratingValue' => (string)$ratingValue,
                            'reviewCount' => (string)$reviewCount,
                            'bestRating'  => 5,
                            'worstRating' => '1',
                        ];
                    }
                }
            } catch (\Exception $e) {
                // 忽略异常，防止因评论表未初始化或异常而导致页面崩溃
            }

        // 4. 【条件注入】全球贸易代码 GTIN / 制造商编号 MPN
        // 假设你在后台建立了名为 gtin 和 mpn 的产品属性
        $gtin = $product->getData('gtin');
        if (!empty($gtin)) {
            // 根据 GTIN 长度动态适配 gtin8, gtin13, gtin14 等，或者统一输出 gtin
            $gtinLength = strlen(trim($gtin));
            if (in_array($gtinLength, [8, 12, 13, 14])) {
                $graph['gtin' . $gtinLength] = (string)$gtin;
            } else {
                $graph['gtin'] = (string)$gtin;
            }
        }
        
        $mpn = $product->getData('mpn');
        if (!empty($mpn)) {
            $graph['mpn'] = (string)$mpn;
        }

        return $graph;
    }

    /**
     * @return string[]
     */
    private function getProductImages(CatalogProduct $product): array
    {
        $urls = [];
        $image = $this->imageHelper->init($product, 'product_base_image')->getUrl();
        if ($image !== '') {
            $urls[] = $image;
        }

        $gallery = $product->getMediaGalleryImages();
        if ($gallery) {
            foreach ($gallery as $galleryImage) {
                $url = (string) $galleryImage->getUrl();
                if ($url !== '' && !in_array($url, $urls, true)) {
                    $urls[] = $url;
                }
            }
        }

        return array_values(array_filter($urls));
    }

    private function resolveBrand(CatalogProduct $product, string $storeCode): string
    {
        $brandName = '';
        if ($product->getResource()->getAttribute('brand') && $product->getData('brand')) {
            $brandName = $product->getAttributeText('brand');
        }elseif ($product->getManufacturer()) {
            $brandName = $product->getAttributeText('manufacturer');
        }elseif ($product->getResource()->getAttribute('vendor') && $product->getData('vendor')) {
            $brandName = $product->getAttributeText('vendor');
        }
        if ($brandName) {
            if (is_array($brandName)) {
                $brandName = reset($brandName);
            }
            return $brandName;
        }

        return '';
    }

    private function buildOffers(CatalogProduct $product, string $storeCode): ?array
    {
        $price = $product->getFinalPrice();
        if ($price === null || (float) $price <= 0) {
            return null;
        }

        $store = $this->_storeManager->getStore();
        $currency = (string) ($store->getCurrentCurrencyCode()
            ?: $store->getBaseCurrencyCode()
            ?: 'USD');

        $productUrl = $product->getProductUrl();
        $isSaleable = $product->isSaleable();

        $offer = [
            '@type'         => 'Offer',
            'price'         => number_format((float) $price, 2, '.', ''),
            'priceCurrency' => $currency ?: 'USD',
            'availability'  => $isSaleable
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock',
            'url'           => $productUrl,
            'itemCondition' => 'https://schema.org/NewCondition',
        ];

        // Google merchant listing required: shipping details + return policy.
        $shipping = $this->buildShippingDetails($storeCode, $currency ?: 'USD');
        if ($shipping !== null) {
            $offer['shippingDetails'] = $shipping;
        }

        $returnPolicy = $this->buildReturnPolicy($storeCode);
        if ($returnPolicy !== null) {
            $offer['hasMerchantReturnPolicy'] = $returnPolicy;
        }

        return $offer;
    }

    /**
     * Builds an OfferShippingDetails node from global config defaults.
     */
    private function buildShippingDetails(string $storeCode, string $currency): ?array
    {
        $country = $this->config->getShippingCountry($storeCode);
        if ($country === '') {
            // No destination country configured -> cannot emit a valid block.
            return null;
        }

        [$handlingMin, $handlingMax] = $this->config->getHandlingTime($storeCode);
        [$transitMin, $transitMax] = $this->config->getTransitTime($storeCode);

        return [
            '@type'              => 'OfferShippingDetails',
            'shippingRate'       => [
                '@type'    => 'MonetaryAmount',
                'value'    => $this->config->getShippingRate($storeCode),
                'currency' => $currency,
            ],
            'shippingDestination'=> [
                '@type'          => 'DefinedRegion',
                'addressCountry' => $country,
            ],
            'deliveryTime'       => [
                '@type'        => 'ShippingDeliveryTime',
                'handlingTime' => [
                    '@type'    => 'QuantitativeValue',
                    'minValue' => $handlingMin,
                    'maxValue' => $handlingMax,
                    'unitCode' => 'DAY',
                ],
                'transitTime'  => [
                    '@type'    => 'QuantitativeValue',
                    'minValue' => $transitMin,
                    'maxValue' => $transitMax,
                    'unitCode' => 'DAY',
                ],
            ],
        ];
    }

    /**
     * Builds a MerchantReturnPolicy node from global config defaults.
     */
    private function buildReturnPolicy(string $storeCode): ?array
    {
        $country = $this->config->getReturnCountry($storeCode);
        $days = $this->config->getReturnDays($storeCode);
        if ($country === '' || $days <= 0) {
            // Cannot emit a valid finite-window return policy without these.
            return null;
        }

        $method = $this->config->getReturnMethod($storeCode) ?: 'https://schema.org/ReturnByMail';
        $fees = $this->config->getReturnFees($storeCode) ?: 'https://schema.org/FreeReturn';

        return [
            '@type'                => 'MerchantReturnPolicy',
            'applicableCountry'    => $country,
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays'   => $days,
            'returnMethod'         => $method,
            'returnFees'           => $fees,
        ];
    }

    /**
     * Load the review-summary row for the product from the cached aggregate
     * table, keyed by (product id, website id) — the way Magento's own review
     * summary block does it.
     *
     * getRatingSummary()/getReviewsCount() are NOT EAV attributes, so they are
     * null on a plain product object; this is the correct source for them.
     *
     * Instantiated via ObjectManager (rather than a constructor dependency)
     * so that any variance in the review summary factory/table across
     * environments is caught by the caller's try/catch and simply results in
     * the aggregateRating node being omitted, never a broken page.
     */
    private function loadReviewSummary(CatalogProduct $product): ?\Magento\Framework\DataObject
    {
        $factory = \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Magento\Review\Model\Review\SummaryFactory::class);
        $summary = $factory->create();
        $summary->load($product->getId(), (int) $this->_storeManager->getStore()->getWebsiteId());

        if (!$summary->getId()) {
            return null;
        }

        return $summary;
    }
}
