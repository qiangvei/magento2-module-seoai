<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source model for the "Watermark Position" system config select field.
 *
 * Returns the five supported overlay positions consumed by
 * Qiangvei\SeoAi\Model\Watermark\Processor.
 */
class WatermarkPosition implements OptionSourceInterface
{
    public const POSITION_TL = 'tl';
    public const POSITION_TR = 'tr';
    public const POSITION_BL = 'bl';
    public const POSITION_BR = 'br';
    public const POSITION_CENTER = 'center';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::POSITION_TL, 'label' => __('Top Left')],
            ['value' => self::POSITION_TR, 'label' => __('Top Right')],
            ['value' => self::POSITION_BL, 'label' => __('Bottom Left')],
            ['value' => self::POSITION_BR, 'label' => __('Bottom Right')],
            ['value' => self::POSITION_CENTER, 'label' => __('Center')],
        ];
    }
}
