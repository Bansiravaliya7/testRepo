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



namespace Mirasvit\Search\Service;

use Magento\Framework\Serialize\Serializer\Json;
use Mirasvit\Search\Api\Data\QueryConfigProviderInterface;

class QueryService
{
    private static $cache              = [];

    private        $synonyms           = [];

    private        $wildcardExceptions = [];

    private        $configProvider;

    private        $serializer;

    public function __construct(
        Json                         $serializer,
        QueryConfigProviderInterface $configProvider
    ) {
        $this->serializer     = $serializer;
        $this->configProvider = $configProvider;
    }

    public function build(string $query): array
    {
        $query   = urldecode($query);
        $storeId = $this->configProvider->getStoreId();

        if (function_exists('mb_strtolower')) {
            $query = mb_strtolower($query);
        } else {
            $query = strtolower($query);
        }

        $identifier = $storeId . $query;

        if (!array_key_exists($identifier, self::$cache)) {
            if (preg_match('~[\p{Han}]~u', $query)) {
                $query = preg_replace('~([\p{Han}])~u', ' $1', $query);
            }

            $query = ' ' . $query . ' ';
            $query = str_replace('!', ' ', $query);

            $queryTree = [];

            $replaceWords = $this->configProvider->getReplaceWords();

            foreach ($replaceWords as $replacement) {
                $query = str_replace(' ' . $replacement['from'] . ' ', ' ' . $replacement['to'] . ' ', $query);
            }

            $arSynonyms = $this->configProvider->getSynonyms([$query], $storeId);

            foreach ($arSynonyms as $term => $synonyms) {
                $this->synonyms    = array_merge($this->synonyms, $synonyms);
                $arSynonyms[$term] = array_splice($arSynonyms[$term], 0, 20);
            }

            $terms = preg_split('#\s#siu', $query, -1, PREG_SPLIT_NO_EMPTY);
            $terms = array_unique($terms);

            $condition = '$like';
            $longTail  = [];

            if ($this->configProvider->getMatchMode() == QueryConfigProviderInterface::MATCH_MODE_OR) {
                $mode = '$or';
            } else {
                $mode = '$and';
            }

            foreach ($terms as $term) {
                if ($this->configProvider->isStopword($term, $storeId) && count($terms) > 1) {
                    $query = preg_replace('~\b' . $term . '\b~', '', $query);
                    continue;
                }

                $wordArr = [];

                $this->addTerms($wordArr, [$term]);
                $this->addTerms($wordArr, [$this->configProvider->applyLongTail($term)]);
                $this->addTerms($wordArr, [$this->configProvider->applyStemming($term)]);

                if (isset($arSynonyms[$term])) {
                    $this->addTerms($wordArr, $arSynonyms[$term], QueryConfigProviderInterface::WILDCARD_DISABLED);
                }

                $queryTree[$condition][$mode][] = ['$or' => array_values($wordArr)];

                $longTail[$term] = trim($this->configProvider->applyLongTail($term));
            }

            $longTail[$query] = trim($this->configProvider->applyLongTail($this->configProvider->applyStemming($query)));

            foreach ($arSynonyms as $synonyms) {
                foreach ($synonyms as $synonym) {
                    if (count(explode(' ', $synonym)) >= 2) {
                        $ar = [];

                        $this->addTerms($ar, [$synonym], QueryConfigProviderInterface::WILDCARD_INFIX);
                        $queryTree = [
                            '$like' => [
                                '$or' => [
                                    $queryTree['$like'],
                                    array_values($ar)[0],
                                ],
                            ],
                        ];
                    }
                }
            }

            $result = [
                'queryTree'          => $queryTree,
                'query'              => trim($query),
                'wildcardMode'       => $this->configProvider->getWildcardMode(),
                'wildcardExceptions' => array_unique($this->wildcardExceptions),
                'matchMode'          => str_replace('$', '', $mode),
                'synonyms'           => $this->synonyms,
                'long_tail'          => [],
            ];

            $longTail = array_unique(array_filter($longTail));

            foreach ($longTail as $term => $replacement) {
                $term        = (string)$term;
                $replacement = (string)$replacement;

                $appliedTerm = trim(str_ireplace($term, $replacement, $query));
                if ($appliedTerm == $query) {
                    continue;
                }

                $result['long_tail'][] = $appliedTerm;
            }

            self::$cache[$identifier] = $result;
        }

        DebugService::log($this->serializer->serialize(self::$cache[$identifier]), 'query_service_build');

        return self::$cache[$identifier];
    }

    private function addTerms(array &$to, array $terms, string $wildcard = null): void
    {
        $exceptions = $this->configProvider->getWildcardExceptions();
        if ($wildcard == null) {
            $wildcard = $this->configProvider->getWildcardMode();
        }

        foreach ($terms as $term) {
            $term = trim($term);

            if ($term == '') {
                continue;
            }

            if ($wildcard == QueryConfigProviderInterface::WILDCARD_PREFIX) {
                $item = [
                    '$phrase'   => $term,
                    '$wildcard' => QueryConfigProviderInterface::WILDCARD_PREFIX,
                ];
            } elseif ($wildcard == QueryConfigProviderInterface::WILDCARD_SUFFIX) {
                $item = [
                    '$phrase'   => $term,
                    '$wildcard' => QueryConfigProviderInterface::WILDCARD_SUFFIX,
                ];
            } elseif ($wildcard == QueryConfigProviderInterface::WILDCARD_DISABLED || in_array($term, $exceptions)) {
                if (in_array($term, $exceptions)) {
                    $this->wildcardExceptions[] = $term;
                }

                $item = [
                    '$phrase'   => $term,
                    '$wildcard' => QueryConfigProviderInterface::WILDCARD_DISABLED,
                ];
            } else {
                $item = [
                    '$phrase'   => $term,
                    '$wildcard' => QueryConfigProviderInterface::WILDCARD_INFIX,
                ];
            }

            $to[implode(array_values($item))]['$term'] = $item;
        }
    }
}
