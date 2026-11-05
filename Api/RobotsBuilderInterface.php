<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Api;

/**
 * Builds the complete robots.txt content for the current store.
 *
 * Combines AI-crawler directives derived from the module configuration
 * with conventional SEO rules and, optionally, an llms.txt pointer.
 */
interface RobotsBuilderInterface
{
    /**
     * Render the full robots.txt body.
     *
     * @return string
     */
    public function build(): string;
}
