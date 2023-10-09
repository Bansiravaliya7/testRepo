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

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as AttributeFactory;
use Magento\Elasticsearch\Model\Adapter\FieldMapperInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Search\Model\ResourceModel\Query\CollectionFactory as QueryCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\Misspell\Model\ConfigProvider as MisspellConfigProvider;
use Mirasvit\Search\Model\ConfigProvider as SearchConfigProvider;
use Mirasvit\Search\Repository\IndexRepository;
use Mirasvit\Search\Repository\StopwordRepository;
use Mirasvit\Search\Repository\SynonymRepository;
use Mirasvit\SearchAutocomplete\Model\ConfigProvider;
use Mirasvit\SearchAutocomplete\Model\IndexProvider;

class ConfigMaker
{
    private $configData   = [];

    private $configMakers = [];

    private $fs;

    private $emulatorService;

    private $storeManager;

    private $indexProvider;

    private $indexRepository;

    private $fieldMapper;

    private $configProvider;

    private $searchConfigProvider;

    private $synonymRepository;

    private $stopwordRepository;

    private $queryCollectionFactory;

    private $attributeFactory;

    private $categoryCollection;

    private $misspellConfigProvider;

    private $serializer;

    public function __construct(
        Filesystem                $fs,
        EmulatorService           $emulatorService,
        StoreManagerInterface     $storeManager,
        IndexProvider             $indexProvider,
        IndexRepository           $indexRepository,
        FieldMapperInterface      $fieldMapper,
        ConfigProvider            $configProvider,
        SearchConfigProvider      $searchConfigProvider,
        SynonymRepository         $synonymRepository,
        StopwordRepository        $stopwordRepository,
        QueryCollectionFactory    $queryCollectionFactory,
        AttributeFactory          $attributeFactory,
        CategoryCollectionFactory $categoryCollection,
        MisspellConfigProvider    $misspellConfigProvider,
        Json                      $serializer,
        array                     $makers = []
    ) {
        $this->fs                     = $fs;
        $this->emulatorService        = $emulatorService;
        $this->storeManager           = $storeManager;
        $this->indexProvider          = $indexProvider;
        $this->indexRepository        = $indexRepository;
        $this->configProvider         = $configProvider;
        $this->searchConfigProvider   = $searchConfigProvider;
        $this->fieldMapper            = $fieldMapper;
        $this->synonymRepository      = $synonymRepository;
        $this->stopwordRepository     = $stopwordRepository;
        $this->queryCollectionFactory = $queryCollectionFactory;
        $this->attributeFactory       = $attributeFactory->create();
        $this->categoryCollection     = $categoryCollection;
        $this->misspellConfigProvider = $misspellConfigProvider;
        $this->configMakers           = $makers;
        $this->serializer             = $serializer;
    }

    public function ensure(): void
    {
        $path             = $this->fs->getDirectoryRead(DirectoryList::CONFIG)
            ->getRelativePath('instant.json');
        $fastModeEnabled  = $this->configProvider->isFastModeEnabled();
        $typeaheadEnabled = $this->configProvider->isTypeAheadEnabled();

        $this->setValue(0, 'instant', $fastModeEnabled);
        $this->setValue(0, 'typeahead', $typeaheadEnabled);

        if ($fastModeEnabled) {
            $this->generateInstantConfig();
        }

        if ($typeaheadEnabled) {
            $this->generateTypeaheadConfig();
        }

        if (!$fastModeEnabled && !$typeaheadEnabled) {
            $this->fs->getDirectoryWrite(DirectoryList::CONFIG)
                ->delete($path);

            return;
        }

        $isNew = $this->fs->getDirectoryWrite(DirectoryList::CONFIG)
            ->isExist($path);

        $this->fs->getDirectoryWrite(DirectoryList::CONFIG)
            ->writeFile($path, $this->serializer->serialize($this->configData));

        if ($isNew) {
            throw new \Exception('To avoid search autocomplete downtime please run search reindex.');
        }
    }

