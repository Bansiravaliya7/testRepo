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


declare(strict_types=1);

namespace Mirasvit\Search\Index\Magento\Catalog\Category;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\Search\Index\AbstractInstantProvider;
use Mirasvit\Search\Service\IndexService;
use Mirasvit\Search\Service\MapperService;

class InstantProvider extends AbstractInstantProvider
{
    private $resource;

    private $categoryFactory;

    private $storeManager;

    private $mapperService;

    public function __construct(
        ResourceConnection    $resource,
        CategoryFactory       $categoryFactory,
        StoreManagerInterface $storeManager,
        IndexService          $indexService,
        MapperService         $mapperService
    ) {
        $this->resource        = $resource;
        $this->categoryFactory = $categoryFactory;
        $this->storeManager    = $storeManager;
        $this->mapperService   = $mapperService;

        parent::__construct($indexService);
    }

    public function getItems(int $storeId, int $limit, int $page = 0): array
    {
        $items = [];

        /** @var \Magento\Catalog\Model\Category $category */
        foreach ($this->getCollection($limit) as $category) {
            $items[] = $this->mapItem($category, $storeId);
        }

        return $items;
    }

    public function getSize(int $storeId): int
    {
        return $this->getCollection(0)->getSize();
    }

    public function map(array $documentData, int $storeId): array
    {
        foreach ($documentData as $entityId => $itm) {
            $entity = ObjectManager::getInstance()->create('\Magento\Catalog\Model\Category')
                ->load($entityId);

            $map = $this->mapItem($entity, $storeId);

            $documentData[$entityId]['_instant'] = $map;
        }

        return $documentData;
    }

    private function getUrlRewritePath(int $storeId, int $categoryId): string
    {
        $data = $this->resource->getConnection()->fetchOne(
            $this->resource->getConnection()
                ->select()
                ->from([$this->resource->getTableName('url_rewrite')], ['request_path'])
                ->where('entity_id = ?', $categoryId)
                ->where('entity_type = ? ', CategoryUrlRewriteGenerator::ENTITY_TYPE)
                ->where('store_id IN(?)', [0, $storeId])
                ->where('redirect_type = 0')
                ->group('entity_id')
        );

        return (string)$data;
    }

    /**
     * @param \Magento\Catalog\Model\Category $category
     * @param int                             $storeId
     *
     * @return array
     */
    private function mapItem($category, int $storeId): array
    {
        $category = $this->categoryFactory->create()->setStoreId($storeId)
            ->load($category->getId());


        return [
            'name' => $this->getFullPath($category, $storeId),
            'url'  => $this->mapperService->getBaseUrl($storeId).$this->getUrlRewritePath($storeId, (int)$category->getId()),
        ];
    }

    private function getFullPath(CategoryInterface $category, int $storeId): string
    {
        $store  = $this->storeManager->getStore($storeId);
        $rootId = $store->getRootCategoryId();

        $result = [
            $category->getName(),
        ];

        do {
            if (!$category->getParentId()) {
                break;
            }
            $category = $this->categoryFactory->create()->setStoreId($storeId)->load($category->getParentId());

            if (!$category->getIsActive() && $category->getId() != $rootId) {
                break;
            }

            if ($category->getId() != $rootId) {
                $result[] = $category->getName();
            }
        } while ($category->getId() != $rootId);

        $result = array_reverse($result);

        return implode('<i>›</i>', $result);
    }
}
