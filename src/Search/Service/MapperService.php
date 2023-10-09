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

namespace Mirasvit\Search\Service;

use Magento\Framework\App\ObjectManager;
use Magento\Store\Model\StoreManagerInterface;

class MapperService
{
    private $storeManager;

    public function __construct(
        StoreManagerInterface $storeManager
    ) {
        $this->storeManager = $storeManager;
    }

    public function getBaseUrl(int $storeId): string
    {
        return $this->storeManager->getStore($storeId)->getBaseUrl();
    }

    public function getAdminPath(): string
    {
        $url = ObjectManager::getInstance()->get(\Magento\Backend\Helper\Data::class)
            ->getHomePageUrl();

        $components = parse_url($url);
        $components = explode('/', trim($components['path'], '/'));

        return array_shift($components);
    }

    public function clearString(string $string): string
    {
        $string = (string)preg_replace('/<style>.*<\/style>/', '', $string);

        return strip_tags(html_entity_decode($string));
    }

    public function correctUrl(int $storeId, string $url): string
    {
        $baseUrl = $this->getBaseUrl($storeId);

        if (strripos($url, $baseUrl) === false || strripos($url, $this->getAdminPath()) !== false) {
            $url = str_replace('/' . $this->getAdminPath() . '/', '/', $url);
            $url = preg_replace('~\/key\/.*~', '', $url);
        }

        if (strripos($url, $baseUrl) === false) {
            $baseChunks = explode('/', $baseUrl);
            $urlChunks  = explode('/', $url);
            foreach ($baseChunks as $idx => $chunk) {
                if (isset($urlChunks[$idx])) {
                    $urlChunks[$idx] = $chunk;
                }
            }

            $url = implode('/', $urlChunks);
        }

        return $url;
    }
}