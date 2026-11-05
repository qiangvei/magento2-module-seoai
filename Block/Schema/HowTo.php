<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Block\Schema;

use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Qiangvei\SeoAi\Model\FaqParser;

/**
 * HowTo schema built from the current product seoai_howto attribute.
 */
class HowTo extends JsonLd
{
    private Registry $registry;
    private FaqParser $faqParser;

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
        if (!$this->config->isHowtoSchemaEnabled()) {
            return null;
        }

        $product = $this->registry->registry('current_product');
        if (!$product instanceof Product) {
            return null;
        }

        $raw = (string) $product->getData('seoai_howto');
        if ($raw === '') {
            return null;
        }

        $steps = $this->faqParser->parseHowto($raw);
        if ($steps === []) {
            return null;
        }

        $stepEntities = [];
        $position = 1;
        foreach ($steps as $step) {
            $label = $step['name'] !== '' ? $step['name'] : 'Step ' . $position;
            $entry = [
                '@type' => 'HowToStep',
                'position' => $position,
                'name'     => $label,
                'text'     => $step['text'],
            ];
            if ($step['url'] !== '') {
                $entry['url'] = $step['url'];
            }
            $stepEntities[] = $entry;
            $position++;
        }

        $graph = [
            '@type' => 'HowTo',
            'step'  => $stepEntities,
        ];

        $name = $this->cleanText($product->getName());
        if ($name !== '') {
            $graph['name'] = $name . ' – How To';
        }

        return $graph;
    }
}
