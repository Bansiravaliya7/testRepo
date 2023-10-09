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

namespace Mirasvit\SearchElastic\Plugin;

use Magento\Elasticsearch\Elasticsearch5\SearchAdapter\Mapper as Mapper;
use Magento\Elasticsearch\Elasticsearch5\SearchAdapter\Query\Builder as QueryBuilder;
use Magento\Elasticsearch\SearchAdapter\Filter\Builder as FilterBuilder;
use Magento\Framework\Search\RequestInterface;
use Mirasvit\Search\Repository\ScoreRuleRepository;
use Mirasvit\SearchElastic\Plugin\PutScoreBoostBeforeAddDocsPlugin as ScoreBoostProcessor;
use Mirasvit\SearchElastic\SearchAdapter\Query\Builder\MatchCompatibility as MatchQueryBuilder;

/**
 * @see \Magento\Elasticsearch\Elasticsearch5\SearchAdapter\Mapper::buildQuery()
 */
class ElasticsearchAddScriptToSearchQueryPlugin extends Mapper
{
    protected $queryBuilder;

    protected $matchQueryBuilder;

    protected $filterBuilder;

    protected $scoreRuleRepository;

    public function __construct(
        QueryBuilder        $queryBuilder,
        MatchQueryBuilder   $matchQueryBuilder,
        FilterBuilder       $filterBuilder,
        ScoreRuleRepository $scoreRuleRepository
    ) {
        $this->scoreRuleRepository = $scoreRuleRepository;
        parent::__construct($queryBuilder, $matchQueryBuilder, $filterBuilder);
    }

    public function aroundBuildQuery(Mapper $subject, callable $proceed, RequestInterface $request)
    {
        $searchQuery = $proceed($request);

        if ($request->getQuery()->getName() == 'quick_search_container'
            && $this->scoreRuleRepository->getCollection()->getSize() > 0
            && $this->isSortByRelevance($request)
        ) {
            $searchQuery['body']['query']['script_score']['query']  = $searchQuery['body']['query'];
            $searchQuery['body']['query']['script_score']['script'] = [
                'source' => '10000 + _score * doc[\'' . ScoreBoostProcessor::MULTIPLY_ATTRIBUTE . '\'].value' .
                    ' + doc[\'' . ScoreBoostProcessor::SUM_ATTRIBUTE . '\'].value',
            ];

            unset($searchQuery['body']['query']['bool']);
        }

        // change minimum_should_match only for search requests
        if ($request->getQuery()->getName() !== 'catalog_view_container'
            && $request->getQuery()->getName() !== 'advanced_search_container') {
            if (isset($searchQuery['body'])
                && isset($searchQuery['body']['query'])
                && isset($searchQuery['body']['query']['bool'])
                && isset($searchQuery['body']['query']['bool']['minimum_should_match'])) {
                $searchQuery['body']['query']['bool']['minimum_should_match'] = 0;
            }

            if (isset($searchQuery['body'])
                && isset($searchQuery['body']['query'])
                && isset($searchQuery['body']['query']['script_score'])
                && isset($searchQuery['body']['query']['script_score']['query'])
                && isset($searchQuery['body']['query']['script_score']['query']['bool'])
                && isset($searchQuery['body']['query']['script_score']['query']['bool']['minimum_should_match'])) {
                $searchQuery['body']['query']['script_score']['query']['bool']['minimum_should_match'] = 0;
            }
        }

        return $searchQuery;
    }

    private function isSortByRelevance(RequestInterface $request): bool
    {
        foreach ($request->getSort() as $sort) {
            if ($sort['field'] == 'relevance') {
                return true;
            }
        }

        return false;
    }
}
