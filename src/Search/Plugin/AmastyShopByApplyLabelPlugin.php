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

/**
 * @see \Amasty\Shopby\Block\Navigation\State::viewLabel()
 */

class AmastyShopByApplyLabelPlugin
{
    public function aroundViewLabel($subject, $proceed, $filter)
    {
        $result = $proceed($filter);

        if (empty($result) && $filter->getFilter() instanceof \Mirasvit\Search\Model\Layer\Filter\SearchFilter) {
            $result = $filter->getLabel();
        }

        return $result;
    }
}
