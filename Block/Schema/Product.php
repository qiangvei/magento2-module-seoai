<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Block\Schema;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Framework\Registry;
use Magento\Review\Model\ResourceModel\Review\Summary\CollectionFactory as SummaryCollectionFactory;
use Magento\Review\Model\ResourceModel\Review\Product\CollectionFactory as ReviewCollectionFactory;

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
    private SummaryCollectionFactory $summaryCollectionFactory;
    private ReviewCollectionFactory $reviewCollectionFactory;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Serialize\Serializer\Json $serializer,
        \Qiangvei\SeoAi\Helper\Config $config,
        Registry $registry,
        Image $imageHelper,
        SummaryCollectionFactory $summaryCollectionFactory,
        ReviewCollectionFactory $reviewCollectionFactory,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->imageHelper = $imageHelper;
        $this->summaryCollectionFactory = $summaryCollectionFactory;
        $this->reviewCollectionFactory = $reviewCollectionFactory;
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

        $storeId = (int) $store->getId();

        try {
            $rating = $this->buildAggregateRating($product, $storeId);
            if ($rating !== null) {
                $graph['aggregateRating'] = $rating;
            }

            $reviews = $this->buildReviews($product, $storeId);
            if ($reviews !== []) {
                $graph['review'] = $reviews;
            }
        } catch (\Throwable $e) {
            // Reviews are optional structured data; never let a review-API
            // issue break the product page or the rest of the JSON-LD.
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
     * AggregateRating resolved from the review summary table.
     *
     * getRatingSummary()/getReviewCount() are NOT EAV attributes, so they are
     * null on a product loaded outside a review-summary join; we query the
     * Magento_Review summary collection directly instead.
     */
    private function buildAggregateRating(CatalogProduct $product, int $storeId): ?array
    {
        $summary = $this->loadSummary($product, $storeId);
        if ($summary === null) {
            return null;
        }

        $rating = $summary->getData('rating_summary'); // 0-100 percent
        $count = $summary->getData('reviews_count');
        if ($rating === null || (int) $count === 0) {
            return null;
        }

        return [
            '@type'       => 'AggregateRating',
            'ratingValue' => (string) round(((float) $rating / 100) * 5, 1),
            'reviewCount' => (int) $count,
            'bestRating'  => '5',
            'worstRating' => '1',
        ];
    }

    /**
     * Individual Review nodes for the product, most recent first.
     *
     * Only approved reviews with a rating are emitted; the review body is the
     * Review's description (a Google requirement for review rich results).
     *
     * @return array<int, array<string,mixed>>
     */
    private function buildReviews(CatalogProduct $product, int $storeId): array
    {
        $reviews = [];
        $collection = $this->reviewCollectionFactory->create();
        $collection->addEntityFilter($product->getId())
            ->addStatusFilter(\Magento\Review\Model\Review::STATUS_APPROVED)
            ->addStoreFilter($storeId)
            ->addRateVotes()
            ->setOrder('review_id', 'DESC')
            ->setPageSize(10);

        foreach ($collection->getItems() as $review) {
            $title = $this->cleanText((string) $review->getTitle());
            $detail = $this->cleanText((string) $review->getDetail());
            if ($title === '' && $detail === '') {
                continue;
            }

            $ratingValue = $this->resolveReviewRating($review);
            $node = [
                '@type'         => 'Review',
                'author'        => [
                    '@type' => 'Person',
                    'name'  => $this->cleanText((string) $review->getNickname()) ?: 'Customer',
                ],
                'name'          => $title !== '' ? $title : $detail,
                'reviewBody'    => $detail !== '' ? $detail : $title,
                'datePublished' => $this->cleanText((string) $review->getCreatedAt()),
            ];

            if ($ratingValue !== null) {
                $node['reviewRating'] = [
                    '@type'       => 'Rating',
                    'ratingValue' => (string) $ratingValue,
                    'bestRating'  => '5',
                    'worstRating' => '1',
                ];
            }

            $reviews[] = $node;
        }

        return $reviews;
    }

    /**
     * Resolve a 1-5 star rating for a single review from its rate votes.
     */
    private function resolveReviewRating($review): ?int
    {
        $votes = $review->getRatingVotes();
        if (!$votes || $votes->getSize() === 0) {
            return null;
        }

        $total = 0;
        $n = 0;
        foreach ($votes as $vote) {
            $total += (int) $vote->getValue();
            $n++;
        }

        if ($n === 0) {
            return null;
        }

        // Magento stores vote values as percentages of a 5-star scale.
        return (int) max(1, min(5, (int) round(($total / $n) / 100 * 5)));
    }

    /**
     * Load the cached review-summary row for the product.
     */
    private function loadSummary(CatalogProduct $product, int $storeId): ?\Magento\Framework\DataObject
    {
        $collection = $this->summaryCollectionFactory->create();
        $collection->addEntityPkFilter($product->getId())
            ->addStoreFilter($storeId);

        return $collection->getFirstItem();
    }
}
