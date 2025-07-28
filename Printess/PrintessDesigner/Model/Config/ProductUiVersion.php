<?php

namespace Printess\PrintessDesigner\Model\Config;

use Magento\Framework\Data\OptionSourceInterface;

class ProductUiVersion implements OptionSourceInterface
{

    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        $arr = $this->toArray();
        $ret = [];
        foreach ($arr as $key => $value) {
            $ret[] = [
                'value' => $key,
                'label' => $value
            ];
        }
        return $ret;
    }

    /**
     * @return string[]
     */
    public function toArray(): array
    {
        return [
            "" => 'Use global settings',
            "panelUi" => 'Panel Ui',
            "classic" => 'Deprecated [The "old" deprecated buyer side ui]',
        ];
    }
}
