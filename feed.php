<?php
require_once './config.php';

set_time_limit(0);
require_once MAGE_PATH;
umask(0);
#ini_set('memory_limit', '512M'); // possibly more by default -- look at parameters of your server (RAM)
Mage::app();

require_once './product_helper.php';

$storeID  = isset($_GET['store']) ? $_GET['store'] : 0;
$currency = $_GET['currency'];

$baseCurrencyCode = Mage::app()->getStore($storeID)->getBaseCurrencyCode();

$_filename = 'feed_prods.json';
$_pidFile = '/tmp/feed_export_products.pid';
if ($_GET['force'] == 'true') unlink($_pidFile);

if (file_exists($_pidFile)) {
    die('Process already running! Wait.'.PHP_EOL);
} else {
    touch($_pidFile);
}

$lastProductId = 0;
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
            // batch load 100 products at a time
            $result = getProducts(100);
            if ($result !== false) {
              $file->streamWrite(json_encode($result));
            }
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
