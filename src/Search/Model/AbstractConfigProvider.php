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

namespace Mirasvit\Search\Model;

use Mirasvit\Search\Api\Data\QueryConfigProviderInterface;

abstract class AbstractConfigProvider implements QueryConfigProviderInterface
{
    public abstract function getLongTailExpressions(): array;

    public function applyLongTail(string $term): string
    {
        $expressions = $this->getLongTailExpressions();

        foreach ($expressions as $expr) {
            $matches = null;
            preg_match_all($expr['match_expr'], $term, $matches);

            foreach ($matches[0] as $math) {
                $replace = (string)preg_replace($expr['replace_expr'], $expr['replace_char'], $math);

                $term = str_replace($math, $replace, $term);
            }
        }

        return $term;
    }
}