<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\ViewModel\JsonLd;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Qiangvei\SeoAi\Block\Schema\JsonLd;
use Qiangvei\SeoAi\Helper\Config;

/**
 * Aggregates the JSON-LD schema blocks into a single output string.
 *
 * The individual schema Block instances are injected as layout arguments
 * (see view/frontend/layout/*.xml). renderAll() concatenates the enabled
 * blocks' script tags so a template can emit them in one place.
 */
class Graph implements ArgumentInterface
{
    private Config $config;

    /** @var array<string, JsonLd> */
    private array $blocks = [];

    public function __construct(Config $config, array $blocks = [])
    {
        $this->config = $config;

        foreach ($blocks as $key => $block) {
            if ($block instanceof JsonLd) {
                $this->blocks[(string) $key] = $block;
            }
        }
    }

    /**
     * Attach (or replace) a schema block at runtime.
     */
    public function addBlock(string $key, JsonLd $block): void
    {
        $this->blocks[$key] = $block;
    }

    /**
     * Whether a given schema group is enabled. $key is one of:
     * product, breadcrumb, organization, website_search, faq, howto.
     */
    public function isEnabled(string $key): bool
    {
        return match ($key) {
            'product'        => $this->config->isProductSchemaEnabled(),
            'breadcrumb'     => $this->config->isBreadcrumbSchemaEnabled(),
            'organization'   => $this->config->isOrganizationSchemaEnabled(),
            'website_search' => $this->config->isWebsiteSearchSchemaEnabled(),
            'faq'            => $this->config->isFaqSchemaEnabled(),
            'howto'          => $this->config->isHowtoSchemaEnabled(),
            default          => false,
        };
    }

    /**
     * Concatenate the script tags of all registered blocks whose schema is
     * enabled. $context is reserved for future page-type filtering and is
     * currently accepted for API compatibility only. Returns an empty string
     * when nothing is active.
     */
    public function renderAll(string $context = ''): string
    {
        $parts = [];
        foreach ($this->blocks as $block) {
            $tag = $block->getScriptTag();
            if ($tag !== '') {
                $parts[] = $tag;
            }
        }

        return implode("\n", $parts);
    }
}
