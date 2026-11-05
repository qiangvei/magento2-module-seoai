<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Api;

/**
 * Processes a product/category image URL into an Open Graph image,
 * optionally overlaying a configured watermark.
 *
 * Implementations must be resilient: on any failure they return the
 * original URL unchanged so that OG tags are never broken.
 *
 * @api
 */
interface WatermarkProcessorInterface
{
    /**
     * Return a (possibly cached) watermarked image URL suitable for use in
     * an og:image meta tag, or the original URL if watermarking is disabled
     * or fails.
     *
     * @param string $imageUrl Absolute or media-relative image URL.
     * @return string Absolute image URL.
     */
    public function process(string $imageUrl): string;
}
