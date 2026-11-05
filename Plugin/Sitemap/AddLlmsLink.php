<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Plugin\Sitemap;

use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sitemap\Controller\Index\Index;
use Magento\Store\Model\StoreManagerInterface;
use Qiangvei\SeoAi\Helper\Config as SeoConfig;

/**
 * Appends a reference to the /seoai/llms/index endpoint to the sitemap
 * response when the "Add /llms.txt Reference" config flag is enabled.
 *
 * Simplified by design: Magento 2.4's sitemap index controller returns a
 * JSON ResultInterface, so appending a literal XML <sitemap> entry would
 * corrupt the payload. This plugin therefore only annotates responses that
 * carry an XML (or raw text) body, appending an XML comment pointing at the
 * llms.txt endpoint. JSON responses are left untouched. This keeps the
 * injected reference valid wherever it is actually emitted.
 */
class AddLlmsLink
{
    private SeoConfig $config;
    private StoreManagerInterface $storeManager;

    public function __construct(SeoConfig $config, StoreManagerInterface $storeManager)
    {
        $this->config = $config;
        $this->storeManager = $storeManager;
    }

    /**
     * After plugin for the sitemap index controller execute() method.
     *
     * @param Index $subject
     * @param ResultInterface|ResponseInterface $result
     * @return ResultInterface|ResponseInterface
     */
    public function afterExecute(Index $subject, $result)
    {
        if (!$this->config->isSeoEnabled() || !$this->config->isLlmsLinkInRobots()) {
            return $result;
        }

        $body = null;
        $response = null;

        if ($result instanceof Raw) {
            $body = (string) $result->getContents();
            $response = null;
        } elseif ($result instanceof HttpResponse) {
            $response = $result;
            $body = (string) $result->getBody();
        }

        if ($body === null || !$this->looksLikeXml($body)) {
            return $result;
        }

        $base = $this->baseUrl();
        $comment = sprintf(
            "\n<!-- AI-friendly site index: %s -->",
            htmlspecialchars($base . 'seoai/llms/index', ENT_QUOTES, 'UTF-8')
        );

        $newBody = $body . $comment;

        if ($response instanceof HttpResponse) {
            $response->setBody($newBody);
        } elseif ($result instanceof Raw) {
            $result->setContents($newBody);
        }

        return $result;
    }

    private function looksLikeXml(string $body): bool
    {
        $trimmed = ltrim($body);

        return str_starts_with($trimmed, '<?xml')
            || str_starts_with($trimmed, '<urlset')
            || str_starts_with($trimmed, '<sitemapindex');
    }

    private function baseUrl(): string
    {
        try {
            return rtrim(
                $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB),
                '/'
            ) . '/';
        } catch (\Throwable $e) {
            return '/';
        }
    }
}
