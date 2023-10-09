<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-search-ultimate
 * @version   2.2.19
 * @copyright Copyright (C) 2023 Mirasvit (https://mirasvit.com/)
 */



namespace Mirasvit\Search\Index\Magento\Catalog\Product\InstantProvider;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Framework\View\LayoutInterface;
use Mirasvit\Search\Model\ConfigProvider;
use Mirasvit\Search\Service\MapperService;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Tax\Model\Config as TaxConfig;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Framework\Pricing\Render;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Mapper
{
    const IN_STOCK = 2;

    const OUT_OF_STOCK = 1;

    const UNSET_STOCK = 0;

    private $attributeIds = [];

    private $pkField      = '';

    private $resource;

    private $configProvider;

    private $mapperService;

    private $imageHelper;

    private $pricingHelper;

    private $taxHelper;

    private $catalogHelper;

    private $layout;

    private $priceRender;

    private $productCollectionFactory;

    public function __construct(
        ResourceConnection       $resource,
        ConfigProvider           $configProvider,
        MapperService            $mapperService,
        ImageHelper              $imageHelper,
        PricingHelper            $pricingHelper,
        TaxHelper                $taxHelper,
        CatalogHelper            $catalogHelper,
        LayoutInterface          $layout,
        ProductCollectionFactory $productCollectionFactory
    ) {
        $this->resource                 = $resource;
        $this->configProvider           = $configProvider;
        $this->mapperService            = $mapperService;
        $this->imageHelper              = $imageHelper;
        $this->pricingHelper            = $pricingHelper;
        $this->taxHelper                = $taxHelper;
        $this->catalogHelper            = $catalogHelper;
        $this->layout                   = $layout;
        $this->productCollectionFactory = $productCollectionFactory;
    }

    public function mapProductSku(int $storeId, array $productIds): array
    {
        $data = $this->resource->getConnection()->fetchPairs(
            $this->resource->getConnection()
                ->select()
                ->from([$this->resource->getTableName('catalog_product_entity')], ['entity_id', 'sku'])
                ->where('entity_id IN(?)', $productIds)
        );

        $map = [];
        foreach ($productIds as $productId) {
            $map[$productId] = '';
        }

        foreach ($data as $productId => $sku) {
            $map[$productId] = $this->mapperService->clearString((string)$sku);
        }

        return $map;
    }

    public function mapProductName(int $storeId, array $productIds): array
    {
        $data = $this->attributeSelectQuery($storeId, $productIds, 'name', 'varchar');

        $map = [];
        foreach ($productIds as $productId) {
            $map[$productId] = '';
        }

        foreach ($data as $productId => $name) {
            $map[$productId] = $this->mapperService->clearString((string)$name);
        }

        return $map;
    }

    public function mapProductUrl(int $storeId, array $productIds): array
    {
        $data = $this->resource->getConnection()->fetchPairs(
            $this->resource->getConnection()
                ->select()
                ->from([$this->resource->getTableName('url_rewrite')], ['entity_id', 'request_path'])
                ->where('entity_id IN(?)', $productIds)
                ->where('entity_type = ? ', ProductUrlRewriteGenerator::ENTITY_TYPE)
                ->where('store_id IN(?)', [$storeId])
                ->where('redirect_type = 0')
                ->group('entity_id')
        );

        $map = [];
        foreach ($productIds as $productId) {
            $map[$productId] = $this->mapperService->getBaseUrl($storeId) . 'catalog/product/view/id/' . $productId . '/';
        }

        foreach ($data as $productId => $requestPath) {
            $map[$productId] = $this->mapperService->getBaseUrl($storeId) . $requestPath;
        }

        return $map;
    }

    public function mapProductImage(int $storeId, array $productIds): array
    {
        $emulation = ObjectManager::getInstance()->get('\Magento\Store\Model\App\Emulation');
        $emulation->startEnvironmentEmulation($storeId, 'frontend', true);

        $data = $this->attributeSelectQuery($storeId, $productIds, 'thumbnail', 'varchar');

        $placeholder = $this->imageHelper->getDefaultPlaceholderUrl('thumbnail');

        $map = [];
        foreach ($productIds as $productId) {
            $map[$productId] = $placeholder;
        }

        foreach ($data as $productId => $path) {
            if ($path == '' || $path == 'no_selection') {
                continue;
            }

            $image = (string)$this->imageHelper->init(null, 'upsell_products_list')
                ->setImageFile($path)
                ->getUrl();

            if ($image == '') {
                $image = $placeholder;
            }

            $map[$productId] = $image;
        }

        $emulation->stopEnvironmentEmulation();

        return $map;
    }

    public function mapProductPrice(int $storeId, array $productIds): array
    {
        if ($this->configProvider->getPriceFetchStrategy() == 'magento') {
            return $this->mapProductPriceV0($storeId, $productIds);
        }

        $priceDisplayType = $this->taxHelper->getPriceDisplayType($storeId);

        $data = $this->resource->getConnection()->fetchAll(
            $this->resource->getConnection()
                ->select()
                ->from(['cpip' => $this->resource->getTableName('catalog_product_index_price')], ['*'])
                ->join(['s' => $this->resource->getTableName('store')], 's.website_id = cpip.website_id', [])
                ->where('entity_id IN(?)', $productIds)
                ->where('s.store_id = ?', $storeId)
                ->group('entity_id')
        );

        $map = [];
        foreach ($productIds as $productId) {
            $map[$productId] = '';
        }

        $taxClassIds = $this->attributeSelectQuery($storeId, $productIds, 'tax_class_id', 'int');

        foreach ($data as $item) {
            $productId = $item['entity_id'];

            $price = 0;
            if ($item['max_price'] != 0) {
                $price = $item['max_price'];
            }
            if ($item['min_price'] != 0) {
                $price = $item['min_price'];
            }
            if ($item['final_price'] != 0) {
                $price = $item['final_price'];
            }

            if ($item['min_price'] != 0
                && $item['final_price'] != 0
                && $item['min_price'] < $item['final_price']) {
                $price = $item['min_price'];
            }

            if ($price <= 0) {
                continue;
            }

            if ($priceDisplayType === TaxConfig::DISPLAY_TYPE_INCLUDING_TAX) {
                $product = new \Magento\Framework\DataObject([
                    'tax_class_id' => $taxClassIds[$productId],
                ]);

                $price = $this->catalogHelper->getTaxPrice($product, $price, true, null, null, null, $storeId, null, true);
            }

            try {
                $map[$productId] = $this->pricingHelper->currencyByStore($price, $storeId, true, false);
            } catch (\Exception $e) {
                $map[$productId] = (string)$price;
            }
        }

        return $map;
    }

    public function mapProductPriceV0(int $storeId, array $productIds): array
    {
        $map = [];
        foreach ($productIds as $productId) {
            $map[$productId] = '';
        }

        $productCollection = $this->productCollectionFactory->create()
            ->setStoreId($storeId)
            ->addAttributeToSelect('*')
            ->addFieldToFilter('entity_id', ['in' => [0, ...$productIds]])
            ->addStoreFilter($storeId);

        foreach ($productCollection as $product) {
            $price = '';
            if ($this->getPriceRenderer()) {
                $emulation = ObjectManager::getInstance()->get('\Magento\Store\Model\App\Emulation');

                $emulation->startEnvironmentEmulation($storeId, 'frontend', true);
                $price = $this->getPriceRenderer()->render(
                    FinalPrice::PRICE_CODE,
                    $product,
                    [
                        'include_container'     => true,
                        'display_minimal_price' => true,
                        'zone'                  => Render::ZONE_ITEM_LIST,
                        'list_category_page'    => true,
                    ]
                );
                $emulation->stopEnvironmentEmulation();
            }

            $map[(int)$product->getId()] = $price;
        }

        return $map;
    }

    private function getPriceRenderer(): \Magento\Framework\Pricing\Render
    {
        if ($this->priceRender) {
            return $this->priceRender;
        }

        $this->priceRender = $this->layout->getBlock('product.price.render.default');

        if (!$this->priceRender) {
            $this->priceRender = $this->layout->createBlock(
                \Magento\Framework\Pricing\Render::class,
                'product.price.render.default',
                ['data' => ['price_render_handle' => 'catalog_product_prices']]
            );
        }

        $this->priceRender->setData('is_product_list', true);

        return $this->priceRender;
    }

    public function mapProductDescription(int $storeId, array $productIds): array
    {
        $descriptionData = $this->attributeSelectQuery($storeId, $productIds, 'description', 'text');

        $shortDescriptionData = $this->attributeSelectQuery($storeId, $productIds, 'short_description', 'text');

        $map = [];
        foreach ($productIds as $productId) {
            $map[$productId] = '';

            if (isset($descriptionData[$productId]) && $descriptionData[$productId] != '') {
                $map[$productId] = $this->mapperService->clearString(
                    (string)$descriptionData[$productId]
                );
            } elseif (isset($shortDescriptionData[$productId]) && $shortDescriptionData[$productId] != '') {
                $map[$productId] = $this->mapperService->clearString(
                    (string)$shortDescriptionData[$productId]
                );
            }
        }

        return $map;
    }

    public function mapProductRating(int $storeId, array $productIds): array
    {
        $data = $this->resource->getConnection()->fetchPairs(
            $this->resource->getConnection()->select()->from(
                [$this->resource->getTableName('review_entity_summary')],
                [
                    'entity_pk_value',
                    'value' => new \Zend_Db_Expr('AVG(rating_summary)'),
                ]
            )->where('entity_pk_value IN (?)', $productIds)
                ->where('store_id = ?', $storeId)
                ->group('entity_pk_value')
        );

        $map = [];
        foreach ($productIds as $productId) {
            $map[$productId] = 0;
        }

        foreach ($data as $productId => $rating) {
            $map[$productId] = $rating;
        }

        return $map;
    }

    public function mapProductReviews(int $storeId, array $productIds): array
    {
        $data = $this->resource->getConnection()->fetchPairs(
            $this->resource->getConnection()->select()->from(
                [$this->resource->getTableName('review')],
                [
                    'entity_pk_value',
                    'value' => new \Zend_Db_Expr('COUNT(review_id)'),
                ]
            )->where('status_id=1')
                ->where('entity_pk_value IN (?)', $productIds)
                ->group('entity_pk_value')
        );

        $map = [];
        foreach ($productIds as $productId) {
            $map[$productId] = 0;
        }

        foreach ($data as $productId => $reviews) {
            $map[$productId] = $reviews;
        }

        return $map;
    }

    public function mapProductCart(int $storeId, array $productIds): array
    {
        $map = [];
        foreach ($productIds as $productId) {
            $map[$productId] = $this->mapperService->getBaseUrl($storeId) . 'searchautocomplete/cart/add/id/' . $productId;
        }

        $stockMap = $this->mapProductStockStatus($storeId, $productIds);

        foreach ($stockMap as $productId => $stockStatus) {
            if ($stockStatus == self::OUT_OF_STOCK) {
                $map[$productId] = '';
            }
        }

        return $map;
    }

    public function mapProductStockStatus(int $storeId, array $productIds): array
    {
        $map = [];
        foreach ($productIds as $productId) {
            $map[$productId] = self::UNSET_STOCK;
        }

        $data = $this->resource->getConnection()->fetchPairs(
            $this->resource->getConnection()->select()
                ->from(
                    ['e' => $this->resource->getTableName('catalog_product_entity')],
                    ['entity_id']
                )->joinInner(
                    ['stock' => $this->resource->getTableName('cataloginventory_stock_status')],
                    'stock.product_id = e.entity_id',
                    ['stock_status']
                )
                ->where('e.entity_id IN (?)', $productIds)
                ->group('e.entity_id')
        );

        foreach ($data as $productId => $stockStatus) {
            $map[$productId] = $stockStatus == 1 ? self::IN_STOCK : self::OUT_OF_STOCK;
        }

        if ($this->resource->getConnection()->isTableExists($this->resource->getTableName('inventory_source_item'))) {
            $data = $this->resource->getConnection()->fetchPairs(
                $this->resource->getConnection()->select()
                    ->from(
                        ['e' => $this->resource->getTableName('catalog_product_entity')],
                        ['entity_id']
                    )->joinInner(
                        ['isi' => $this->resource->getTableName('inventory_source_item')],
                        'isi.sku = e.sku',
                        ['status' => 'MAX(status)']
                    )
                    ->where('e.entity_id IN (?)', $productIds)
                    ->group('e.entity_id')
            );

            foreach ($data as $productId => $stockStatus) {
                if ($map[$productId] == self::OUT_OF_STOCK) {
                    $map[$productId] = $stockStatus == 1 ? self::IN_STOCK : self::OUT_OF_STOCK;
                }
            }
        }

        return $map;
    }

    private function attributeSelectQuery(int $storeId, array $productIds, string $attribute, string $type): array
    {
        $map = [];
        foreach ($productIds as $productId) {
            $map[$productId] = '';
        }

        $mainTable = 'catalog_product_entity_' . $type;

        foreach ([$storeId, 0, 1] as $sid) {
            $query = $this->resource->getConnection()
                ->select()
                ->from(['ev' => $this->resource->getTableName($mainTable)], ['entity_id', 'value'])
                ->where('ev.attribute_id = ?', $this->getAttributeId($attribute))
                ->where('ev.store_id = ?', $sid)
                ->where('ev.entity_id IN(?)', $productIds)
                ->order('ev.store_id')
                ->group('ev.entity_id');

            if ($this->getPkField() == 'row_id') {
                $query = $this->resource->getConnection()
                    ->select()
                    ->from(['e' => $this->resource->getTableName('catalog_product_entity')], ['entity_id'])
                    ->joinLeft(['ev' => $this->resource->getTableName($mainTable)], 'e.row_id = ev.row_id', ['value'])
                    ->where('ev.attribute_id = ?', $this->getAttributeId($attribute))
                    ->where('ev.store_id = ?', $sid)
                    ->where('e.entity_id IN(?)', $productIds)
                    ->order('ev.store_id')
                    ->group('e.entity_id');
            }

            $data = $this->resource->getConnection()->fetchPairs($query);

            foreach ($data as $productId => $value) {
                if ($map[$productId] == '' || $map[$productId] == 'no_selection') {
                    $map[$productId] = $value;
                }
            }
        }

        return $map;
    }

    private function getAttributeId(string $attributeCode): int
    {
        if (count($this->attributeIds) == 0) {
            $this->attributeIds = $this->resource->getConnection()->fetchPairs(
                $this->resource->getConnection()->select()
                    ->from(['ea' => $this->resource->getTableName('eav_attribute')], ['attribute_code', 'attribute_id'])
                    ->joinLeft(['eet' => $this->resource->getTableName('eav_entity_type')], 'eet.entity_type_id = ea.entity_type_id', [])
                    ->where('eet.entity_type_code = ?', 'catalog_product')
            );
        }

        return (int)$this->attributeIds[$attributeCode];
    }

    private function getPkField(): string
    {
        if ($this->pkField == '') {
            $this->pkField = (string)$this->resource->getConnection()->getAutoIncrementField($this->resource->getTableName('catalog_product_entity'));
        }

        return $this->pkField;
    }
}
