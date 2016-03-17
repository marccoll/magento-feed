<?php

set_time_limit(0);
require_once '../app/Mage.php';
umask(0);
Mage::app();

$storeID = $_GET['store'];
$currency = $_GET['currency'];

$baseCurrencyCode = Mage::app()->getStore($storeID)->getBaseCurrencyCode(); 

try {

    //$products = Mage::getModel('catalog/product')->getCollection();
    $products = Mage::getResourceModel('catalog/product_collection')->setStore($storeID);
    $products->addAttributeToFilter('status', 1);// get enabled prod
    $prodIds = $products->getAllIds();

    $product = Mage::getModel('catalog/product');
    $prods = array();
    foreach ($prodIds as $productId) {
        $product->load($productId);

        $prodData = array();

        $prodData['title'] = $product->getName();
        $prodData['description'] = strip_tags($product->getDescription());

        // get parent product ID if exist
        $parentIds = Mage::getResourceSingleton('catalog/product_type_configurable')->getParentIdsByChild($productId);
        if($parentIds){
            $prodData['artno'] = intval($parentIds[0]);
        }else{
            $prodData['artno'] = intval($productId);
        }

        // barcodes
        $barcode = intval($product->getSku());
        if($barcode){
            $prodData['barcodes'] = [$barcode];
        }

        // url
        $productUrl = $product->getProductUrl();
        $productUrl = str_replace('/' . basename($_SERVER['PHP_SELF']), '', $productUrl); // do not included index.php in url
        $prodData['url'] = $productUrl;

        // price
        if(isset($currency)){
            $prodData['currency'] = $currency;
            $price = $product->getPrice();
            $prodData['price'] = Mage::helper('directory')->currencyConvert($price, $baseCurrencyCode, $currency); 
            if ($product->getSpecialPrice()) {
                $old_price = $product->getSpecialPrice();
                $prodData['old_price'] = Mage::helper('directory')->currencyConvert($old_price, $baseCurrencyCode, $currency); 
            }
        }else{
            $prodData['currency'] = $baseCurrencyCode;
            $prodData['price'] = $product->getPrice();
            if ($product->getSpecialPrice()) {
                $prodData['old_price'] = $product->getSpecialPrice();
            }
        }

        // brand
        $brand = $product->getResource()->getAttribute('manufacturer')->getFrontend()->getValue($product);
        if($brand != 'No'){
            $prodData['brand'] = $brand;
        }

        // images
        $backend = Mage::getResourceModel('catalog/product_attribute_backend_media');
        $attributeId = Mage::getResourceModel('eav/entity_attribute')->getIdByCode('catalog_product', 'media_gallery');
        $container = new Varien_Object(array(
            'attribute' => new Varien_Object(array('id' => $attributeId))
        ));
        $gallery = $backend->loadGallery($product, $container);

        $images = array();
        foreach ($gallery as $image) {
            array_push($images, Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $image['file']);
        }
        $prodData['image_urls'] = $images;

        // sizes
        $attr = Mage::getResourceModel('catalog/eav_attribute')->loadByCode('catalog_product','size');
        if($attr->getId()){
            $size = $product->getAttributeText('size');
            if($size){
                $prodData['sizes'] = [$size];
            }
        }
        
        // colors
        $color = $product->getAttributeText('color');
        if($color){
            $prodData['colors'] = [$color];
        }

        // stocks
        $isParent = count(Mage::getModel('catalog/product_type_configurable')->getChildrenIds($productId)[0]) > 0;
        if(!$isParent){
            $stock = round($product->getStockItem()->getQty(), 0);
            $stockData = array();
            if($color){
                if($size){
                    $stockData['colors'][$color]['sizes'][$size] = $stock;
                }else{
                    $stockData['colors'][$color] = $stock;
                }
            }else{
                if($size){
                    $stockData['sizes'][$size] = $stock;
                }else{
                    $stockData = $stock;
                }
            }
            $prodData['stocks'] = $stockData;
        }

        // categories
        $prodData['categories'] = array();

        foreach ($product->getCategoryIds() as $_categoryId) {
            $category = Mage::getModel('catalog/category')->load($_categoryId);
            array_push($prodData['categories'], $category->getName());
        }

        array_push($prods, $prodData);
    }

    echo json_encode($prods);

} catch(Exception $e) {
    die($e->getMessage());
}
?>
