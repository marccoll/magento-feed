<?php
require_once './config.php';

set_time_limit(0);
require_once $magePath;
umask(0);
#ini_set('memory_limit', '512M'); // possibly more by default -- look at parameters of your server (RAM)
Mage::app();

$storeID = isset($_GET['store'])?$_GET['store']:0;
$currency = $_GET['currency'];

$baseCurrencyCode = Mage::app()->getStore($storeID)->getBaseCurrencyCode();

$_filename = 'feed_prods.json';
$_pidFile = '/tmp/feed_export_products.pid';

if (file_exists($_pidFile)) {
    die('Process already running! Wait.'.PHP_EOL);
} else {
    touch($_pidFile);
}

$lastProductId = 0;
$sizeLimit = 100;

if($key == $_GET['key'])
{
    $file = new Varien_Io_File();
    $path = Mage::getBaseDir('var') . DS . 'export' . DS;
    $filename = $path . DS . $_filename;
    $file->setAllowCreateFolders(true);
    $file->open(array('path' => $path));
    $file->streamOpen($filename, 'w');
    $file->streamLock(true);

    do {
        try {
            $result = getProducts();

            $file->streamWrite(json_encode($result));
        } catch (Exception $ex) {
            unlink($_pidFile);
            die($ex->getMessage());
        }
    } while( $result !== false );

    $file->streamUnlock();
    $file->streamClose();

    if (file_exists($filename)) {
        file_put_contents($filename, str_replace('][', ',', file_get_contents($filename)));

        header('Content-Type: application/json');
        readfile($filename);
    }
}else{
    echo 'wrong key';
}

unlink($_pidFile); // the end of process






// HELPERS

function getImages($prodID){
    $p = Mage::getModel('catalog/product')->load($prodID);
    $backend = Mage::getResourceModel('catalog/product_attribute_backend_media');
    $attributeId = Mage::getResourceModel('eav/entity_attribute')->getIdByCode('catalog_product', 'media_gallery');
    $container = new Varien_Object(array(
        'attribute' => new Varien_Object(array('id' => $attributeId))
    ));
    $gallery = $backend->loadGallery($p, $container);

    $images = array();
    foreach ($gallery as $image) {
        array_push($images, Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $image['file']);
    }
    return $images;
    //return ['hola', 'images'];
}

function getProducts() {
    global $storeID, $currency, $baseCurrencyCode, $sizeLimit, $lastProductId;

    $products = Mage::getResourceModel('catalog/product_collection')->setStore($storeID);
    $products->addAttributeToFilter('status', 1);// get enabled prod
    $products->addFieldToFilter('entity_id', array('gt' => $lastProductId));
    $products->setOrder('entity_id', Varien_Data_Collection::SORT_ORDER_ASC);
    $products->setPageSize($sizeLimit)->load();

    $prodIds = $products->getLoadedIds();

    $prods = array();

    foreach ($prodIds as $productId) {
        $product = Mage::getModel('catalog/product');
        $product->load($productId);

        $lastProductId = $productId;

        $prodData = array();

        $prodData['storeId'] = $storeID;
        $prodData['title'] = html_entity_decode(strip_tags($product->getName()));
        $prodData['description'] = html_entity_decode(strip_tags($product->getDescription()));

        // get parent product ID if exist and get url of parent
        $parentIds = Mage::getResourceSingleton('catalog/product_type_configurable')->getParentIdsByChild($productId);
        $groupedParentsIds = Mage::getResourceSingleton('catalog/product_link')
            ->getParentIdsByChild($productId, Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED);
        if($parentIds){
            $artno = intval($parentIds[0]);
            $url =  Mage::getModel('catalog/product')->load($artno)->getUrlInStore();
        }else if($groupedParentsIds){
            $artno = intval($groupedParentsIds[0]);
            $_p = Mage::getModel('catalog/product')->load($artno);
            $url =  $_p->getUrlInStore();
            $prodData['title'] = $_p->getName();
        }else{
            $artno = intval($productId);
            $url = $product->getUrlInStore();
        }
        $prodData['artno'] = '' . $artno;
        $prodData['url'] = $url;


        // barcodes
        $barcode = '' . intval($product->getSku());
        if($barcode){
            $prodData['barcodes'] = [$barcode];
        }

        // price
        if(isset($currency)){
            $prodData['currency'] = $currency;
            $price = Mage::helper('tax')->getPrice($product, $product->getFinalPrice());
            $prodData['price'] = Mage::helper('directory')->currencyConvert($price, $baseCurrencyCode, $currency);
            if ($product->getSpecialPrice()) {
                $prodData['old_price'] = $prodData['price'];
                $price = Mage::helper('tax')->getPrice($product, $product->getSpecialPrice());
                $prodData['price'] = Mage::helper('directory')->currencyConvert($price, $baseCurrencyCode, $currency);
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
        $images = getImages($productId);
        if(count($images) == 0){
            $images = getImages($artno);
        }
        $prodData['image_urls'] = $images;

        // don't collect some data if is a configurable parent product
        $isParent = false;
        $childConfigProducts = Mage::getResourceSingleton('catalog/product_type_configurable')
            ->getChildrenIds($productId);
        if(count($childConfigProducts[0]) > 1){
            $isParent = true;
        }

        if(!$isParent){
            // sizes
            if (isset($sizeAttrNames) && is_array($sizeAttrNames)){
                foreach ($sizeAttrNames as $attrName) {
                    $attr = Mage::getResourceModel('catalog/eav_attribute')->loadByCode('catalog_product', $attrName);
                    if($attr->getId()){
                        $size = $product->getAttributeText($attrName);
                        if($size){
                            $prodData['sizes'] = [$size];
                        }
                    }
                }
            }

            // colors
            $color = $product->getAttributeText('color');
            if($color){
                $prodData['colors'] = [$color];
            }

            // stocks
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

        // don't push grouped parent prods
        if($product->getTypeId() != 'grouped'){
            array_push($prods, $prodData);
        }
    }

    return (is_array($prods) && !empty($prods) ? $prods : false);
}
