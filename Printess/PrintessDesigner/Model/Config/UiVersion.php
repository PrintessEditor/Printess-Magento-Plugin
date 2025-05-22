<?php

namespace Printess\PrintessDesigner\Model\Config;

use Magento\Framework\Data\OptionSourceInterface;

class UiVersion implements OptionSourceInterface
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
            "classic" => 'Classic [The classic "old" buyer side ui]',
            "panelUi" => 'Panel Ui'
        ];
    }
}
