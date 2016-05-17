<?php
header('Content-type: text/html; charset=UTF-8');
require_once './config.php';

set_time_limit(0);
require_once $magePath;
umask(0);
Mage::app();

$storeID = $_GET['store'];
$currency = $_GET['currency'];

$baseCurrencyCode = Mage::app()->getStore($storeID)->getBaseCurrencyCode();

if($key == $_GET['key']){

  try {

      //$products = Mage::getModel('catalog/product')->getCollection();
      $products = Mage::getResourceModel('catalog/product_collection')->setStore($storeID);
      $products->addAttributeToFilter('status', 1);// get enabled prod
      $prodIds = $products->getAllIds();

      $prods = array();

      foreach ($prodIds as $productId) {
          $product = Mage::getModel('catalog/product');
          $product->load($productId);

          $prodData = array();

          $prodData['storeId'] = $storeID;
          $prodData['title'] = $product->getName();
          $prodData['description'] = strip_tags($product->getDescription());

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
            foreach ($sizeAttrNames as $attrName) {
              $attr = Mage::getResourceModel('catalog/eav_attribute')->loadByCode('catalog_product', $attrName);
              if($attr->getId()){
                $size = $product->getAttributeText($attrName);
                if($size){
                    $prodData['sizes'] = [$size];
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

      echo json_encode($prods);

  } catch(Exception $e) {
      die($e->getMessage());
  }

}else{
  echo 'wrong key';
}


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

?>