    protected function setValue(int $scope, string $path, $value): ConfigMaker
    {
        $this->configData["$scope/$path"] = $value;

        return $this;
    }

    private function generateInstantConfig(): void
    {
        $this->setValue(0, 'isEnabled', $this->configProvider->isFastModeEnabled())
            ->setValue(0, 'engine', $this->configProvider->getSearchEngine());

        $storeIds = [0];
        foreach ($this->storeManager->getStores() as $store) {
            $storeIds[] = (int)$store->getId();
        }

        $this->attributeFactory->addFieldToFilter('is_filterable_in_search', 1);

        foreach ($storeIds as $scopeId) {
            $buckets = [];
            foreach ($this->attributeFactory as $attribute) {
                $attributeCode                    = $attribute->getAttributeCode();
                $buckets[$attributeCode]['label'] = $attribute->getStoreLabel($scopeId);
                foreach ($attribute->getOptions() as $option) {
                    $label = $option->getStoreLabels($scopeId);
                    if (empty($label)) {
                        $label = $option->getLabel();
                    }
                    if (is_array($option->getValue())) {
                        unset($buckets[$attributeCode]);
                        continue 2;
                    } else {
                        $buckets[$attributeCode]['options'][$option->getValue()] = (string)$label;
                    }
                }
            }

            foreach ($this->categoryCollection->create()->addAttributeToSelect('*')->setStore($scopeId) as $categoryData) {
                $buckets['category_ids']['options'][$categoryData->getId()] = (string)$categoryData->getName();
            }
            $buckets['category_ids']['label'] = __('Categories');
            $this->setValue($scopeId, 'buckets', $buckets);
            unset($buckets);


            $synonymList = [];
            foreach ($this->synonymRepository->getCollection()->addFieldToFilter('store_id', [0, $scopeId]) as $synonym) {
                $synonymList[] = $synonym->getSynonymGroup();
            }

            $stopwordList = [];
            foreach ($this->stopwordRepository->getCollection()->addFieldToFilter('store_id', [0, $scopeId]) as $stopword) {
                $stopwordList[] = $stopword->getTerm();
            }

            $this
                ->setValue($scopeId, 'isEnabled', $this->configProvider->isFastModeEnabled())
                ->setValue($scopeId, 'engine', $this->configProvider->getSearchEngine())
                ->setValue($scopeId, 'productsPerPage', $this->configProvider->getProductsPerPage())
                ->setValue($scopeId, 'displayFilters', $this->configProvider->getLayeredNavigationPosition())
                ->setValue($scopeId, 'pagination', $this->configProvider->getPaginationPosition())
                ->setValue($scopeId, 'is_show_cart_button', $this->configProvider->isShowCartButton())
                ->setValue($scopeId, 'is_ajax_cart_button', $this->configProvider->isAjaxCartButton())
                ->setValue($scopeId, 'is_show_image', $this->configProvider->isShowImage())
                ->setValue($scopeId, 'is_show_price', $this->configProvider->isShowPrice())
                ->setValue($scopeId, 'is_show_rating', $this->configProvider->isShowRating())
                ->setValue($scopeId, 'is_show_sku', $this->configProvider->isShowSku())
                ->setValue($scopeId, 'is_show_short_description', $this->configProvider->isShowShortDescription())
                ->setValue($scopeId, 'is_show_stock_status', $this->configProvider->isShowStockStatus())
                ->setValue($scopeId, 'textAll', $this->emulatorService->getStoreText('View all %d results →', $scopeId))
                ->setValue($scopeId, 'textEmpty', $this->emulatorService->getStoreText('Sorry, nothing has been found for "%s".', $scopeId))
                ->setValue($scopeId, 'urlAll', $this->emulatorService->getStoreUrl($scopeId))
                ->setValue($scopeId, 'configuration/wildcard', $this->searchConfigProvider->getWildcardMode())
                ->setValue($scopeId, 'configuration/wildcard_exceptions', $this->searchConfigProvider->getWildcardExceptions())
                ->setValue($scopeId, 'configuration/replace_words', $this->searchConfigProvider->getReplaceWords())
                ->setValue($scopeId, 'configuration/long_tail_expressions', $this->searchConfigProvider->getLongTailExpressions())
                ->setValue($scopeId, 'configuration/match_mode', $this->searchConfigProvider->getMatchMode())
                ->setValue($scopeId, 'synonymList', $synonymList)
                ->setValue($scopeId, 'stopwordList', $stopwordList)
                ->setValue($scopeId, 'currencySymbol', $store->getCurrentCurrency()->getCurrencySymbol());


            $indexes           = [];
            $isMisspellEnabled = $this->misspellConfigProvider->isMisspellEnabled();
            if ($isMisspellEnabled) {
                $indexes[] = 'mst_misspell_index';
            }

            foreach ($this->indexProvider->getList() as $index) {
                if (!$index->isActive()) {
                    continue;
                }

                $searchIndex = $this->indexRepository->getInstanceByIdentifier($index->getIdentifier());

                $identifier = $index->getIdentifier();
                if ($identifier == 'catalogsearch_fulltext') {
                    $identifier = 'magento_catalog_product';
                }

                $indexes[] = $identifier;

                $this
                    ->setValue($scopeId, "index/$identifier/identifier", $identifier)
                    ->setValue($scopeId, "index/$identifier/title", $this->emulatorService->getStoreText($index->getTitle(), $scopeId))
                    ->setValue($scopeId, "index/$identifier/position", $index->getPosition())
                    ->setValue($scopeId, "index/$identifier/limit", $index->getLimit())
                    ->setValue($scopeId, "index/$identifier/attributes", $searchIndex->getAttributeWeights());

                $fields = [];
                foreach ($searchIndex->getAttributeWeights() as $attribute => $weight) {
                    $resolvedField = $this->fieldMapper->getFieldName(
                        $attribute,
                        ['type' => FieldMapperInterface::TYPE_QUERY]
                    );

                    $fields[$resolvedField] = $weight;
                }
                $this->setValue($scopeId, "index/$identifier/fields", $fields);
            }

            $this->setValue($scopeId, "indexes", $indexes);

            foreach ($this->configMakers as $engine => $maker) {
                $this->setValue($scopeId, $engine, $maker->getConfig($scopeId, $isMisspellEnabled));
            }
        }
    }

