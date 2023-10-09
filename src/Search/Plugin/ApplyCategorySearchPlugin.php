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

use Magento\Catalog\Model\Layer\FilterList as GenericFilterList;
use Magento\Catalog\Model\Config\LayerCategoryConfig;
use Magento\Framework\ObjectManagerInterface;

/**
 * @see \Magento\Catalog\Model\Layer\FilterList::getFilters()
 */

class ApplyCategorySearchPlugin extends GenericFilterList
{
    protected $layerCategoryConfig;
    protected $objectManager;

    public function __construct(
        LayerCategoryConfig $layerCategoryConfig,
        ObjectManagerInterface $objectManager
    ) {
        $this->layerCategoryConfig  = $layerCategoryConfig;
        $this->objectManager        = $objectManager;
    }

    public function aroundGetFilters($subject, $proceed, \Magento\Catalog\Model\Layer $layer)
    {
        $result = $proceed($layer);

        if ($layer instanceOf \Magento\Catalog\Model\Layer\Category && $this->layerCategoryConfig->isCategoryFilterVisibleInLayerNavigation()) {
            $toApply = true;

            foreach ($result as $filter) {
                if ($filter instanceOf \Mirasvit\Search\Model\Layer\Filter\SearchFilter) {
                    $toApply = false;
                }
            }

            if ($toApply) {
                $result [] = $this->objectManager->create(\Mirasvit\Search\Model\Layer\Filter\SearchFilter::class, ['layer' => $layer]);
            }
        }

        return $result;
    }
}
