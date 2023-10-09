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

namespace Mirasvit\SearchAutocomplete\InstantProvider;

use Mirasvit\Search\Model\AbstractConfigProvider;

class ConfigProvider extends AbstractConfigProvider
{
    private $configData;

    private $storeId = 0;

    public function __construct(array $configData)
    {
        $this->configData = $configData;
    }

    public function getEngine(): string
    {
        $engine = (string)$this->getConfigData('engine');//$this->configData["$this->storeId/engine"];
        if ($engine == 'opensearch') {
            $engine = 'elasticsearch7';
        }

        return $engine;
    }

    public function getIndexes(): array
    {
        return (array)$this->getConfigData('indexes');//$this->configData["$this->storeId/indexes"];
    }

    public function getIndexFields(string $indexIdentifier): array
    {
        return (array)$this->getConfigData("index/$indexIdentifier/fields");//$this->configData["$this->storeId/index/$indexIdentifier/fields"];
    }

    public function getIndexAttributes(string $indexIdentifier): array
    {
        return (array)$this->getConfigData("index/$indexIdentifier/attributes");//$this->configData["$this->storeId/index/$indexIdentifier/attributes"];
    }

    public function getLimit(string $indexIdentifier): int
    {
        return (int)$this->getConfigData("index/$indexIdentifier/limit");//$this->configData["$this->storeId/index/$indexIdentifier/limit"];
    }

    public function getIndexName(string $indexIdentifier): string
    {
        $searchEngine = $this->getEngine();

        return (string)$this->getConfigData($searchEngine)[$indexIdentifier];
    }

    public function getEngineConnection(): array
    {
        $searchEngine = $this->getEngine();

        return (array)$this->getConfigData($searchEngine)['connection'];
    }

    public function getIndexPosition(string $indexIdentifier): int
    {
        return (int)$this->getConfigData("index/$indexIdentifier/position");//$this->configData["$this->storeId/index/$indexIdentifier/position"];
    }

    public function getIndexTitle(string $indexIdentifier): string
    {
        return (string)$this->getConfigData("index/$indexIdentifier/title");//$this->configData["$this->storeId/index/$indexIdentifier/title"];
    }

    public function getTextAll(): string
    {
        return (string)$this->getConfigData('textAll');//$this->configData["$this->storeId/textAll"];
    }

    public function getTextEmpty(): string
    {
        return (string)$this->getConfigData('textEmpty');//$this->configData["$this->storeId/textEmpty"];
    }

    public function getUrlAll(): string
    {
        return (string)$this->getConfigData('urlAll');//$this->configData["$this->storeId/urlAll"];
    }

    public function getLongTailExpressions(): array
    {
        return (array)$this->getConfigData('configuration/long_tail_expressions');//$this->configData["$this->storeId/configuration/long_tail_expressions"];
    }

    public function getReplaceWords(): array
    {
        return (array)$this->getConfigData('configuration/replace_words');//$this->configData["$this->storeId/configuration/replace_words"];
    }

    public function getWildcardMode(): string
    {
        return (string)$this->getConfigData('configuration/wildcard');//$this->configData["$this->storeId/configuration/wildcard"];
    }

    public function getMatchMode(): string
    {
        return (string)$this->getConfigData('configuration/match_mode');//$this->configData["$this->storeId/configuration/match_mode"];
    }

    public function getWildcardExceptions(): array
    {
        return (array)$this->getConfigData('configuration/wildcard_exceptions');//$this->configData["$this->storeId/configuration/wildcard_exceptions"];
    }

