<?php

require_once './config.php';

set_time_limit(0);
require_once MAGE_PATH;
umask(0);
Mage::app();

if ($key != $_GET['key']){
  echo "no access";
  die;
}

require_once './product_helper.php';

// list stores (done echo before as mage will set a header)
foreach (Mage::app()->getWebsites() as $website) {

  foreach ($website->getGroups() as $group) {
    $stores = $group->getStores();
    foreach ($stores as $store) {
      $storeID = $store->getStoreId();
      $countryList = Mage::getModel('directory/country')->getResourceCollection()
        ->loadByStore($storeID)
        ->toOptionArray(true);
      $currency = $store->getCurrentCurrencyCode();

      echo 'ID: ' . $storeID . ' - Name: ' . $store->getName() . ' - <a href="./feed.php?store=' . $storeID . '&currency=' . $currency . '&key=' . $key . '">Feed</a>';
      echo ' <em>(Countries: ';
      foreach ($countryList as $k => $country) {
        echo $country['value'] . ' ';
      }
      echo ' Currency: '. $currency;
      echo ')</em><br>';
      echo '<br><br>';
    }
  }
}

$storeID = isset($_GET['store']) ? $_GET['store'] : 0;
$currency = isset($_GET['currency']) ? $_GET['currency'] : 'SEK';

// show a product
$baseCurrencyCode = Mage::app()->getStore($storeID)->getBaseCurrencyCode();
$lastProductId = 0;
$result = getProducts(2);
echo "Product:<pre>". json_encode($result[0]) .'</pre><br><br>';

// list attributes
$attributes = Mage::getModel('catalog/product')->getAttributes();
$attributeArray = array();
foreach($attributes as $a){
  foreach ($a->getEntityType()->getAttributeCodes() as $attributeName) {
    array_push($attributeArray, $attributeName);
  }
  break;
}
echo "Attributes: <pre>". json_encode($attributeArray) ."</pre>";



