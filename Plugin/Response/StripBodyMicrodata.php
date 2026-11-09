<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Plugin\Response;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Layout;
use Qiangvei\SeoAi\Helper\Config as SeoConfig;

/**
 * Strips legacy HTML microdata from the full rendered page body.
 *
 * Why this exists
 * ---------------
 * Magento_Review's native templates (product/view/summary.phtml and the review
 * list templates) emit HTML microdata such as:
 *
 *   <div class="product-reviews-summary" itemprop="aggregateRating" itemscope
 *        itemtype="http://schema.org/AggregateRating">
 *     <span itemprop="ratingValue">100</span>...
 *     <span itemprop="reviewCount">1</span>
 *   </div>
 *
 * That node is missing the Google-required "itemReviewed" field, so Google
 * crawls it as a second, INVALID structured-data source and reports an error in
 * Rich Results Test — even though this module's <head> JSON-LD is complete and
 * correct. Because the JSON-LD is authoritative, the fix is to remove the
 * redundant body microdata so Google only ever sees the JSON-LD.
 *
 * Why hook Layout::getOutput()
 * ----------------------------
 * Layout::getOutput() is public, returns the assembled page-body HTML string,
 * and is the source of the content that Full Page Cache stores. Hooking here
 * therefore works whether the request is a fresh render OR a cache hit, and it
 * sees the whole <body> (unlike the Head::toHtml SanitizeHead plugin, which
 * only sees <head> and so misses body microdata).
 *
 * Safety
 * ------
 * - Guarded by the schema/strip_body_microdata config flag (default ON).
 * - JSON-LD <script> blocks and <style> blocks are preserved verbatim.
 * - Only microdata *attributes* are removed; the visible star-rating HTML and
 *   its CSS classes are untouched, so the storefront looks identical.
 */
class StripBodyMicrodata
{
    /** Matches itemprop / itemscope / itemtype attributes. */
    private const MICRODATA_ATTR_PATTERN = '/\s+item(?:prop|scope|type)\s*(?:=\s*(?:"[^"]*"|\'[^\']*\'))?/i';

    /** Captures JSON-LD <script> blocks so their contents are preserved. */
    private const JSONLD_SCRIPT_PATTERN = '/(<script[^>]*application\/ld\+json[^>]*>.*?<\/script>)/is';

    /** Captures generic <script> and <style> blocks to skip. */
    private const SKIP_TAG_PATTERN = '/(<script\b[^>]*>.*?<\/script>|<style\b[^>]*>.*?<\/style>)/is';

    /** Full-action names whose body should be sanitised. */
    private const TARGET_ACTIONS = [
        'catalog_product_view',
        'catalog_category_view',
    ];

    private SeoConfig $config;

    private RequestInterface $request;

    public function __construct(SeoConfig $config, RequestInterface $request)
    {
        $this->config = $config;
        $this->request = $request;
    }

    /**
     * Post-process the assembled page body HTML.
     *
     * @param Layout $subject
     * @param string $result
     * @return string
     */
    public function afterGetOutput(Layout $subject, string $result): string
    {
        if ($result === '' || !$this->config->isSeoEnabled()) {
            return $result;
        }

        if (!$this->config->isStripBodyMicrodata()) {
            return $result;
        }

        $action = $this->request->getFullActionName();
        if (!in_array($action, self::TARGET_ACTIONS, true)) {
            return $result;
        }

        return $this->stripMicrodata($result);
    }

    /**
     * Remove microdata attributes from everything outside script/style and
     * JSON-LD blocks.
     *
     * Uses an extract → strip → restore approach instead of one big
     * preg_split alternation: protected blocks (JSON-LD payloads, <script>,
     * <style>) are lifted out into placeholders first, microdata attributes are
     * then stripped from the remaining markup, and finally the blocks are put
     * back byte-for-byte. This avoids fragile combined-pattern splitting.
     */
    private function stripMicrodata(string $html): string
    {
        // 1. Lift protected blocks out into placeholders.
        $protected = [];
        $stashed = preg_replace_callback(
            self::JSONLD_SCRIPT_PATTERN,
            static function (array $m) use (&$protected): string {
                $token = "\u{E000}SEOAI" . count($protected) . "\u{E001}";
                $protected[$token] = $m[0];

                return $token;
            },
            $html
        );
        if ($stashed === null) {
            return $html;
        }

        $stashed = preg_replace_callback(
            self::SKIP_TAG_PATTERN,
            static function (array $m) use (&$protected): string {
                $token = "\u{E000}SEOAI" . count($protected) . "\u{E001}";
                $protected[$token] = $m[0];

                return $token;
            },
            $stashed
        );
        if ($stashed === null) {
            return $html;
        }

        // 2. Strip microdata attributes from the remaining markup.
        $stashed = preg_replace(self::MICRODATA_ATTR_PATTERN, '', $stashed) ?? $stashed;

        // 3. Restore the protected blocks.
        return str_replace(array_keys($protected), array_values($protected), $stashed);
    }
}
