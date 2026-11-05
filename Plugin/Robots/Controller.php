<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Plugin\Robots;

use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Robots\Controller\Index\Index;
use Qiangvei\SeoAi\Api\RobotsBuilderInterface;
use Qiangvei\SeoAi\Helper\Config as SeoConfig;

/**
 * Serves /robots.txt from the module's RobotsBuilder when enabled.
 *
 * Magento handles /robots.txt through Magento\Robots\Controller\Router, which
 * matches the "robots.txt" path and dispatches Magento\Robots\Controller\Index\Index.
 * That controller's execute() takes no arguments and returns a
 * \Magento\Framework\View\Result\Page (handle "robots_index_index") that renders
 * Magento\Robots\Block\Data from the stock DataProvider.
 *
 * This around plugin therefore returns a Raw result with our generated content
 * when the module is enabled, fully replacing the stock page render. When the
 * module is disabled it calls $proceed() to preserve Magento's default behaviour.
 */
class Controller
{
    private SeoConfig $config;
    private RobotsBuilderInterface $robotsBuilder;
    private ResultFactory $resultFactory;

    public function __construct(
        SeoConfig $config,
        RobotsBuilderInterface $robotsBuilder,
        ResultFactory $resultFactory
    ) {
        $this->config = $config;
        $this->robotsBuilder = $robotsBuilder;
        $this->resultFactory = $resultFactory;
    }

    /**
     * Around plugin for Magento\Robots\Controller\Index\Index::execute().
     *
     * @param Index    $subject
     * @param callable $proceed
     * @return Raw|\Magento\Framework\View\Result\Page
     */
    public function aroundExecute(Index $subject, callable $proceed)
    {
        if (!$this->config->isRobotsEnabled()) {
            return $proceed();
        }

        /** @var Raw $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $result->setHeader('Content-Type', 'text/plain; charset=UTF-8');
        $result->setContents($this->robotsBuilder->build());

        return $result;
    }
}
