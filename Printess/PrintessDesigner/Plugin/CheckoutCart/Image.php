<?php

namespace Printess\PrintessDesigner\Plugin\CheckoutCart;

use Magento\Checkout\Block\Cart\Item\Renderer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;

class Image
{
    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;
    /**
     * @var Json|mixed
     */
    protected $serializer;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Json|null $serializer
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ?Json $serializer = null
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->serializer = $serializer ?: ObjectManager::getInstance()->get(Json::class);
    }

    /**
     * @param Renderer $subject
     * @param $result
     * @return mixed
     */
    public function afterGetImage(Renderer $subject, $result)
    {
        $item = $subject->getItem();

        if ($additionalOptions = $item->getOptionByCode('additional_options')) {
            $additionalOptions = (array) $this->serializer->unserialize($additionalOptions->getValue());
        }

        if (isset($additionalOptions['printess_thumbnail_url']['value'])) {
            $result->setImageUrl($additionalOptions['printess_thumbnail_url']['value']);
        }

        return $result;
    }
}
