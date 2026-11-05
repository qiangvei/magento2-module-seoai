<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source model for the "Return Method" system config select field.
 *
 * Maps to the schema.org MerchantReturnPolicy.returnMethod property.
 */
class ReturnMethod implements OptionSourceInterface
{
    public const RETURN_BY_MAIL = 'https://schema.org/ReturnByMail';
    public const RETURN_AT_STORE = 'https://schema.org/ReturnAtStore';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::RETURN_BY_MAIL, 'label' => __('Return by Mail')],
            ['value' => self::RETURN_AT_STORE, 'label' => __('Return at Store')],
        ];
    }
}
