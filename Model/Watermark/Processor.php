<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Model\Watermark;

use Qiangvei\SeoAi\Api\WatermarkProcessorInterface;
use Qiangvei\SeoAi\Helper\Config;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Overlays a configured watermark PNG/JPEG onto a product/category image
 * using native GD and caches the result under pub/media/seoai/og/.
 *
 * Best-effort by design: any GD or filesystem failure returns the original
 * URL so Open Graph tags are never broken by image processing.
 */
class Processor implements WatermarkProcessorInterface
{
    /** Output cache sub-directory under media. */
    private const CACHE_DIR = 'seoai/og';

    private Config $config;
    private Filesystem $filesystem;
    private StoreManagerInterface $storeManager;

    /** Position => [x-ratio, y-ratio] of watermark top-left within the base image. */
    private const POSITION_ANCHORS = [
        'tl'     => [0.04, 0.04],
        'tr'     => [0.96, 0.04],
        'bl'     => [0.04, 0.96],
        'br'     => [0.96, 0.96],
        'center' => [0.50, 0.50],
    ];

    public function __construct(
        Config $config,
        Filesystem $filesystem,
        StoreManagerInterface $storeManager
    ) {
        $this->config = $config;
        $this->filesystem = $filesystem;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritdoc
     */
    public function process(string $imageUrl): string
    {
        $storeId = (string) $this->storeManager->getStore()->getId();

        if (!$this->config->isWatermarkEnabled($storeId ?: null)) {
            return $imageUrl;
        }

        $watermarkPath = $this->config->getWatermarkImage($storeId ?: null);
        if ($watermarkPath === '') {
            return $imageUrl;
        }

        try {
            $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            $mediaAbsPath = $media->getAbsolutePath();

            $sourceRel = $this->toMediaRelative($imageUrl, $mediaAbsPath);
            if ($sourceRel === null || !$media->isFile($sourceRel)) {
                return $imageUrl;
            }

            $wmRel = 'seoai/watermark/' . ltrim($watermarkPath, '/');
            if (!$media->isFile($wmRel)) {
                // The value may already be stored with its sub-path.
                $wmRel = ltrim($watermarkPath, '/');
                if (!$media->isFile($wmRel)) {
                    return $imageUrl;
                }
            }

            $cacheRel = self::CACHE_DIR . '/' . $this->cacheFilename($sourceRel, $wmRel, $storeId);

            if ($media->isFile($cacheRel)) {
                return $this->toAbsoluteUrl($cacheRel);
            }

            $baseImage = $this->loadImage($mediaAbsPath . $sourceRel);
            $watermark = $this->loadImage($mediaAbsPath . $wmRel);

            if ($baseImage === null || $watermark === null) {
                return $imageUrl;
            }

            $this->composite($baseImage, $watermark);

            $media->createDir(self::CACHE_DIR);
            if (!$this->savePng($baseImage, $mediaAbsPath . $cacheRel)) {
                return $imageUrl;
            }

            imagedestroy($baseImage);
            imagedestroy($watermark);

            return $this->toAbsoluteUrl($cacheRel);
        } catch (\Throwable $e) {
            return $imageUrl;
        }
    }

    /**
     * Convert an absolute or media URL into a path relative to the media dir.
     */
    private function toMediaRelative(string $url, string $mediaAbsPath): ?string
    {
        $path = $url;

        // Absolute URL -> strip the media base URL if present.
        try {
            $mediaBaseUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
            if ($mediaBaseUrl !== '' && str_starts_with($path, $mediaBaseUrl)) {
                $path = substr($path, strlen($mediaBaseUrl));
            }
        } catch (\Throwable $e) {
            // leave as-is
        }

        // Absolute filesystem path -> strip media absolute path.
        $normalizedMedia = str_replace('\\', '/', $mediaAbsPath);
        $normalizedPath = str_replace('\\', '/', $path);
        if (str_starts_with($normalizedPath, $normalizedMedia)) {
            $path = substr($normalizedPath, strlen($normalizedMedia));
        }

        $path = ltrim($path, '/\\');

        return $path !== '' ? str_replace('\\', '/', $path) : null;
    }

    private function cacheFilename(string $sourceRel, string $wmRel, string $storeId): string
    {
        $key = $sourceRel . '|' . $wmRel . '|' . $storeId . '|'
            . $this->config->getWatermarkOpacity($storeId) . '|'
            . $this->config->getWatermarkPosition($storeId);

        return hash('sha1', $key) . '.png';
    }

    /**
     * Load an image file into a GD resource (truecolor where possible).
     *
     * @return resource|\GdImage|null
     */
    private function loadImage(string $absolutePath)
    {
        if (!is_file($absolutePath) || !function_exists('imagecreatefromstring')) {
            return null;
        }

        $data = @file_get_contents($absolutePath);
        if ($data === false) {
            return null;
        }

        $image = @imagecreatefromstring($data);
        if ($image === false) {
            return null;
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        return $image;
    }

    /**
     * Composite the watermark onto the base image at the configured position
     * and opacity.
     *
     * @param resource|\GdImage $base
     * @param resource|\GdImage $watermark
     */
    private function composite($base, $watermark): void
    {
        $baseW = imagesx($base);
        $baseH = imagesy($base);
        $wmW = imagesx($watermark);
        $wmH = imagesy($watermark);

        // Scale watermark down if larger than a third of the base dimension.
        $maxW = (int) max(1, $baseW / 3);
        $maxH = (int) max(1, $baseH / 3);
        if ($wmW > $maxW || $wmH > $maxH) {
            $scale = min($maxW / $wmW, $maxH / $wmH);
            $newW = (int) max(1, $wmW * $scale);
            $newH = (int) max(1, $wmH * $scale);
            $resized = imagecreatetruecolor($newW, $newH);
            if ($resized !== false) {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                imagefill($resized, 0, 0, $transparent);
                if (imagecopyresampled($resized, $watermark, 0, 0, 0, 0, $newW, $newH, $wmW, $wmH)) {
                    imagedestroy($watermark);
                    $watermark = $resized;
                    $wmW = $newW;
                    $wmH = $newH;
                } else {
                    imagedestroy($resized);
                }
            }
        }

        $anchor = self::POSITION_ANCHORS[$this->config->getWatermarkPosition()] ?? self::POSITION_ANCHORS['br'];
        $x = (int) round($anchor[0] * $baseW - ($anchor[0] >= 0.5 ? $wmW : 0));
        $y = (int) round($anchor[1] * $baseH - ($anchor[1] >= 0.5 ? $wmH : 0));

        $x = (int) min(max($x, 0), max(0, $baseW - $wmW));
        $y = (int) min(max($y, 0), max(0, $baseH - $wmH));

        $opacity = $this->config->getWatermarkOpacity();
        $alpha = (int) round(127 - (127 * max(0, min(100, $opacity))) / 100);

        if (function_exists('imagecopymerge')) {
            imagecopymerge($base, $watermark, $x, $y, 0, 0, $wmW, $wmH, (int) max(0, min(100, $opacity)));
        } else {
            imagecopy($base, $watermark, $x, $y, 0, 0, $wmW, $wmH);
        }
        // Note: imagecopymerge alpha handling is best-effort; the $alpha value is
        // applied via the merge percent above for cross-version compatibility.
        unset($alpha);
    }

    /**
     * Save a GD image as PNG, returning success.
     *
     * @param resource|\GdImage $image
     */
    private function savePng($image, string $absolutePath): bool
    {
        $dir = dirname($absolutePath);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        return imagepng($image, $absolutePath, 6);
    }

    private function toAbsoluteUrl(string $mediaRelative): string
    {
        try {
            return rtrim(
                $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
                '/'
            ) . '/' . ltrim($mediaRelative, '/');
        } catch (\Throwable $e) {
            return $mediaRelative;
        }
    }
}
