<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source model for the "Return Fees" system config select field.
 *
 * Maps to the schema.org MerchantReturnPolicy.returnFees property.
 */
class ReturnFees implements OptionSourceInterface
{
    public const FREE_RETURN = 'https://schema.org/FreeReturn';
    public const RETURN_SHIPPING_FEES = 'https://schema.org/ReturnShippingFees';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::FREE_RETURN, 'label' => __('Free Returns')],
            ['value' => self::RETURN_SHIPPING_FEES, 'label' => __('Customer Pays Return Shipping')],
        ];
    }
}