    public function getSynonyms(array $terms, int $storeId): array
    {
        $synonyms     = [];
        $terms        = implode(' ', $terms);
        $initialQuery = $terms;
        $terms        = preg_replace('~\s~', ' ', trim($terms));
        $terms        = explode(' ', $terms);
        $terms[]      = $initialQuery;

        foreach ((array)$this->getConfigData('synonymList') as $synonymsGroup) {
            foreach (explode(',', $synonymsGroup) as $synonym) {
                foreach ($terms as $term) {
                    $synonym = trim($synonym);
                    $term    = trim($term);
                    if (mb_strtolower($synonym) == mb_strtolower($term)) {
                        if (isset($synonyms[$term])) {
                            $synonyms[$term] = array_merge($synonyms[$term], preg_split('/,/', $synonymsGroup));
                        } else {
                            $synonyms[$term] = preg_split('/,/', $synonymsGroup);
                        }
                    }
                }
            }
        }

        return $synonyms;
    }

    public function isStopword(string $term, int $storeId): bool
    {
        return in_array($term, (array)$this->getConfigData('stopwordList'));
    }

    public function applyStemming(string $term): string
    {
        if (substr($term, -2) === 'es') {
            $term = mb_substr($term, 0, -2);
        } elseif (substr($term, -1) === 's') {
            $term = mb_substr($term, 0, -1);
        }

        return $term;
    }

    public function getStoreId(): int
    {
        return $this->storeId;
    }

    public function setStoreId(int $storeId): void
    {
        $this->storeId = $storeId;
    }

    public function getTypeaheadSuggestions(string $query): array
    {
        $suggestions = [];
        foreach ((array)$this->getConfigData('typeahead') as $groupKey => $suggestionsGroup) {
            if (substr($query, 0, 2) == $groupKey) {
                $suggestions = $suggestionsGroup;
                break;
            }
        }

        return $suggestions;
    }

    public function getAvailableBuckets(): array
    {
        return array_keys((array)$this->getConfigData('buckets'));
    }

    public function getBuckets(): array
    {
        return (array)$this->getConfigData('buckets');
    }

    public function getBucketOptionsData(string $code, array $options): array
    {
        $buckets = (array)$this->getConfigData('buckets');

        if (!isset($buckets[$code]) || !isset($buckets[$code]['label'])) {
            return [];
        }

        $bucketData          = [];
        $bucketData['label'] = $buckets[$code]['label'];
        $bucketData['code']  = $code;

        if ($code == 'price') {
            return $bucketData;
        }

        if (!isset($buckets[$code]['options'])) {
            return [];
        }

        $keys          = array_column($options, 'key');
        $activeOptions = array_intersect_key($buckets[$code]['options'], array_flip($keys));

        foreach ($options as $option) {
            if ($option['doc_count'] == 0) {
                continue;
            }

            if ($code == 'category_ids' && (int)$option['key'] == 2) {
                continue;
            }

            if (!isset($activeOptions[$option['key']])) {
                continue;
            }

            $bucketData['items'][] = [
                'key'    => $option['key'],
                'label'  => $activeOptions[$option['key']],
                'count'  => $option['doc_count'],
                'filter' => json_encode([$code => $option['key']]),
            ];
        }

        if (empty($bucketData['items'])) {
            return [];
        }

        return $bucketData;
    }

    public function getActiveFilters(): array
    {
        $filters = [];
        if (filter_input(INPUT_GET, 'filters', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY)) {
            $filters = array_merge($filters, filter_input(INPUT_GET, 'filters', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY));
        }

        return $filters;
    }

    public function getProductsPerPage(): int
    {
        return (int)$this->getConfigData('productsPerPage');//$this->configData["$this->storeId/productsPerPage"];
    }

    public function getLayeredNavigationPosition(): string
    {
        return (string)$this->getConfigData('displayFilters');//$this->configData["$this->storeId/displayFilters"];
    }

    public function getPaginationPosition(): string
    {
        return (string)$this->getConfigData('pagination');//$this->configData["$this->storeId/pagination"];
    }

    private function getConfigData(string $key)
    {
        if (isset($this->configData[$this->storeId . '/' . $key])) {
            return $this->configData[$this->storeId . '/' . $key];
        }

        return false;
    }
}
