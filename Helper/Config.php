<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

/**
 * Central config reader for the SeoAi module.
 *
 * All subsystems read backend configuration through this helper so that
 * configuration paths live in exactly one place.
 *
 * @api
 */
class Config extends AbstractHelper
{
    /** XML path prefix for the whole module. */
    private const PREFIX = 'seoai/';

    /** Known AI crawlers => config field suffix. */
    public const AI_BOTS = [
        'GPTBot'         => 'bot_gptbot',
        'Google-Extended'=> 'bot_googleextended',
        'ClaudeBot'      => 'bot_claudebot',
        'Claude-Web'     => 'bot_anthropicai',
        'PerplexityBot'  => 'bot_perplexitybot',
        'CCBot'          => 'bot_ccbot',
        'Bytespider'     => 'bot_bytespider',
        'ImagesiftBot'   => 'bot_imagesift',
        'omgilibot'      => 'bot_omgili',
        'Diffbot'        => 'bot_diffbot',
    ];

    /**
     * Read an arbitrary config value under seoai/.
     *
     * @param string      $key   Path relative to seoai/ (e.g. "seo/enabled").
     * @param string|null $store
     * @return string|null
     */
    public function getValue(string $key, ?string $store = null): ?string
    {
        $scope = $store !== null ? ScopeInterface::SCOPE_STORE : ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $value = $this->scopeConfig->getValue(
            self::PREFIX . $key,
            $scope,
            $store
        );

        return $value !== null ? (string) $value : null;
    }

