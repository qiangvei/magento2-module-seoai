<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Controller\Llms;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;
use Qiangvei\SeoAi\Api\LlmsGeneratorInterface;
use Qiangvei\SeoAi\Helper\Config;

/**
 * Serves the summary llms.txt document at /seoai/llms/index.
 */
class Index extends Action
{
    private const CACHE_MAX_AGE = 3600;

    protected Config $config;

    protected LlmsGeneratorInterface $generator;

    protected RawFactory $rawFactory;

    public function __construct(
        Context $context,
        Config $config,
        LlmsGeneratorInterface $generator,
        RawFactory $rawFactory
    ) {
        parent::__construct($context);
        $this->config = $config;
        $this->generator = $generator;
        $this->rawFactory = $rawFactory;
    }

    /**
     * @inheritdoc
     */
    public function execute(): ResultInterface
    {
        /** @var Raw $result */
        $result = $this->rawFactory->create();
        $result->setHeader('Content-Type', 'text/plain; charset=UTF-8', true);

        if (!$this->config->isLlmsEnabled()) {
            $result->setHttpResponseCode(404);
            $result->setContents("404 Not Found\n");

            return $result;
        }

        $result->setContents($this->generator->generate(false));
        // Short-lived public cache: keeps AI crawlers polite while staying easy to
        // refresh (config re-save / FPC flush invalidates it).
        $result->setHeader('Cache-Control', 'public, max-age=' . self::CACHE_MAX_AGE, true);
        $result->setHeader('X-Robots-Tag', 'noindex', true);

        return $result;
    }
}
