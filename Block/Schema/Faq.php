<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Block\Schema;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Qiangvei\SeoAi\Model\FaqParser;

/**
 * FAQPage schema built from the current product/category seoai_faq attribute.
 *
 * Exposes getFaqItems() for the visible FAQ HTML block as well.
 */
class Faq extends JsonLd
{
    private Registry $registry;
    private FaqParser $faqParser;

    /** @var array<int, array{q:string,a:string}>|null */
    private ?array $items = null;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Serialize\Serializer\Json $serializer,
        \Qiangvei\SeoAi\Helper\Config $config,
        Registry $registry,
        FaqParser $faqParser,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->faqParser = $faqParser;
        parent::__construct($context, $serializer, $config, $data);
    }

    protected function getGraph(): ?array
    {
        if (!$this->config->isFaqSchemaEnabled()) {
            return null;
        }

        $items = $this->getFaqItems();
        if ($items === []) {
            return null;
        }

        $entities = [];
        foreach ($items as $item) {
            if ($item['q'] === '' || $item['a'] === '') {
                continue;
            }
            $entities[] = [
                '@type'          => 'Question',
                'name'           => $item['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $item['a'],
                ],
            ];
        }

        if ($entities === []) {
            return null;
        }

        return [
            '@type'      => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }

    /**
     * Parsed FAQ entries for the current product or category.
     *
     * @return array<int, array{q:string,a:string}>
     */
    public function getFaqItems(): array
    {
        if ($this->items !== null) {
            return $this->items;
        }

        $raw = $this->getRawFaqJson();
        $this->items = $raw !== null
            ? $this->faqParser->parseFaq($raw)
            : [];

        return $this->items;
    }

    private function getRawFaqJson(): ?string
    {
        $product = $this->registry->registry('current_product');
        if ($product instanceof Product) {
            $value = (string) $product->getData('seoai_faq');
            return $value !== '' ? $value : null;
        }

        $category = $this->registry->registry('current_category');
        if ($category instanceof Category) {
            $value = (string) $category->getData('seoai_faq');
            return $value !== '' ? $value : null;
        }

        return null;
    }
}
