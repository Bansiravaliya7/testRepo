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



namespace Mirasvit\SearchAutocomplete\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Mirasvit\SearchAutocomplete\Model\ConfigProvider;

class FiltersLayout implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => ConfigProvider::DISABLE,
                'label' => __('Disable'),
            ],
            [
                'value' => ConfigProvider::IN_SIDEBAR,
                'label' => __('In Sidebar'),
            ],
            [
                'value' => ConfigProvider::ON_TOP,
                'label' => __('On Top'),
            ],
        ];
    }
}