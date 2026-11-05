<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Api;

/**
 * Generates an llms.txt (or llms-full.txt) document describing the store.
 */
interface LlmsGeneratorInterface
{
    /**
     * @param bool $full When true, include full product descriptions (llms-full.txt).
     * @return string
     */
    public function generate(bool $full): string;
}
