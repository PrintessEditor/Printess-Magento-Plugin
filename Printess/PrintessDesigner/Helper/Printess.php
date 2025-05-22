<?php

namespace Printess\PrintessDesigner\Helper;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\ConfigurableProduct\Api\LinkManagementInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable\Product\CollectionFactory;
use \Magento\Catalog\Model\ProductFactory;
use \Magento\Store\Api\Data\StoreInterface;
use \Magento\Framework\App\ScopeResolver;
use \Magento\Framework\Locale\Bundle\DataBundle;

class Printess extends AbstractHelper
{

    public const XML_PATH_DESIGNER_SHOP_TOKEN = "designer/api_token/shop_token";
    public const XML_PATH_DESIGNER_SHOW_STARTUP_ANIMATION = "designer/api_token/show_animation";
    public const XML_PATH_DESIGNER_STARTUP_LOGO = "designer/api_token/startup_logo_url";
    public const XML_PATH_DESIGNER_EMBEDDED = "designer/startup/embedded";
    public const XML_PATH_DESIGNER_API_URL = "designer/urls/api_url";
    public const XML_PATH_DESIGNER_EDITOR_URL = "designer/urls/editor_url";
    public const XML_PATH_DESIGNER_EDITOR_VERSION = "designer/urls/editor_version";
    public const XML_PATH_DESIGNER_LEGAL_TEXT = "designer/frontend_settings/legal_price_notice";
    public const XML_PATH_DESIGNER_UI_VERSION = "designer/frontend_settings/ui_version";

    /**
     * @var ProductRepositoryInterface
     */
    protected ProductRepositoryInterface $productRepository;

    /**
     * @var LinkManagementInterface
     */
    protected LinkManagementInterface $linkManagement;

    protected ProductFactory $productFactory;

    private CollectionFactory $productCollectionFactory;

    private StoreInterface $store;

    private ScopeResolver $scopeResolver;

    private static $defaultNumberSet = 'latn';

    /**
     * @param Context $context
     * @param ProductRepositoryInterface $productRepositoryInterface
     * @param LinkManagementInterface $linkManagement
     */
    public function __construct(
        Context $context,
        ProductRepositoryInterface $productRepositoryInterface,
        LinkManagementInterface $linkManagement,
        CollectionFactory $productCollectionFactory,
        ProductFactory $productFactory,
        StoreInterface $store,
        ScopeResolver $scopeResolver
    ) {
        $this->productRepository = $productRepositoryInterface;
        $this->linkManagement = $linkManagement;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productFactory = $productFactory;
        $this->store = $store;
        $this->scopeResolver = $scopeResolver;

        parent::__construct($context);
    }

    public function getOptions($product) {
        $optionsBySku = array();
        $typeInstance = $product->getTypeInstance();

        if(!method_exists($typeInstance, "getConfigurableOptions"))
        {
            return array();
        }

        $data = $product->getTypeInstance()->getConfigurableOptions($product);

        foreach($data as $key => &$value) {
            foreach($value as &$option) {
                if(!array_key_exists($option["sku"], $optionsBySku))
                {
                    $optionsBySku[$option["sku"]] = array();
                }

                $optionsBySku[$option["sku"]][] = array("valueIndex" => $option["value_index"], "attributeCode" => $option["attribute_code"], "label" => $option["super_attribute_label"], "defaultTitle" => $option["default_title"], "optionTitle" => $option["option_title"], "optionId" => $key);
            }
        }

        return $optionsBySku;
    }

    /**
     * @param \Magento\Catalog\Model\Product $parentProduct
     * @param \Magento\Catalog\Model\Product $simpleProduct
     * @return string Hashed Url
     */
    public function getVariantUrl($parentProduct, $simpleProduct) {
        $configType = $parentProduct->getTypeInstance();
        $attributes = $configType->getConfigurableAttributesAsArray($parentProduct);
        $options = [];

        if(!isset($simpleProduct)) {
            return $parentProduct->getProductUrl();
        }

        foreach ($attributes as $attribute) {
            $id = $attribute['attribute_id'];
            $value = $simpleProduct->getData($attribute['attribute_code']);
            $options[$id] = $value;
        }

        $options = http_build_query($options);
        return $parentProduct->getProductUrl().($options ? '#' . $options : '');
    }

