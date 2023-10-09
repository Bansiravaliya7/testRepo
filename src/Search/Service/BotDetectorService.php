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

use Mirasvit\Search\Model\ConfigProvider;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Mirasvit\SearchReport\Service\LogService;

class BotDetectorService
{
    public  $possibleInjectionTerms
        = [
            'admin',
            'wp',
            'login',
            'db',
            'zip',
            'rar',
            'tar',
            'gz',
            'sql',
            '7z',
            'bz2',
            'bak',
            'bck',
            'database',
            'sid',
            'localhost',
            'backup',
            'magento',
            'config',
            'passwd',
            'panel',
            'mysql',
            'admo',
            'ajaxplorer',
            'dump',
            'select',
            'where',
            'union',
            'alter',
            'drop',
            'create',
            'delete',
            'exec',
        ];

    private $configProvider;

    public function __construct(
        ConfigProvider $configProvider
    ) {
        $this->configProvider = $configProvider;
    }

    public function isBotQuery(string $query): bool
    {
        $query = strtolower($query);

        $isBot = false;

        if (empty($query)) {
            return false;
        }

        $ignoredIps = $this->configProvider->getIgnoredIps();

        if (in_array($this->configProvider->getIp(), $ignoredIps)) {
            return true;
        }

        foreach ($this->possibleInjectionTerms as $term) {
            if (str_contains($query, $term)) {
                return true;
            }
        }

        $terms = array_unique(preg_split('#\s#siu', $query, -1, PREG_SPLIT_NO_EMPTY));
        $terms = array_unique($terms);
        foreach ($terms as $term) {
            if ($this->configProvider->isStopword($term, 0)) {
                return true;
            }
        }

        return false;
    }
}
