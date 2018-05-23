<?php

namespace B2bapp\UpdateHandler\Observer;

class ProductChangeObserver implements \Magento\Framework\Event\ObserverInterface
{
    public function detectProductChanges(\Magento\Framework\Event\Observer $observer)
    {
        /**
         * @var $product Mage_Catalog_Model_Product
         */
        $product = $observer->getEvent()->getProduct();
        try {
            $logger = \Magento\Framework\App\ObjectManager::getInstance()
                ->create('\Psr\Log\LoggerInterface');

            $productId = 0;
            if (!$product->getId()) {
                //New product
                $collectionFactory = \Magento\Framework\App\ObjectManager::getInstance()
                    ->create('\Magento\Catalog\Model\ResourceModel\Category\CollectionFactory');
                $collection = $collectionFactory
                    ->create()
                    ->addAttributeToFilter('name',$product->getName())
                    ->setPageSize(1);

                if ($collection->getSize()) {
                    $productId = $collection->getFirstItem()->getId();
                } else {
                    $logger->log(\Psr\Log\LogLevel::DEBUG, 'B2bapp_UpdateHandler - Detect Product changes: Could not get ID for '. $product->getName());
                    return;
                }
            } else {
                $productId = $product->getId();
            }
            
            $model = \Magento\Framework\App\ObjectManager::getInstance()
                ->create('B2bapp\UpdateHandler\Model\UpdatedEntities');

            $model->load($product->getSku(), 'sku');

            $action = '';
            if($observer->getEvent()->getName() == 'catalog_product_save_after') {
                $action = 'change';
            } else if($observer->getEvent()->getName() == 'catalog_product_delete_after') {
                $action = 'delete';
            }

            $model->addData([
                "entity" => 'product',
                "entity_id" => $productId,
                "action" => $action,
                "sku" => $product->getSku(),
                "updated_at" => (new \DateTime())->format(\Magento\Framework\Stdlib\DateTime::DATETIME_PHP_FORMAT),
                "status" => true
            ]);
            $model->save();
        } catch (Exception $e) {
            echo "error saving updated entities";
        }
        return $this;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $this->detectProductChanges($observer);

        return $this;
    }
}