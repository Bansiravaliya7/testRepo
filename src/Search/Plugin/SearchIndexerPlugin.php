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



namespace Mirasvit\Search\Plugin;

use Magento\CatalogSearch\Model\Indexer\Fulltext\Action\DataProvider;
use Mirasvit\Search\Api\Data\IndexInterface;
use Mirasvit\Search\Repository\IndexRepository;

/**
 * @see \Magento\CatalogSearch\Model\Indexer\Fulltext\Action\DataProvider::prepareProductIndex()
 */
class SearchIndexerPlugin
{

    private $indexRepository;

    public function __construct(
        IndexRepository $indexRepository
    ) {
        $this->indexRepository = $indexRepository;
    }

    public function afterPrepareProductIndex(
        $dataProvider,
        $attributeData,
        $productData = null,
        $productAdditional = null,
        $storeId = null
    ) {
        if ($productData === null || count($productData) === 0) {
            return $attributeData;
        }

        if (!$this->getIndex()) {
            return $attributeData;
        }

        $includeBundled = $this->getIndex()->getProperty('include_bundled');
        $productData    = array_values($productData)[0];
        if (!$includeBundled) {
            foreach ($attributeData as $attributeId => $value) {
                if (is_array($value)) {
                    foreach ($value as $key => $option) {
                        $value[$key] = preg_replace('/(\d.*\|)/', '', $option);
                    }
                } else {
                    $value = preg_replace('/(attr.*\|)/', '', $value);
                }

                $attribute = $dataProvider->getSearchableAttribute($attributeId);
                if (!empty($value) && in_array($attribute->getFrontendInput(), ['multiselect', 'select'])) {
                    if (is_array($value)) {
                        foreach ($value as $nestedKey => $nestedValue) {
                            $attributeData[$attributeId][$nestedKey] = $nestedValue;
                        }
                    } else {
                        $attributeData[$attributeId] = $value;
                    }
                    continue;
                }

                if (isset($productData[$attributeId])) {
                    $attributeData[$attributeId] = trim($productData[$attributeId]);
                }
            }
        }

        return $attributeData;
    }

    private function getIndex(): ?IndexInterface
    {
        return $this->indexRepository->getByIdentifier('catalogsearch_fulltext');
    }
}
