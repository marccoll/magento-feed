<?php 

set_time_limit(0);
require_once '../app/Mage.php';
umask(0);
Mage::app();

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

//$currency_code = Mage::app()->getStore()->getCurrentCurrencyCode(); 
//echo $currency_code . '<br><br>';

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
