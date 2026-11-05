<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Block\Schema;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Serialize\Serializer\Json;
use Qiangvei\SeoAi\Helper\Config;

/**
 * Base block for JSON-LD structured data output.
 *
 * Subclasses implement getGraph() and return a schema.org node (or null to
 * suppress output). getScriptTag() wraps the graph in a @context/@graph
 * envelope, escapes `<` to prevent HTML injection and returns a ready to
 * print <script type="application/ld+json"> tag.
 */
abstract class JsonLd extends Template
{
    protected Json $serializer;
    protected Config $config;

    public function __construct(
        Context $context,
        Json $serializer,
        Config $config,
        array $data = []
    ) {
        $this->serializer = $serializer;
        $this->config = $config;
        parent::__construct($context, $data);
    }

    /**
     * Build the schema.org node for this block.
     *
     * Return null to suppress the script tag entirely.
     */
    abstract protected function getGraph(): ?array;

    /**
     * Render the JSON-LD script tag. Empty string when graph is null.
     */
    public function getScriptTag(): string
    {
        $graph = $this->getGraph();
        if ($graph === null) {
            return '';
        }

        $payload = [
            '@context' => 'https://schema.org',
            '@graph'   => [$graph],
        ];

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            return '';
        }

        // Escape `<` and `>` so a stray "</script>" or "<" inside text
        // content cannot break out of the script element.
        $json = str_replace(
            ['<', '>'],
            ['\u003c', '\u003e'],
            $json
        );

        return '<script type="application/ld+json">' . $json . '</script>';
    }

    /**
     * Current URL of the store.
     */
    protected function getStoreBaseUrl(): string
    {
        return rtrim((string) $this->_storeManager->getStore()->getBaseUrl(), '/') . '/';
    }

    /**
     * Strip tags and normalise whitespace for use in schema text fields.
     */
    protected function cleanText(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        $text = html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return trim($text);
    }
}