    /**
     * Boolean config shortcut.
     */
    public function isSetFlag(string $key, ?string $store = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::PREFIX . $key,
            $store !== null ? ScopeInterface::SCOPE_STORE : ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $store
        );
    }

    // ---------------------------------------------------------------
    // SEO group
    // ---------------------------------------------------------------

    public function isSeoEnabled(?string $store = null): bool
    {
        return $this->isSetFlag('seo/enabled', $store);
    }

    public function getTitleSuffix(?string $store = null): string
    {
        return (string) $this->getValue('seo/title_suffix', $store);
    }

    public function isMetaDescriptionEnabled(?string $store = null): bool
    {
        return $this->isSetFlag('seo/meta_desc_enabled', $store);
    }

    public function isCanonicalEnabled(?string $store = null): bool
    {
        return $this->isSetFlag('seo/canonical_enabled', $store);
    }

    public function isStripParams(?string $store = null): bool
    {
        return $this->isSetFlag('seo/strip_params', $store);
    }

    public function isHreflangEnabled(?string $store = null): bool
    {
        return $this->isSetFlag('seo/hreflang_enabled', $store);
    }

    public function getProductMetaTitleTemplate(?string $store = null): string
    {
        return (string) $this->getValue('seo/product_meta_tpl', $store);
    }

    public function getProductMetaDescTemplate(?string $store = null): string
    {
        return (string) $this->getValue('seo/product_meta_desc_tpl', $store);
    }

    public function getCategoryMetaTitleTemplate(?string $store = null): string
    {
        return (string) $this->getValue('seo/category_meta_tpl', $store);
    }

    public function getCategoryMetaDescTemplate(?string $store = null): string
    {
        return (string) $this->getValue('seo/category_meta_desc_tpl', $store);
    }

    // ---------------------------------------------------------------
    // Schema group
    // ---------------------------------------------------------------

    public function isProductSchemaEnabled(?string $store = null): bool
    {
        return $this->isSetFlag('schema/product_enabled', $store);
    }

    public function isBreadcrumbSchemaEnabled(?string $store = null): bool
    {
        return $this->isSetFlag('schema/breadcrumb_enabled', $store);
    }

    public function isOrganizationSchemaEnabled(?string $store = null): bool
    {
        return $this->isSetFlag('schema/organization_enabled', $store);
    }

    public function isWebsiteSearchSchemaEnabled(?string $store = null): bool
    {
        return $this->isSetFlag('schema/website_search_enabled', $store);
    }

    public function isFaqSchemaEnabled(?string $store = null): bool
    {
        return $this->isSetFlag('schema/faq_enabled', $store);
    }

    public function isHowtoSchemaEnabled(?string $store = null): bool
    {
        return $this->isSetFlag('schema/howto_enabled', $store);
    }

    /**
     * Whether to suppress Luma's legacy microdata (itemscope/itemprop) so the
     * JSON-LD is the single source of product structured data.
     */
    public function isSuppressMicrodata(?string $store = null): bool
    {
        return $this->isSetFlag('schema/suppress_microdata', $store);
    }

    /**
     * Whether to strip <noscript> blocks that confuse Rich Results Test.
     */
    public function isHandleNoscript(?string $store = null): bool
    {
        return $this->isSetFlag('schema/handle_noscript', $store);
    }

    public function getDefaultBrand(?string $store = null): string
    {
        return (string) $this->getValue('schema/brand', $store);
    }

    /**
     * ISO 3166-1 alpha-2 destination country for the OfferShippingDetails block.
     */
    public function getShippingCountry(?string $store = null): string
    {
        return (string) $this->getValue('schema/shipping_country', $store);
    }

    /**
     * Flat shipping rate value (e.g. "0" for free shipping).
     */
    public function getShippingRate(?string $store = null): string
    {
        return (string) $this->getValue('schema/shipping_rate', $store);
    }

    /**
     * Handling time range in days [min, max].
     *
     * @return array{0:int,1:int}
     */
    public function getHandlingTime(?string $store = null): array
    {
        return [
            (int) $this->getValue('schema/shipping_handling_min', $store),
            (int) $this->getValue('schema/shipping_handling_max', $store),
        ];
    }

    /**
     * Transit time range in days [min, max].
     *
     * @return array{0:int,1:int}
     */
    public function getTransitTime(?string $store = null): array
    {
        return [
            (int) $this->getValue('schema/shipping_transit_min', $store),
            (int) $this->getValue('schema/shipping_transit_max', $store),
        ];
    }

    /**
     * ISO 3166-1 alpha-2 applicable country for the MerchantReturnPolicy.
     */
    public function getReturnCountry(?string $store = null): string
    {
        return (string) $this->getValue('schema/return_country', $store);
    }

    /**
     * Number of days the customer has to return a product.
     */
    public function getReturnDays(?string $store = null): int
    {
        return (int) $this->getValue('schema/return_days', $store);
    }

    /**
     * schema.org return method URL (e.g. ReturnByMail).
     */
    public function getReturnMethod(?string $store = null): string
    {
        return (string) $this->getValue('schema/return_method', $store);
    }

    /**
     * schema.org return fees URL (e.g. FreeReturn).
     */
    public function getReturnFees(?string $store = null): string
    {
        return (string) $this->getValue('schema/return_fees', $store);
    }

    public function getOrganizationName(?string $store = null): string
    {
        return (string) $this->getValue('schema/organization_name', $store);
    }

    public function getOrganizationLogo(?string $store = null): string
    {
        return (string) $this->getValue('schema/organization_logo', $store);
    }

    /**
     * @return string[]
     */
    public function getOrganizationSameAs(?string $store = null): array
    {
        return $this->toLines((string) $this->getValue('schema/organization_same_as', $store));
    }

    // ---------------------------------------------------------------
    // AEO group
    // ---------------------------------------------------------------

    public function isFaqHtmlEnabled(?string $store = null): bool
    {
        return $this->isSetFlag('aeo/faq_html_enabled', $store);
    }

    public function isLlmsEnabled(?string $store = null): bool
    {
        return $this->isSetFlag('aeo/llms_enabled', $store);
    }

    public function isLlmsFullEnabled(?string $store = null): bool
    {
        return $this->isSetFlag('aeo/llms_full_enabled', $store);
    }

    public function getLlmsSummary(?string $store = null): string
    {
        return (string) $this->getValue('aeo/llms_summary', $store);
    }

    public function isMarkdownEnabled(?string $store = null): bool
    {
        return $this->isSetFlag('aeo/markdown_enabled', $store);
    }

    // ---------------------------------------------------------------
    // GEO group
    // ---------------------------------------------------------------

    public function isRobotsEnabled(?string $store = null): bool
    {
        return $this->isSetFlag('geo/robots_enabled', $store);
    }

    /**
     * Returns map of User-Agent => shouldBlock.
     *
     * @return array<string, bool>
     */
    public function getAiBotPolicies(?string $store = null): array
    {
        $result = [];
        foreach (self::AI_BOTS as $userAgent => $field) {
            $result[$userAgent] = $this->isSetFlag('geo/' . $field, $store);
        }

        return $result;
    }

    public function getEntityName(?string $store = null): string
    {
        return (string) $this->getValue('geo/entity_name', $store);
    }

    public function getEntityPhone(?string $store = null): string
    {
        return (string) $this->getValue('geo/entity_phone', $store);
    }

    public function getEntityAddress(?string $store = null): string
    {
        return (string) $this->getValue('geo/entity_address', $store);
    }

    /**
     * @return string[]
     */
    public function getBrandSameAs(?string $store = null): array
    {
        return $this->toLines((string) $this->getValue('geo/brand_same_as', $store));
    }

    // ---------------------------------------------------------------
    // Open Graph group
    // ---------------------------------------------------------------

    public function isOpenGraphEnabled(?string $store = null): bool
    {
        return $this->isSetFlag('og/enabled', $store);
    }

    public function isTwitterEnabled(?string $store = null): bool
    {
        return $this->isSetFlag('og/twitter_enabled', $store);
    }

    public function getTwitterSite(?string $store = null): string
    {
        return (string) $this->getValue('og/twitter_site', $store);
    }

    public function isOgImageEnabled(?string $store = null): bool
    {
        return $this->isSetFlag('og/og_image_enabled', $store);
    }

    public function isWatermarkEnabled(?string $store = null): bool
    {
        return $this->isSetFlag('og/watermark_enabled', $store);
    }

    public function getWatermarkImage(?string $store = null): string
    {
        return (string) $this->getValue('og/watermark_image', $store);
    }

    public function getWatermarkOpacity(?string $store = null): int
    {
        $value = $this->getValue('og/watermark_opacity', $store);

        return $value !== null && $value !== '' ? (int) $value : 60;
    }

    public function getWatermarkPosition(?string $store = null): string
    {
        return (string) ($this->getValue('og/watermark_position', $store) ?: 'br');
    }

    public function getDefaultOgImage(?string $store = null): string
    {
        return (string) $this->getValue('og/default_og_image', $store);
    }

    // ---------------------------------------------------------------
    // Sitemap group
    // ---------------------------------------------------------------

    public function isSitemapImageEnabled(?string $store = null): bool
    {
        return $this->isSetFlag('sitemap/image_enabled', $store);
    }

    public function isSitemapNewsEnabled(?string $store = null): bool
    {
        return $this->isSetFlag('sitemap/news_enabled', $store);
    }

    public function isLlmsLinkInRobots(?string $store = null): bool
    {
        return $this->isSetFlag('sitemap/add_llms_link', $store);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * @return string[]
     */
    private function toLines(string $value): array
    {
        $lines = preg_split('/\R+/', trim($value)) ?: [];

        return array_values(array_filter(array_map('trim', $lines), static fn ($l) => $l !== ''));
    }
}