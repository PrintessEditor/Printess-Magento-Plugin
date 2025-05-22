<?php

namespace Printess\PrintessDesigner\Model\Config\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

class UiVersion extends AbstractSource
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
            ['label' => 'Classic [ The classic "old" buyer side ui]', 'value' => ""],
            ['label' => 'Panel Ui', 'value' => "panelUi"]
        ];

        return $this->_options;

    }

}