    private function generateTypeaheadConfig(): void
    {
        foreach ($this->storeManager->getStores() as $store) {
            $results    = [];
            $storeId    = (int)$store->getId();
            $collection = $this->queryCollectionFactory->create();

            $collection->getSelect()->reset(\Magento\Framework\DB\Select::COLUMNS)
                ->columns([
                    'suggest'     => new \Zend_Db_Expr('MAX(query_text)'),
                    'suggest_key' => new \Zend_Db_Expr('substring(query_text,1,2)'),
                    'popularity'  => new \Zend_Db_Expr('MAX(popularity)'),
                ])
                ->where('num_results > 0')
                ->where('store_id = ?', $storeId)
                ->where('display_in_terms = 1')
                ->where('is_active = 1')
                ->where('popularity > 5 ')
                ->where('CHAR_LENGTH(query_text) > 3')
                ->group(new \Zend_Db_Expr('substring(query_text,1,6)'))
                ->group(new \Zend_Db_Expr('substring(query_text,1,2)'))
                ->order('suggest_key ' . \Magento\Framework\DB\Select::SQL_ASC)
                ->order('popularity ' . \Magento\Framework\DB\Select::SQL_DESC);

            foreach ($collection as $suggestion) {
                $results[strtolower($suggestion['suggest_key'])][] = strtolower($suggestion['suggest']);
            }

            $this->setValue($storeId, 'typeahead', $results);
        }
    }

}
