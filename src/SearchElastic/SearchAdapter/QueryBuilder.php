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

namespace Mirasvit\SearchElastic\SearchAdapter;

use Mirasvit\Search\Api\Data\QueryConfigProviderInterface;
use Mirasvit\Search\Service\QueryService;

class QueryBuilder
{
    protected $searchTerms;

    private   $queryService;

    public function __construct(
        QueryService $queryService
    ) {
        $this->queryService = $queryService;
    }

    public function build(array $selectQuery, string $searchTerm, array $fields): array
    {
        $this->searchTerms = [];

        if (!isset($selectQuery['bool'])) {
            $selectQuery['bool'] = [];
        }
        if (!isset($selectQuery['bool']['must'])) {
            $selectQuery['bool']['must'] = [];
        }

        foreach ($selectQuery['bool']['must'] as $key => $item) {
            if (isset($item['query_string'])) {
                return $selectQuery;
            }
        }
        $selectQuery['bool']['must'] = array_values($selectQuery['bool']['must']);

        $searchQuery = $this->queryService->build($searchTerm);

        $queryValue = $searchQuery['query'];

        $preparedFields = [];

        $this->compileQuery($searchQuery['queryTree']);

        $wildcardExceptions = $searchQuery['wildcardExceptions'];

        if (empty($wildcardExceptions)) {
            $processedQuery = $queryValue;
        } else {
            $processedQuery = $this->escape(preg_replace('~\b(' . implode('|', $wildcardExceptions) . ')\b~', " $1 ", $queryValue));
        }

        $terms = preg_split('~\s~', $processedQuery);

        $compiledQuery = $this->compileQuery($searchQuery['queryTree']);

        foreach ($fields as $field => $boost) {
            $preparedFields[] = $field;

            $boost = (int)$boost;
            if ($boost == 10) {
                $boost = 1000000;
            } elseif ($boost == 9) {
                $boost = 100000;
            } elseif ($boost == 8) {
                $boost = 10000;
            } elseif ($boost == 7) {
                $boost = 1000;
            } elseif ($boost > 1) {
                $boost = pow(2, $boost);
            } else {
                $boost = 0;
            }

            if ($boost <= 1) {
                continue;
            }

            $selectQuery['bool']['should'][]['terms'] = [
                $field  => $terms,
                'boost' => $boost,
            ];

            $selectQuery['bool']['should'][]['match_phrase'] = [
                $field => [
                    'query' => $processedQuery,
                    'boost' => (string)($boost + 100),
                ],
            ];

            $selectQuery['bool']['should'][]['wildcard'][$field . '.keyword'] = [
                'value' => $processedQuery . '*',
                'boost' => (string)($boost + 50),
            ];

            $selectQuery['bool']['should'][]['wildcard'][$field . '.keyword'] = [
                'value' => '*' . $processedQuery,
                'boost' => (string)($boost + 50),
            ];

            $selectQuery['bool']['should'][]['wildcard'][$field . '.keyword'] = [
                'value' => '*' . $processedQuery . '*',
                'boost' => (string)($boost + 10),
            ];

            foreach ($this->searchTerms as $term => $boostMultiplier) {
                $term = (string)$term;

                if (strlen($term) < 3) {
                    $selectQuery['bool']['should'][]['wildcard'][$field . '.keyword'] = [
                        'value' => $term,
                        'boost' => (string)$boost * 0.75 * (float)$boostMultiplier,
                    ];
                } else {
                    $selectQuery['bool']['should'][]['wildcard'][$field . '.keyword'] = [
                        'value' => $term,
                        'boost' => (string)$boost * (float)$boostMultiplier,
                    ];
                }
            }

            if (count($selectQuery['bool']['should']) > 1000) {
                break;
            }
        }

        unset($processedQuery);

        $selectQuery['bool']['must'][]['query_string'] = [
            'query'            => $compiledQuery,
            'fields'           => $preparedFields,
            'default_operator' => strtoupper($searchQuery['matchMode']),
        ];

        return $selectQuery;
    }

    protected function escape(string $value): string
    {
        $pattern = '/(\+|-|\/|&&|\|\||!|\(|\)|\{|}|\[|]|\^|"|~|\*|\?|:|\\\)/';
        $replace = '\\\$1';

        return preg_replace($pattern, $replace, $value);
    }

    private function compileQuery(array $query): string
    {
        $compiled = [];
        foreach ($query as $directive => $value) {
            switch ($directive) {
                case '$like':
                    $compiled[] = '(' . $this->compileQuery($value) . ')';
                    break;
                case '$and':
                    $and = [];
                    foreach ($value as $item) {
                        $and[] = $this->compileQuery($item);
                    }
                    $compiled[] = '(' . implode(' AND ', $and) . ')';
                    break;

                case '$or':
                    $or = [];

                    foreach ($value as $item) {
                        $or[] = $this->compileQuery($item);
                    }

                    $compiled[] = '(' . implode(' OR ', $or) . ')';
                    break;

                case '$term':
                    $phrase = $this->escape($value['$phrase']);

                    switch ($value['$wildcard']) {
                        case QueryConfigProviderInterface::WILDCARD_INFIX:
                            $compiled[]                     = "(*$phrase*)";
                            $this->searchTerms[$phrase]     = 1;
                            $this->searchTerms["*$phrase*"] = 0.3;
                            break;
                        case QueryConfigProviderInterface::WILDCARD_PREFIX:
                            $compiled[]                    = "(*$phrase)";
                            $this->searchTerms[$phrase]    = 1;
                            $this->searchTerms["*$phrase"] = 0.5;
                            break;
                        case QueryConfigProviderInterface::WILDCARD_SUFFIX:
                            $compiled[]                    = "($phrase*)";
                            $this->searchTerms[$phrase]    = 1;
                            $this->searchTerms["$phrase*"] = 0.5;
                            break;
                        case QueryConfigProviderInterface::WILDCARD_DISABLED:
                            if (strpos($phrase, ' ') !== false) {
                                $compiled[] = "(*$phrase*)";
                            } else {
                                $compiled[] = '(' . $phrase . ')';
                            }
                            $this->searchTerms[$phrase] = 1;
                            break;
                    }
                    break;
            }
        }

        return implode(' OR ', $compiled);
    }
}
