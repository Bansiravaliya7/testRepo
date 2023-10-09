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

namespace Mirasvit\SearchElastic\SearchAdapter\Query\Builder;

use Magento\Catalog\Model\Product;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Elasticsearch\Model\Adapter\FieldMapper\Product\AttributeProvider;
use Magento\Elasticsearch\Model\Adapter\FieldMapper\Product\FieldProvider\FieldType\ResolverInterface as TypeResolver;
use Magento\Elasticsearch\Model\Adapter\FieldMapperInterface;
use Magento\Elasticsearch\Model\Config;
use Magento\Elasticsearch\SearchAdapter\Query\ValueTransformerPool;
use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;
use Mirasvit\Search\Model\ConfigProvider;
use Mirasvit\SearchElastic\SearchAdapter\QueryBuilder;

class MatchQuery extends MatchCompatibility
{
    private $queryBuilder;

    private $fieldMapper;

    private $attributeRepository;


    public function __construct(
        QueryBuilder                 $queryBuilder,
        FieldMapperInterface         $fieldMapper,
        AttributeProvider            $attributeProvider,
        TypeResolver                 $fieldTypeResolver,
        ValueTransformerPool         $valueTransformerPool,
        AttributeRepositoryInterface $attributeRepository,
        Config                       $config
    ) {
        $this->queryBuilder        = $queryBuilder;
        $this->fieldMapper         = $fieldMapper;
        $this->attributeRepository = $attributeRepository;

        parent::__construct($fieldMapper, $attributeProvider, $fieldTypeResolver, $valueTransformerPool, $config);
    }

    /**
     * @param string $conditionType
     */
    public function build(array $selectQuery, RequestQueryInterface $requestQuery, $conditionType): array
    {
        $queryValue        = $requestQuery->getValue();
        $fields            = [];

        foreach ($requestQuery->getMatches() as $match) {
            $attribute = false;
            try {
                $attribute = $this->attributeRepository->get(Product::ENTITY, $match['field']);
            } catch (\Exception $e) {
            }

            if ($attribute && in_array($attribute->getFrontendInput(), ['price', 'weight', 'date', 'datetime'])) {
                continue;
            }

            $resolvedField = $this->fieldMapper->getFieldName(
                $match['field'],
                ['type' => FieldMapperInterface::TYPE_QUERY]
            );

            if (in_array($resolvedField, ['links_purchased_separately'])) {
                continue;
            }

            if ($resolvedField === '_search') {
                $resolvedField = ConfigProvider::MISC_FIELD;
            }

            $fields[$resolvedField] = (int)($match['boost'] ?? 1);
        }

        $selectQuery = $this->queryBuilder->build($selectQuery, (string)$queryValue, $fields);

        return $selectQuery;
    }
}
