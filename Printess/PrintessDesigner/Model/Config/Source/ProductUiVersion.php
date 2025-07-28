<?php

namespace Printess\PrintessDesigner\Model\Config\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

class ProductUiVersion extends AbstractSource
{

    /**
     * @var string
     */
    protected string $optionFactory;

    /**
     * @return array
     */
    public function getAllOptions(): array
    {

        $this->_options = [
            ['label' => 'Use global settings', 'value' => ""],
            ['label' => 'Panel Ui', 'value' => "panelui"],
            ['label' => 'Deprecated [ The "old" deprecated buyer side ui]', 'value' => "classic"]
        ];

        return $this->_options;

    }

}
