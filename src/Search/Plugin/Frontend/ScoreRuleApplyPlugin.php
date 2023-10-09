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



namespace Mirasvit\Search\Plugin\Frontend;

use Magento\Framework\Search\AdapterInterface;
use Magento\Framework\Search\Request;
use Magento\Framework\Search\Response\QueryResponse;
use Mirasvit\Search\Service\ScoreRuleService;

/**
 * @see \Magento\Framework\Search\AdapterInterface::query()
 */
class ScoreRuleApplyPlugin
{
    private $scoreRuleService;

    public function __construct(
        ScoreRuleService $scoreRuleService
    ) {
        $this->scoreRuleService = $scoreRuleService;
    }

    public function beforeQuery($subject, Request $request)
    {
        return [$request];

        // magento 2.4.5
        if ($request->getName() != 'quick_search_container') {
            return [$request];
        }

        if (!$this->isSortByRelevance($request)) {
            return [$request];
        }

        $r = new Request(
            $request->getName(),
            $request->getIndex(),
            $request->getQuery(),
            $request->getFrom(),
            $request->getSize(), //redeclare size to correctly apply search weights
            $request->getDimensions(),
            $request->getAggregation(),
            $request->getSort()
        );

        return [$r];
    }

    public function afterQuery(AdapterInterface $subject, QueryResponse $response, Request $request): QueryResponse
    {
        /** @var QueryResponse $response */
        if (empty($request->getQuery()->getShould())) {
            return $response;
        }

        if (!$this->isSortByRelevance($request)) {
            return $response;
        }

        $originalScores = [];
        foreach ($response->getIterator() as $item) {
            $originalScores[$item->getId()] = $item->getCustomAttribute('score')->getValue();
        }

        $results = $this->scoreRuleService->applyScores($originalScores, $request);

        // if results are sorted by some value and then by relevance and scores are same
        if ($originalScores === $results) {
            return $response;
        }

        $documents = [];
        foreach ($response->getIterator() as $item) {
            $item->getCustomAttribute('score')->setValue($results[$item->getId()]);
            $documents[] = $item;
        }

        usort($documents, function ($a, $b) {
            if ($a->getCustomAttribute('score')->getValue() == $b->getCustomAttribute('score')->getValue()) {
                return 0;
            }

            return ($a->getCustomAttribute('score')->getValue() < $b->getCustomAttribute('score')->getValue()) ? 1 : -1;
        });

        return new QueryResponse(
            $documents,
            $response->getAggregations(),
            $response->getTotal()
        );
    }

    private function isSortByRelevance(Request $request): bool
    {
        if (count($request->getSort()) == 0) {
            return true;
        }

        foreach ($request->getSort() as $sort) {
            if ($sort['field'] == 'relevance') {
                return true;
            }
        }

        return false;
    }
}
