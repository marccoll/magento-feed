<?php

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
}

function getProducts($batchSize) {
    global $storeID, $currency, $baseCurrencyCode, $lastProductId, $sizeAttrNames;

    $products = Mage::getResourceModel('catalog/product_collection')->setStore($storeID);
    $products->addAttributeToFilter('status', 1);// get enabled prod
    $products->addFieldToFilter('entity_id', array('gt' => $lastProductId));
    $products->setOrder('entity_id', Varien_Data_Collection::SORT_ORDER_ASC);
    $products->setPageSize($batchSize)->load();

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
            $url = $_p->getUrlInStore();

            // use title and description from parent
            $prodData['title'] = html_entity_decode(strip_tags($_p->getName()));
            $prodData['description'] = html_entity_decode(strip_tags($_p->getDescription()));

        }else{
            $artno = intval($productId);
            $url = $product->getUrlInStore();
        }
        $prodData['artno'] = '' . $artno; // this is magento product_id
        $prodData['url'] = str_replace("feed.php/", "", $url);

        // barcodes (SKU)
        $barcode = '' . $product->getSku();
        if($barcode){ $prodData['barcodes'] = [$barcode]; }

        // price
        if(isset($currency)){
            $prodData['currency'] = $currency;
            $price = Mage::helper('tax')->getPrice($product, $product->getPrice()); // getFinalPrice do not seems to work
            $prodData['price'] = Mage::helper('directory')->currencyConvert($price, $baseCurrencyCode, $currency);
            $specialPrice = $product->getSpecialPrice();
            if ($specialPrice) {
                $fromDate = new DateTime($product->getData('special_from_date'));
                $rawToDate = $product->getData('special_to_date');
                $toDate = new DateTime($rawToDate ? $rawToDate : '3000-01-01');
                $today = new DateTime();
                if ($today->getTimestamp() >= $fromDate->getTimestamp() &&
                    $today->getTimestamp() <= $toDate->getTimestamp()){
                  $oldPrice = Mage::helper('tax')->getPrice($product, $product->getPrice());
                  $prodData['old_price'] = Mage::helper('directory')->currencyConvert($oldPrice, $baseCurrencyCode, $currency);
                  $price = Mage::helper('tax')->getPrice($product, $specialPrice);
                  $prodData['price'] = Mage::helper('directory')->currencyConvert($price, $baseCurrencyCode, $currency);
                }
            }

        }else{
            $prodData['currency'] = $baseCurrencyCode;
            $prodData['price'] = $product->getPrice();
            $specialPrice = $product->getSpecialPrice();
            if ($specialPrice) {
                $prodData['old_price'] = $product->getPrice();
                $prodData['price'] = $product->getSpecialPrice();
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
        $prodData['is_parent'] = $isParent;
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

            /* $attributes = Mage::getModel('catalog/product')->getAttributes(); */
            /* $attributeArray = array(); */
            /* foreach($attributes as $a){ */
            /*   foreach ($a->getEntityType()->getAttributeCodes() as $attributeName) { */
            /*     array_push($attributeArray, $attributeName); */
            /*   } */
            /*   break; */
            /* } */
            /* $prodData['attr'] = $attributeArray; */

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