    public function getVariations($productId, $sku, bool $assoziative = false): array
    {
        $product = null;

        if(isset($productId)) {
            $product = $this->productFactory->create()->load($productId);
        }

        if(!isset($product) && isset($sku))
        {
            $product = $this->productRepository->get($sku);
        }

        if (!is_null($sku))
        {
            $parentProduct = $product->getParent($product);
            
            if(!isset($parentProduct)) {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $parentIds = $objectManager->create('Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable')->getParentIdsByChild($product->getId());
                
                if(count($parentIds) > 0) {
                    $parentProduct = $this->productRepository->getById($parentIds[0]);
                }
            }

            if(isset($parentProduct)) {
                $product = $parentProduct;
            }

            if($assoziative)
            {
                $variants = array();
                $options = $this->getOptions($product);
                $children = $this->productCollectionFactory->create()->setFlag('product_children', true)->setProductFilter($product);
    
                foreach ($children as $child) {
                    $childProduct = $this->productRepository->getById($child->getId());

                    $newVariant = array('id' => $child->getId(),
                    'product_id' => $product->getId(),
                    'sku' => $child->getSku(),
                    'name' => $childProduct->getName(),
                    'price' => $childProduct->getPrice());

                    if(array_key_exists($child->getSku(), $options)) {
                        $newVariant["options"] = $options[$child->getSku()];
                    }
    
                    $variants[] = $newVariant;
                }
                
                if(count($variants) == 0) {
                    $dummy = $product->getTypeId();
                    $variants[] = [
                        'id' => $product->getId(),
                        'product_id' => $product->getId(),
                        'sku' => $product->getSku(),
                        'name' => $product->getName(),
                        'price' => $product->getPrice(),
                        'isDefault' => true
                    ];
                }

                return $variants;
            }
            else
            {
                return $usedProducts;
            }
        }
        else
        {
            return array();
        }
    }

    public function getData($sku, $keys): array
    {
        if(!is_array($keys))
        {
            return $this->getData($sku, array($keys));
        }

        $ret = array();

        if (!is_null($sku)) {
            $product = $this->productRepository->get($sku);
            $parentProduct = $product->getParent($product);
            
            if(!isset($parentProduct)) {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $parentIds = $objectManager->create('Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable')->getParentIdsByChild($product->getId());
                
                if(count($parentIds) > 0) {
                    $parentProduct = $this->productRepository->getById($parentIds[0]);
                }
            }

            if(isset($parentProduct)) {
                $product = $parentProduct;
            }

            foreach ($keys as $key) {
                $ret[$key] = $product->getData($key);
            }
        }

        return $ret;
    }

