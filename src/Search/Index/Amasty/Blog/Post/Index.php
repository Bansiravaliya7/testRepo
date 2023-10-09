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



namespace Mirasvit\Search\Index\Amasty\Blog\Post;

use Magento\Framework\Data\Collection;
use Magento\Framework\DB\Select;
use Mirasvit\Search\Model\Index\AbstractIndex;

class Index extends AbstractIndex
{
    public function getName(): string
    {
        return 'Amasty / Blog';
    }

    public function getIdentifier(): string
    {
        return 'amasty_blog_post';
    }

    public function getAttributes(): array
    {
        return [
            'title'         => __('Title'),
            'short_content' => __('Short Content'),
            'full_content'  => __('Full Content'),
        ];
    }

    public function getPrimaryKey(): string
    {
        return 'post_id';
    }

    public function buildSearchCollection(): Collection
    {
        $collectionFactory = $this->context->getObjectManager()
            ->get('Amasty\Blog\Model\ResourceModel\Posts\CollectionFactory');

        /** @var \Amasty\Blog\Model\ResourceModel\Posts\Collection $collection */
        $collection = $collectionFactory->create();
        $collection->addStoreWithDefault(0);
        $collection->addFilterByStatus([2]);

        $this->context->getSearcher()->joinMatches($collection, 'main_table.post_id');

        return $collection;
    }

    public function getIndexableDocuments(int $storeId, array $entityIds = [], int $lastEntityId = 0, int $limit = 100): array
    {
        $collectionFactory = $this->context->getObjectManager()
            ->get('Amasty\Blog\Model\ResourceModel\Posts\CollectionFactory');

        /** @var \Amasty\Blog\Model\ResourceModel\Posts\Collection $collection */
        $collection = $collectionFactory->create();

        $collection->addStoreFilter([0, $storeId]);

        if ($entityIds) {
            $collection->addFieldToFilter('post_id', ['in' => $entityIds]);
        }

        $collection->addFieldToFilter('main_table.post_id', ['gt' => $lastEntityId])
            ->setPageSize($limit)
            ->setOrder('post_id', Select::SQL_ASC);

        return $collection->toArray()['items'];
    }
}
