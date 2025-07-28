<?php

namespace Printess\PrintessDesigner\Api;

use Psr\Log\LoggerInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable\Product\CollectionFactory;
use \Magento\Catalog\Model\ProductFactory;
use  \Magento\Framework\Webapi\Rest\Request;
use Magento\Checkout\Helper\Cart;

class FakeCartItem
{
    public $itemId = 0;

    public function getId() {
        return $this->itemId;
    }
}

class PrintessApi implements PrintessApiInterface
{
    /**
     * @var ProductRepositoryInterface
     */
    protected ProductRepositoryInterface $productRepository;
    private CollectionFactory $productCollectionFactory;
    protected ProductFactory $productFactory;
    protected Request $request;
    protected Cart $cart;

    protected $logger;

    public function __construct(
        LoggerInterface $logger,
        ProductRepositoryInterface $productRepositoryInterface,
        CollectionFactory $productCollectionFactory,
        ProductFactory $productFactory,
        Request $request,
        Cart $cart
    )
    {
        $this->productRepository = $productRepositoryInterface;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->logger = $logger;
        $this->productFactory = $productFactory;
        $this->request = $request;
        $this->cart = $cart;
    }

    /**
     * @inheritdoc
     */

    public function getProductInfo()
    {
        $response = ['success' => false];
        $product = null;
        $body = $this->request->getBodyParams();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $printessHelper = $objectManager->get('Printess\PrintessDesigner\Helper\Printess');

        $editorSettings = $printessHelper->getEditorSettings();

        try {
            if(isset($body))
            {
                if(isset($body["id"]))
                {
                    $product = $this->productFactory->create()->load($body["id"]);
                }

                if(!isset($product) && isset($body["sku"]))
                {
                    $product = $this->productRepository->get($body["sku"]);
                }
            } else {
                $id = $body["id"];

                if(!isset($id)) {
                    $id = $body;
                }

                $product = $this->productFactory->create()->load($id);
            }


            if(isset($product))
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
            }

            if(isset($product))
            {
                $uiVersion = $product->getData('printess_ui_version');

                if($uiVersion !== null && !empty($uiVersion)) {
                    $editorSettings["uiVersion"] = $uiVersion;
                }

                $info = array(
                    "id" => $product->getId(),
                    "sku" => $product->getSku(),
                    "productName" => $product->getName(),
                    "productPrice" => $product->getPrice(),
                    "quoteId" => $product->getQuoteItemId(),
                    "entityId" => $product->getData("entity_id"),
                    "addToCartLink" =>  $this->cart->getAddUrl($product),
                    "options" => array(),
                    "legalText" => $product->getData('printess_legal_text') !== null && !empty($product->getData('printess_legal_text')) ? $product->getData('printess_legal_text') : $editorSettings["legalText"],
                    'formFields' => $product->getData('printess_form_fields')
                );

                if(isset($body["itemId"])) {
                    $item = new FakeCartItem;
                    $item->itemId = $body["itemId"];

                    $info["deleteJson"] = $this->cart->getDeletePostJson($item);
                }

                foreach ($product->getOptions() as &$o) {
                    $values = $o->getValues();

                    if(!array_key_exists($o->getData("option_id"), $info["options"]))
                    {
                        $info["options"][$o->getData("option_id")] = array();
                    }

                    foreach($values as $id => &$value) {
                        $info["options"][$o->getData("option_id")][$id] = $value->getData("default_title");
                    }
                }
            }

            $info["variants"] = $printessHelper->getVariations($product->getId(), $product->getSku(), true);
            $info["editorSettings"] = $editorSettings;
            $info["priceFormat"] = $printessHelper->getPriceFormat();

            return json_encode($info);
        } catch (\Exception $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
            $this->logger->info($e->getMessage());
        }
        $returnArray = json_encode($response);
        return $returnArray;
    }
}


?>