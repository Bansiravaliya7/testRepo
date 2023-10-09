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

namespace Mirasvit\SearchGraphQl\Model\Resolver\Magento\Catalog;

use Magento\CatalogGraphQl\Model\AttributesJoiner;
use Magento\CatalogGraphQl\Model\Category\Hydrator;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class Category implements ResolverInterface
{
    private $hydrator;

    private $attributesJoiner;

    public function __construct(
        Hydrator         $hydrator,
        AttributesJoiner $attributesJoiner
    ) {
        $this->hydrator         = $hydrator;
        $this->attributesJoiner = $attributesJoiner;
    }

    public function resolve(
        Field       $field,
                    $context,
        ResolveInfo $info,
        array       $value = null,
        array       $args = null
    ) {
        $result = $value[$field->getName()] ?? null;
        if (!$result) {
            return null;
        }

        /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $collection */
        $collection = $result['instance']->getSearchCollection();
        $collection->setPageSize($args['pageSize'])
            ->setCurPage($args['currentPage']);

        $this->attributesJoiner->join($info->fieldNodes[0], $collection, $info);

        $items = [];

        foreach ($collection as $category) {
            $items[] = $this->hydrator->hydrateCategory($category);
        }

        $totalCount = $collection->getSize();

        return [
            ...$result,
            'items'       => $items,
            'total_count' => $totalCount,
            'page_info'   => [
                'total_pages'  => ceil($totalCount / $args['pageSize']),
                'page_size'    => $args['pageSize'],
                'current_page' => $args['currentPage'],
            ],
        ];
    }
}
