<?php

require_once '../app/Mage.php';

foreach (Mage::app()->getWebsites() as $website) {
  foreach ($website->getGroups() as $group) {
    $stores = $group->getStores();
    foreach ($stores as $store) {
      $storeID = $store->getStoreId();
      $countryList = Mage::getModel('directory/country')->getResourceCollection()
                            ->loadByStore($storeID)
                            ->toOptionArray(true);
      $currency = $store->getCurrentCurrencyCode();
      echo 'ID: ' . $storeID . ' - Name: ' . $store->getName() . ' - <a href="./feed.php?store=' . $storeID . '&currency=' . $currency . '">Feed</a> <br>';
      echo 'Countries: ';
      foreach ($countryList as $key => $country) {
        echo $country['value'] . ' ';
      }
      echo '<br>';
      echo 'Currency: '. $currency;
      echo '<br><br>';
    }
  }
}
