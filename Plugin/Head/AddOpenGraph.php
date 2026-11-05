<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Plugin\Head;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Helper\Image as ProductImageHelper;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\Block\Html\Head;
use Qiangvei\SeoAi\Api\WatermarkProcessorInterface;
use Qiangvei\SeoAi\Helper\Config as SeoConfig;
use Qiangvei\SeoAi\Model\Meta\TemplateEngine;
use Qiangvei\SeoAi\Model\Meta\VariableResolver;

/**
 * Injects Open Graph and Twitter Card <meta> tags into the rendered head
 * HTML (after-plugin on Magento\Theme\Block\Html\Head::toHtml), before the
 * closing </head> tag.
 *
 * Guarded against re-entry via a static flag so nested toHtml calls do not
 * duplicate the tags.
 */
class AddOpenGraph
{
    /** In-flight guard shared across instances of this request. */
    private static bool $inProgress = false;

    private SeoConfig $config;
    private RequestInterface $request;
    private Registry $registry;
    private CatalogHelper $catalogHelper;
    private ProductImageHelper $productImageHelper;
    private StoreManagerInterface $storeManager;
    private TemplateEngine $templateEngine;
    private VariableResolver $variableResolver;
    private WatermarkProcessorInterface $watermarkProcessor;

    public function __construct(
        SeoConfig $config,
        RequestInterface $request,
        Registry $registry,
        CatalogHelper $catalogHelper,
        ProductImageHelper $productImageHelper,
        StoreManagerInterface $storeManager,
        TemplateEngine $templateEngine,
        VariableResolver $variableResolver,
        WatermarkProcessorInterface $watermarkProcessor
    ) {
        $this->config = $config;
        $this->request = $request;
        $this->registry = $registry;
        $this->catalogHelper = $catalogHelper;
        $this->productImageHelper = $productImageHelper;
        $this->storeManager = $storeManager;
        $this->templateEngine = $templateEngine;
        $this->variableResolver = $variableResolver;
        $this->watermarkProcessor = $watermarkProcessor;
    }

    /**
     * After plugin for Head::toHtml.
     *
     * @param Head  $subject
     * @param string $result
     * @return string
     */
    public function afterToHtml(Head $subject, string $result): string
    {
        if (self::$inProgress) {
            return $result;
        }

        if (!$this->config->isSeoEnabled() || !$this->config->isOpenGraphEnabled()) {
            return $result;
        }

        if (str_contains($result, 'property="og:title"') || str_contains($result, "property='og:title'")) {
            return $result;
        }

        $action = $this->request->getFullActionName();
        if ($action !== 'catalog_product_view' && $action !== 'catalog_category_view') {
            return $result;
        }

        $metaTags = $this->buildMetaTags($action);
        if ($metaTags === '') {
            return $result;
        }

        self::$inProgress = true;
        try {
            $tagBlock = "\n<!-- Qiangvei SeoAi: Open Graph / Twitter Card -->\n"
                . $metaTags
                . "\n";

            if (str_contains($result, '</head>')) {
                return str_replace('</head>', $tagBlock . '</head>', $result);
            }

            return $result . $tagBlock;
        } finally {
            self::$inProgress = false;
        }
    }

    private function buildMetaTags(string $action): string
    {
        $store = $this->currentStore();
        $siteName = $store ? (string) $store->getName() : '';
        $pageUrl = $this->request->getUriString();

        $title = '';
        $description = '';
        $image = '';
        $type = 'website';

        if ($action === 'catalog_product_view') {
            $product = $this->getCurrentProduct();
            if ($product === null) {
                return '';
            }

            $variables = $this->variableResolver->fromProduct($product, $store);
            $title = $this->templateEngine->renderTitle(
                $this->config->getProductMetaTitleTemplate(),
                $variables,
                $store ? (string) $store->getId() : null
            );
            $description = $this->templateEngine->renderDescription(
                $this->config->getProductMetaDescTemplate(),
                $variables
            );
            $image = $this->getProductImage($product);
            $type = 'product';
        } else {
            $category = $this->registry->registry('current_category');
            if ($category === null || !method_exists($category, 'getName')) {
                return '';
            }

            $variables = $this->variableResolver->fromCategory($category);
            $title = $this->templateEngine->renderTitle(
                $this->config->getCategoryMetaTitleTemplate(),
                $variables,
                $store ? (string) $store->getId() : null
            );
            $description = $this->templateEngine->renderDescription(
                $this->config->getCategoryMetaDescTemplate(),
                $variables
            );
        }

        if ($title === '' && $description === '' && $image === '') {
            return '';
        }

        $tags = [];
        $og = [
            'og:title'       => $title,
            'og:description' => $description,
            'og:type'        => $type,
            'og:url'         => $pageUrl,
            'og:site_name'   => $siteName,
            'og:image'       => $image,
        ];

        foreach ($og as $property => $content) {
            if ($content !== '') {
                $tags[] = $this->metaTag('property', $property, $content);
            }
        }

        if ($this->config->isTwitterEnabled()) {
            $twitter = [
                'twitter:card'        => $image !== '' ? 'summary_large_image' : 'summary',
                'twitter:site'        => $this->config->getTwitterSite(),
                'twitter:title'       => $title,
                'twitter:description' => $description,
                'twitter:image'       => $image,
            ];

            foreach ($twitter as $name => $content) {
                if ($content !== '') {
                    $tags[] = $this->metaTag('name', $name, $content);
                }
            }
        }

        return implode("\n", $tags);
    }

    private function metaTag(string $attr, string $key, string $content): string
    {
        return sprintf(
            '<meta %s="%s" content="%s" />',
            $attr,
            htmlspecialchars($key, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($content, ENT_QUOTES, 'UTF-8')
        );
    }

    private function getProductImage(ProductInterface $product): string
    {
        if (!$this->config->isOgImageEnabled()) {
            return '';
        }

        $image = '';
        try {
            $image = (string) $this->productImageHelper
                ->init($product, 'product_page_image_large')
                ->setImageFile($product->getData('small_image'))
                ->getUrl();
        } catch (\Throwable $e) {
            $image = '';
        }

        if ($image === '') {
            $default = $this->config->getDefaultOgImage();
            if ($default !== '') {
                $image = $this->mediaUrl('seoai/og/' . ltrim($default, '/'));
            }
        }

        if ($image !== '' && $this->config->isWatermarkEnabled()) {
            $image = $this->watermarkProcessor->process($image);
        }

        return $image;
    }

    private function mediaUrl(string $path): string
    {
        try {
            return rtrim(
                $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
                '/'
            ) . '/' . ltrim($path, '/');
        } catch (\Throwable $e) {
            return $path;
        }
    }

    private function getCurrentProduct(): ?ProductInterface
    {
        $product = $this->registry->registry('current_product');
        if ($product instanceof ProductInterface) {
            return $product;
        }

        try {
            return $this->catalogHelper->getProduct();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function currentStore(): ?\Magento\Store\Api\Data\StoreInterface
    {
        try {
            return $this->storeManager->getStore();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
