<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Controller\Adminhtml\Audit;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * Renders the SEO/AEO audit report page (read-only aggregation).
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Qiangvei_SeoAi::audit';

    private PageFactory $resultPageFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Qiangvei_SeoAi::audit');
        $resultPage->getConfig()->getTitle()->prepend(__('SEO/AEO Audit'));

        return $resultPage;
    }

    /**
     * @inheritdoc
     */
    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }
}
