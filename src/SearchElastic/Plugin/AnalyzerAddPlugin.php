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

/**
 * @see \Magento\Elasticsearch\Model\Adapter\Index\Builder::build()
 */
class AnalyzerAddPlugin
{
    public function afterBuild($subject, array $result): array
    {
        $result['analysis']['analyzer']['mst'] = [
            'type'      => 'custom',
            'tokenizer' => 'whitespace',
            'filter'    => [
                'lowercase',
                'keyword_repeat',
                'asciifolding',
                'default_stemmer',
                'unique_stem',
            ],
        ];

        return $result;
    }
}