    /**
     * @param $sku
     * @return bool
     * @throws NoSuchEntityException
     */
    public function hasTemplate($sku = null): bool
    {
        if (!is_null($sku)) {
            $product = $this->productRepository->get($sku);

            $printessTemplate = $product->getData('printess_template');
            if ($printessTemplate) {
                return true;
            }

            if ($product->getTypeId() === 'configurable') {
                $children = $this->linkManagement->getChildren($product->getSku());

                foreach ($children as $child) {
                    $childProduct = $this->productRepository->get($child->getSku());

                    $printessTemplate = $childProduct->getData('printess_template');

                    if ($printessTemplate) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function denullify($value, $defaultValue)
    {
        return isset($value) && $value != "" ? $value : $defaultValue;
    }

    public function getEditorSettings(): array
    {
        $settings = array();

        $storeScope = ScopeInterface::SCOPE_STORE;
        $settings["shopToken"] = $this->denullify($this->scopeConfig->getValue(self::XML_PATH_DESIGNER_SHOP_TOKEN, $storeScope), "");
        $settings["showStartupAnimation"] = $this->scopeConfig->getValue(self::XML_PATH_DESIGNER_SHOW_STARTUP_ANIMATION, $storeScope) === 1 || $this->denullify($this->scopeConfig->getValue(self::XML_PATH_DESIGNER_SHOW_STARTUP_ANIMATION, $storeScope), "1") === "1" || $this->denullify($this->scopeConfig->getValue(self::XML_PATH_DESIGNER_SHOW_STARTUP_ANIMATION, $storeScope), "true") === "true" || $this->scopeConfig->getValue(self::XML_PATH_DESIGNER_SHOW_STARTUP_ANIMATION, $storeScope) === true;
        $settings["customLogoUrl"] = $this->denullify($this->scopeConfig->getValue(self::XML_PATH_DESIGNER_STARTUP_LOGO, $storeScope), "");
        $settings["embedEditor"] = $this->scopeConfig->getValue(self::XML_PATH_DESIGNER_EMBEDDED, $storeScope) === true || $this->denullify($this->scopeConfig->getValue(self::XML_PATH_DESIGNER_EMBEDDED, $storeScope), "true") === "true";
        $settings["editorVersion"] = $this->denullify($this->scopeConfig->getValue(self::XML_PATH_DESIGNER_EDITOR_VERSION, $storeScope), "");
        $settings["editorUrl"] = $this->denullify($this->scopeConfig->getValue(self::XML_PATH_DESIGNER_EDITOR_URL, $storeScope), "https://editor.printess.com");
        $settings["apiUrl"] = $this->denullify($this->scopeConfig->getValue(self::XML_PATH_DESIGNER_API_URL, $storeScope), "https://api.printess.com");
        $settings["legalText"] = $this->denullify($this->scopeConfig->getValue(self::XML_PATH_DESIGNER_LEGAL_TEXT, $storeScope), "");
        $settings["uiVersion"] = $this->denullify($this->scopeConfig->getValue(self::XML_PATH_DESIGNER_UI_VERSION, $storeScope), "");

        return $settings;
    }

    function getEmailThumbnailUrl($item)
    {
        $printessThumbnailUrl = null;

        if($item && method_exists($item, "getData")) {
            $productOptions = $item->getData("product_options");

            if(isset($productOptions["additional_options"]) && isset($productOptions["additional_options"]["printess_thumbnail_url"]))
            {
                if(isset($productOptions["additional_options"]["printess_thumbnail_url"]["value"]))
                {
                    $printessThumbnailUrl = $productOptions["additional_options"]["printess_thumbnail_url"]["value"];
                }
            }
        }

        if(!isset($printessThumbnailUrl) || $printessThumbnailUrl == "")
        {
            $_order = $item->getOrder();
            $_store = $_order->getStore();
            $_imageHelper = \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Catalog\Helper\Image');
            $_baseImageUrl = $_store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product';

            $printessThumbnailUrl = $_imageHelper->init($item->getProduct(), 'small_image', ['type'=>'small_image'])->keepAspectRatio(true)->resize('65','65')->getUrl();
        }

        return $printessThumbnailUrl;
    }

    public function getPriceFormat($localeCode = null)
    {
        $localeCode = $localeCode ?: $this->store->getLocaleCode() ?: "en-US";

        $currency = $this->store->getCurrentCurrency();

        $localeData = (new DataBundle())->get($localeCode);
        $defaultSet = $localeData['NumberElements']['default'] ?: self::$defaultNumberSet;
        $format = $localeData['NumberElements'][$defaultSet]['patterns']['currencyFormat'] ?: ($localeData['NumberElements'][self::$defaultNumberSet]['patterns']['currencyFormat'] ?: explode(';', $localeData['NumberPatterns'][1])[0]);

        $decimalSymbol = $localeData['NumberElements'][$defaultSet]['symbols']['decimal'] ?: ($localeData['NumberElements'][self::$defaultNumberSet]['symbols']['decimal'] ?: $localeData['NumberElements'][0]);

        $groupSymbol = $localeData['NumberElements'][$defaultSet]['symbols']['group'] ?: ($localeData['NumberElements'][self::$defaultNumberSet]['symbols']['group'] ?: $localeData['NumberElements'][1]);

        $pos = strpos($format, ';');

        if ($pos !== false) {
            $format = substr($format, 0, $pos);
        }

        $format = preg_replace("/[^0\#\.,]/", "", $format);
        $totalPrecision = 0;
        $decimalPoint = strpos($format, '.');

        if ($decimalPoint !== false) {
            $totalPrecision = strlen($format) - (strrpos($format, '.') + 1);
        } else {
            $decimalPoint = strlen($format);
        }

        $requiredPrecision = $totalPrecision;
        $t = substr($format, $decimalPoint);
        $pos = strpos($t, '#');

        if ($pos !== false) {
            $requiredPrecision = strlen($t) - $pos - $totalPrecision;
        }

        if (strrpos($format, ',') !== false) {
            $group = $decimalPoint - strrpos($format, ',') - 1;
        } else {
            $group = strrpos($format, '.');
        }

        $integerRequired = strpos($format, '.') - strpos($format, '0');

        $result = [
            'pattern' => $currency->getOutputFormat(),
            'precision' => $totalPrecision,
            'requiredPrecision' => $requiredPrecision,
            'decimalSymbol' => $decimalSymbol,
            'groupSymbol' => $groupSymbol,
            'groupLength' => $group,
            'integerRequired' => $integerRequired,
        ];

        return $result;
    }
}
