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

use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\CatalogGraphQl\DataProvider\Product\LayeredNavigation\LayerBuilder;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Mirasvit\Search\Repository\IndexRepository;
use Mirasvit\Search\Model\Index\Context as IndexContext;

class Product implements ResolverInterface
{
    private $layerResolver;

    private $indexContext;

    private $layerBuilder;

    private $defaultParams
        = [
            'sort'   =>
                ['relevance' => 'DESC'],
            'filter' => [],
        ];

    public function __construct(
        LayerResolver $layerResolver,
        IndexContext  $indexContext,
        LayerBuilder  $layerBuilder
    ) {
        $this->layerResolver = $layerResolver;
        $this->indexContext  = $indexContext;
        $this->layerBuilder  = $layerBuilder;
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        foreach ($this->defaultParams as $parameter => $defaultValue) {
            if (!isset($args[$parameter])) {
                $args[$parameter] = $defaultValue;
            }
        }

        $result = $value['catalogsearch_fulltext'] ?? null;
        if (!$result) {
            return null;
        }

        $layer      = $this->layerResolver->get();
        $collection = $layer->getProductCollection();
        $collection->addAttributeToSelect('*');

        $searcher = $this->indexContext->getSearcher();
        $searcher->setInstance($result['instance']);

        if (str_contains((string)$collection->getSelect(), '`e`')) {
            $searcher->joinMatches($collection, 'e.entity_id', $args);
        } else {
            $searcher->joinMatches($collection, 'main_table.entity_id', $args);
        }

        $collection->setPageSize($args['pageSize'])
            ->setOrder($args['sort']);

        $items = [];
        foreach ($collection as $product) {
            $productData          = $product->getData();
            $productData['model'] = $product;

            $items[] = $productData;
        }

        $totalCount = $searcher->getTotal();

        return [
            ...$result,
            'items'        => $items,
            'total_count'  => $totalCount,
            'aggregations' => $this->getAggregations($context, $searcher->getAggregations()),
            'page_info'    => [
                'total_pages'  => ceil($totalCount / $args['pageSize']),
                'page_size'    => $args['pageSize'],
                'current_page' => $args['currentPage'],
            ],
        ];
    }


    private function getAggregations($context, $aggregations)
    {
        if ($aggregations) {
            $store   = $context->getExtensionAttributes()->getStore();
            $storeId = (int)$store->getId();

            return $this->layerBuilder->build($aggregations, $storeId);
        } else {
            return [];
        }
    }
}
