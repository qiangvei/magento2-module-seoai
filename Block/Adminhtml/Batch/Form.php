<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Block\Adminhtml\Batch;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Provides view data for the Batch Meta Generator form: store selector
 * options and attribute set options.
 */
class Form extends Template
{
    private StoreManagerInterface $storeManager;
    private AttributeSetRepositoryInterface $attributeSetRepository;
    private EavConfig $eavConfig;
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        AttributeSetRepositoryInterface $attributeSetRepository,
        EavConfig $eavConfig,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->storeManager = $storeManager;
        $this->attributeSetRepository = $attributeSetRepository;
        $this->eavConfig = $eavConfig;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Flat store-view options for a single <select>.
     *
     * Iterates StoreInterface objects directly via StoreManagerInterface and
     * reads getName() from each, avoiding System\Store's tree-structured
     * option arrays whose group/website headers carry array "value" fields.
     *
     * @return array<int, array{value: int, label: string}>
     */
    public function getStoreOptions(): array
    {
        $options = [['value' => 0, 'label' => (string) __('All Store Views (default)')]];
        foreach ($this->storeManager->getStores() as $store) {
            if (!$store instanceof StoreInterface) {
                continue;
            }
            $name = (string) $store->getName();
            if ($name === '') {
                $name = (string) $store->getCode();
            }
            $options[] = [
                'value' => (int) $store->getId(),
                'label' => $name,
            ];
        }

        return $options;
    }

    /**
     * Catalog PRODUCT attribute sets only.
     *
     * AttributeSetRepositoryInterface::getList() takes only a SearchCriteria
     * (no entity-type argument), and without a filter it returns attribute sets
     * for every object type (customer, order, cms_page, ...), each of which has
     * its own "Default" set — that is why the list showed many "Default" rows.
     * We filter by the entity_type_id resolved for "catalog_product".
     *
     * @return array<int, array{value: int, label: string}>
     */
    public function getAttributeSetOptions(): array
    {
        $productEntityTypeId = $this->getProductEntityTypeId();

        $criteriaBuilder = $this->searchCriteriaBuilder;
        if ($productEntityTypeId !== null) {
            $criteriaBuilder->addFilter('entity_type_id', $productEntityTypeId, 'eq');
        }
        $searchCriteria = $criteriaBuilder->create();
        $result = $this->attributeSetRepository->getList($searchCriteria);

        $options = [['value' => 0, 'label' => (string) __('Any Attribute Set')]];
        $sets = [];
        foreach ($result->getItems() as $set) {
            $sets[] = $set;
        }
        // Sort by name in PHP: the repository ignores client-side sort orders,
        // and building a framework SortOrder object here just to be discarded
        // by the collection processor is not worth it.
        usort($sets, static function ($a, $b): int {
            return strcasecmp(
                (string) $a->getAttributeSetName(),
                (string) $b->getAttributeSetName()
            );
        });
        foreach ($sets as $set) {
            $options[] = [
                'value' => (int) $set->getAttributeSetId(),
                'label' => (string) $set->getAttributeSetName(),
            ];
        }

        return $options;
    }

    /**
     * Numeric entity_type_id for the catalog_product EAV entity type, or null
     * if it cannot be resolved (the caller then skips the filter rather than
     * passing a literal null that would match nothing).
     */
    private function getProductEntityTypeId(): ?int
    {
        try {
            $entityType = $this->eavConfig->getEntityType('catalog_product');
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            return null;
        }

        $id = $entityType->getId();

        return $id !== null ? (int) $id : null;
    }

    public function getFormActionUrl(): string
    {
        return $this->getUrl('seoai/batch/run');
    }
}
