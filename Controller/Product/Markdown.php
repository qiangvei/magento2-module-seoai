<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Controller\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Qiangvei\SeoAi\Helper\Config;
use Throwable;

/**
 * Renders a single product as Markdown (PDP with ?format=md).
 */
class Markdown extends Action
{
    protected Config $config;

    protected ProductRepositoryInterface $productRepository;

    protected RawFactory $rawFactory;

    protected Registry $registry;

    protected StoreManagerInterface $storeManager;

    protected PriceCurrencyInterface $priceCurrency;

    public function __construct(
        Context $context,
        Config $config,
        ProductRepositoryInterface $productRepository,
        RawFactory $rawFactory,
        Registry $registry,
        StoreManagerInterface $storeManager,
        PriceCurrencyInterface $priceCurrency
    ) {
        parent::__construct($context);
        $this->config = $config;
        $this->productRepository = $productRepository;
        $this->rawFactory = $rawFactory;
        $this->registry = $registry;
        $this->storeManager = $storeManager;
        $this->priceCurrency = $priceCurrency;
    }

    /**
     * @inheritdoc
     */
    public function execute(): ResultInterface
    {
        /** @var Raw $result */
        $result = $this->rawFactory->create();
        $result->setHeader('Content-Type', 'text/markdown; charset=UTF-8', true);

        if (!$this->config->isMarkdownEnabled()) {
            $result->setHttpResponseCode(404);
            $result->setContents("404 Not Found\n");

            return $result;
        }

        $product = $this->resolveProduct();
        if ($product === null || (int) $product->getStatus() !== 1) {
            $result->setHttpResponseCode(404);
            $result->setContents("404 Not Found\n");

            return $result;
        }

        $result->setContents($this->render($product));
        $result->setHeader('X-Robots-Tag', 'noindex', true);

        return $result;
    }

    /**
     * Resolve the product from the request id, falling back to the registry
     * (set by the catalog product view controller).
     */
    private function resolveProduct(): ?ProductInterface
    {
        $storeId = (int) $this->storeManager->getStore()->getId();

        $id = (int) $this->getRequest()->getParam('id');
        if ($id <= 0) {
            $registered = $this->registry->registry('current_product');
            if ($registered instanceof ProductInterface) {
                return $registered;
            }

            return null;
        }

        try {
            return $this->productRepository->getById($id, false, $storeId);
        } catch (NoSuchEntityException) {
            return null;
        } catch (Throwable) {
            return null;
        }
    }

    private function render(ProductInterface $product): string
    {
        $lines = [];

        $lines[] = '# ' . $this->clean($product->getName());
        $lines[] = '';

        $lines[] = '- **SKU:** ' . $this->clean((string) $product->getSku());
        $lines[] = '- **Price:** ' . $this->formatPrice($product);

        $short = $this->clean((string) $product->getShortDescription());
        if ($short !== '') {
            $lines[] = '';
            $lines[] = '## Summary';
            $lines[] = '';
            $lines[] = $short;
        }

        $description = $this->clean((string) $product->getDescription());
        if ($description !== '') {
            $lines[] = '';
            $lines[] = '## Description';
            $lines[] = '';
            $lines[] = $description;
        }

        foreach ($this->parseJsonAttribute($product, 'seoai_faq') as $faq) {
            $lines[] = '';
            $lines[] = '## FAQ';
            $lines[] = '';
            $lines[] = '### ' . $this->clean((string) ($faq['q'] ?? ''));
            $lines[] = '';
            $lines[] = $this->clean((string) ($faq['a'] ?? ''));
        }

        foreach ($this->parseJsonAttribute($product, 'seoai_howto') as $step) {
            $lines[] = '';
            $lines[] = '## HowTo';
            $lines[] = '';
            $lines[] = '### ' . $this->clean((string) ($step['name'] ?? ''));
            $lines[] = '';
            $lines[] = $this->clean((string) ($step['text'] ?? ''));
        }

        $lines[] = '';
        $lines[] = '---';
        $lines[] = '[View online](' . $this->buildProductUrl($product) . ')';

        return implode("\n", $lines) . "\n";
    }

    private function formatPrice(ProductInterface $product): string
    {
        try {
            $amount = (float) $product->getFinalPrice();
            if ($amount <= 0) {
                $amount = (float) $product->getPrice();
            }

            return $this->priceCurrency->convertAndFormat($amount, false);
        } catch (Throwable) {
            return (string) $product->getPrice();
        }
    }

    private function buildProductUrl(ProductInterface $product): string
    {
        try {
            $url = (string) $product->getProductUrl();
            if ($url !== '') {
                return $url;
            }
        } catch (Throwable) {
            // fall through
        }

        $urlKey = (string) ($product->getUrlKey() ?: $product->getSku());
        $baseUrl = rtrim((string) $this->storeManager->getStore()->getBaseUrl(), '/');

        return $baseUrl !== '' ? $baseUrl . '/' . $urlKey : '/' . $urlKey;
    }

    /**
     * Decode a JSON-structured AEO attribute into an array of rows.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseJsonAttribute(ProductInterface $product, string $code): array
    {
        $raw = trim((string) $product->getData($code));
        if ($raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * Strip HTML and normalize whitespace, preserving paragraph breaks.
     */
    private function clean(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\R{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
