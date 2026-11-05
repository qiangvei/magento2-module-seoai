<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Observer;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Qiangvei\SeoAi\Helper\Config;

/**
 * Reroutes catalog product view requests carrying ?format=md to the Markdown controller.
 *
 * Bound to controller_action_predispatch_catalog_product_view. When the markdown
 * view is enabled and the request asks for it, the standard catalog product page
 * dispatch is halted and the router re-dispatches to seoai/product/markdown,
 * which returns the product as text/markdown.
 */
class RedirectMarkdown implements ObserverInterface
{
    private Config $config;

    private RequestInterface $request;

    private ActionFlag $actionFlag;

    public function __construct(
        Config $config,
        RequestInterface $request,
        ActionFlag $actionFlag
    ) {
        $this->config = $config;
        $this->request = $request;
        $this->actionFlag = $actionFlag;
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        if (!$this->config->isMarkdownEnabled()) {
            return;
        }

        if (strtolower((string) $this->request->getParam('format')) !== 'md') {
            return;
        }

        /** @var ActionInterface $controllerAction */
        $controllerAction = $observer->getEvent()->getData('controller_action');
        if ($controllerAction === null) {
            return;
        }

        // Preserve the product identifier for the Markdown controller, which
        // accepts `id` from the request or falls back to the registry product.
        $id = $this->request->getParam('id');
        if ($id === null) {
            $id = $this->request->getParam('product_id');
        }

        // Halt the original catalog/product/view action...
        $this->actionFlag->set('', ActionFlag::FLAG_NO_DISPATCH, true);
        // ...and point the router at seoai/product/markdown for this request.
        $this->request->setModuleName('SeoAi')
            ->setControllerName('product')
            ->setActionName('markdown')
            ->setDispatched(false);

        if ($id !== null) {
            $this->request->setParam('id', $id);
        }
    }
}
