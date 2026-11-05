<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Controller\Adminhtml\Batch;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Qiangvei\SeoAi\Model\Meta\BatchGenerator;

/**
 * POST handler for the Batch Meta Generator form.
 *
 * Accepts store scope, optional product-id list, attribute-set and visibility
 * filters, and an overwrite flag; delegates to BatchGenerator and redirects
 * back with a session message.
 */
class Run extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Qiangvei_SeoAi::batch';

    private BatchGenerator $batchGenerator;
    private ProductCollectionFactory $productCollectionFactory;

    // Note: $resultRedirectFactory is already declared (untyped, protected) on
    // the parent AbstractAction and populated by Context during construction,
    // so it must NOT be redeclared here (a typed redeclare is a fatal error).

    public function __construct(
        Context $context,
        BatchGenerator $batchGenerator,
        ProductCollectionFactory $productCollectionFactory
    ) {
        parent::__construct($context);
        $this->batchGenerator = $batchGenerator;
        $this->productCollectionFactory = $productCollectionFactory;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $request = $this->getRequest();

        if (!$request->isPost()) {
            $this->messageManager->addErrorMessage(__('Invalid request method.'));

            return $this->resultRedirectFactory->create()->setPath('seoai/batch/index');
        }

        $storeId = (int) $request->getParam('store_id', 0);
        $overwrite = (bool) $request->getParam('overwrite', false);
        $rawIds = (string) $request->getParam('product_ids', '');
        $attributeSetId = (int) $request->getParam('attribute_set_id', 0);

        $productIds = $this->parseProductIds($rawIds);

        // Resolve filters to product IDs when none supplied explicitly.
        if ($productIds === [] && ($attributeSetId > 0 || $storeId > 0)) {
            $productIds = $this->resolveIdsFromFilters($attributeSetId, $storeId);
        }

        if ($productIds === []) {
            $this->messageManager->addWarningMessage(__('No products selected for meta generation.'));

            return $this->resultRedirectFactory->create()->setPath('seoai/batch/index');
        }

        try {
            $count = $this->batchGenerator->generateForProducts($productIds, $storeId, $overwrite);

            $this->messageManager->addSuccessMessage(
                __('Meta fields generated for %1 product(s).', $count)
            );
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(
                __('Batch generation failed: %1', $e->getMessage())
            );
        }

        return $this->resultRedirectFactory->create()->setPath('seoai/batch/index');
    }

    /**
     * @param string $raw Comma/newline-separated product IDs.
     * @return int[]
     */
    private function parseProductIds(string $raw): array
    {
        $parts = preg_split('/[\s,;]+/', trim($raw)) ?: [];
        $ids = array_map('intval', $parts);

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    /**
     * @return int[]
     */
    private function resolveIdsFromFilters(int $attributeSetId, int $storeId): array
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addStoreFilter($storeId > 0 ? $storeId : 0);

        if ($attributeSetId > 0) {
            $collection->addAttributeToFilter('attribute_set_id', $attributeSetId);
        }

        $collection->addFieldToSelect('entity_id');

        return array_map('intval', $collection->getAllIds());
    }

    /**
     * @inheritdoc
     */
    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }
}
