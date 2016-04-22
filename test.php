<?php

require_once './config.php';

set_time_limit(0);
require_once $magePath;
umask(0);
Mage::app();

if($key == $_GET['key']){
  $countryList = Mage::getModel('directory/country')->getResourceCollection()
                            ->loadByStore(1)
                            ->toOptionArray(true);
  print_r($countryList);

  $storeID = $_GET['store'];

  $stores = Mage::app()->getStores();
  foreach ($stores as $store){
    echo $store->getName() . ' ' . $store->getStoreId();
    echo $store->getHomeUrl();
    echo '<br>';
  }

  echo '<br><br>';

  $currency_code = Mage::app()->getStore()->getCurrentCurrencyCode();
  echo $currency_code . '<br><br>';

  $products = Mage::getResourceModel('catalog/product_collection')->setStore($storeID);
  $products->addAttributeToFilter('status', 1);// get enabled prod

  $prodIds = $products->getAllIds();
  print_r($prodIds);
  echo '<br><br>';

  $product = Mage::getModel('catalog/product');

  foreach ($prodIds as $productId) {
    $product->load($productId);
    echo $product->getName() . ' ' . $product->getPrice();
    echo '<br>';

  }

  // get product parent
  $product = Mage::getModel('catalog/product')->load(329);
  echo $product->getName() . '<br>';
  $parentIds = Mage::getResourceSingleton('catalog/product_type_configurable')->getParentIdsByChild(329);
  print_r($parentIds);
  $groupedParentsIds = Mage::getResourceSingleton('catalog/product_link')
                     ->getParentIdsByChild(329, Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED);
                     print_r($groupedParentsIds);

  echo '<br><br>';

  // get isParent
  $isParent = false;
  $productId = 330;
  $_product = Mage::getModel('catalog/product')->load($productId);
  echo 'is group ' . $_product->getTypeId();
  $childConfigProducts = Mage::getResourceSingleton('catalog/product_type_configurable')
    ->getChildrenIds($productId);
  $childGroupedProducts = Mage::getResourceSingleton('catalog/product_link')
                   ->getParentIdsByChild($productId, Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED);
  if($childConfigProducts or $childGroupedProducts){
    $isParent = true;
  }
  var_dump($childConfigProducts);
  echo '<br>';
  var_dump($childGroupedProducts);
  echo '<br> isParent: ' . $isParent;

  $baseCurrencyCode = Mage::app()->getStore($storeID)->getBaseCurrencyCode();
  echo '<br><br>Base currency code: ' . $baseCurrencyCode;

}else{
  echo 'wrong key';
}
