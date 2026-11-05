<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Plugin\Head;

use Magento\Framework\App\RequestInterface;
use Magento\Theme\Block\Html\Head;
use Qiangvei\SeoAi\Helper\Config as SeoConfig;

/**
 * Post-processes the fully rendered <head> markup for the product page.
 *
 * Two concerns, both driven by the migration from legacy microdata to JSON-LD:
 *
 * 1. Suppress microdata
 *    Luma still emits scattered microdata attributes (itemprop on various
 *    product blocks and sometimes a leftover itemscope/itemtype on body or
 *    inline wrappers) that can coexist with (and slightly conflict with) the
 *    authoritative JSON-LD this module emits. When configured, this plugin
 *    strips those attributes from the head markup so the JSON-LD block is the
 *    single source of truth for Google / AI crawlers.
 *
 * 2. Handle noscript
 *    Magento ships a <noscript> block (usually a JS-disabled notice or a meta
 *    refresh redirect). Rich Results Test sometimes misreads that block and
 *    reports "JavaScript disabled" / "content mismatch" false positives. When
 *    configured, this plugin neutralises the redirect behaviour of noscript
 *    blocks while keeping a gentle text hint.
 *
 * The plugin targets Head::toHtml (after) so it sees the final head HTML,
 * including the JSON-LD script tags injected by the layout blocks.
 */
class SanitizeHead
{
    /** Matches any microdata attribute (itemprop / itemscope / itemtype). */
    private const MICRODATA_ATTR_PATTERN = '/\s+item(?:prop|scope|type)\s*(?:=\s*(?:"[^"]*"|\'[^\']*\'))?/i';

    /** Matches a noscript block that performs a meta-refresh redirect. */
    private const NOSCRIPT_REDIRECT_PATTERN
        = '#<noscript[^>]*>\s*<meta\s+http-equiv\s*=\s*["\']refresh["\'][^>]*/?\s*>\s*</noscript>#i';

    private SeoConfig $config;

    private RequestInterface $request;

    public function __construct(SeoConfig $config, RequestInterface $request)
    {
        $this->config = $config;
        $this->request = $request;
    }

    /**
     * Clean the rendered head markup.
     *
     * @param Head   $subject
     * @param string $result
     * @return string
     */
    public function afterToHtml(Head $subject, string $result): string
    {
        if (!$this->config->isSeoEnabled()) {
            return $result;
        }

        if (!$this->isProductPage()) {
            return $result;
        }

        if ($this->config->isHandleNoscript()) {
            $result = $this->neutraliseNoscript($result);
        }

        if ($this->config->isSuppressMicrodata()) {
            $result = $this->stripMicrodata($result);
        }

        return $result;
    }

    /**
     * Replace redirect-based noscript blocks with a plain hint so Rich Results
     * Test does not flag them as a JS-disabled redirect.
     */
    private function neutraliseNoscript(string $html): string
    {
        $hint = '<noscript><span class="seoai-js-hint">' . "\n"
            . 'This experience requires JavaScript for full functionality.'
            . "\n" . '</span></noscript>';

        $html = preg_replace(self::NOSCRIPT_REDIRECT_PATTERN, $hint, $html) ?? $html;

        return $html;
    }

    /**
     * Remove microdata attributes, but preserve everything inside the
     * JSON-LD script tags (those are JSON payloads, unaffected by the regex,
     * but we guard anyway by only touching non-script content).
     */
    private function stripMicrodata(string $html): string
    {
        $parts = preg_split(
            '/(<script[^>]*application\/ld\+json[^>]*>.*?<\/script>)/is',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        ) ?: [$html];

        foreach ($parts as $index => &$part) {
            // Odd indexes are the captured JSON-LD script blocks; skip those.
            if ($index % 2 === 1) {
                continue;
            }
            $part = preg_replace(self::MICRODATA_ATTR_PATTERN, '', $part) ?? $part;
        }
        unset($part);

        return implode('', $parts);
    }

    private function isProductPage(): bool
    {
        return $this->request->getFullActionName() === 'catalog_product_view';
    }
}
