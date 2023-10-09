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

namespace Mirasvit\SearchElastic\Plugin\Frontend;

use Magento\Elasticsearch\SearchAdapter\ConnectionManager;
use Magento\Elasticsearch7\SearchAdapter\Mapper;
use Magento\Framework\Search\AdapterInterface;
use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\Response\QueryResponse;
use Magento\Framework\Serialize\Serializer\Json;
use Mirasvit\Search\Service\DebugService;

/**
 * @see \Magento\Elasticsearch7\SearchAdapter\Adapter::query()
 */
class ElasticsearchDebugLoggerAdapterPlugin
{
    private        $mapper;

    private        $connectionManager;

    private        $debugService;

    private        $serializer;

    private static $counter = 1;

    public function __construct(
        Mapper            $mapper,
        ConnectionManager $connectionManager,
        DebugService      $debugService,
        Json              $serializer
    ) {
        $this->mapper            = $mapper;
        $this->connectionManager = $connectionManager;
        $this->debugService      = $debugService;
        $this->serializer        = $serializer;
    }

    public function aroundQuery(AdapterInterface $subject, callable $proceed, RequestInterface $request): QueryResponse
    {
        if ($this->debugService->isEnabled()) {
            $client = $this->connectionManager->getConnection();
            /** @var \Magento\Elasticsearch7\Model\Client\Elasticsearch $client */
            $query = $this->mapper->buildQuery($request);

            $indexName = $request->getName();

            DebugService::log($this->serializer->serialize($query), 'query' . self::$counter . ': ' . $indexName);

            try {
                $rawResponse = $client->query($query);
            } catch (\Exception $e) {
                DebugService::log($e->getMessage(), 'exception' . self::$counter . ': ' . $indexName);
                $rawResponse = [];
            }

            DebugService::log($this->serializer->serialize($rawResponse), 'response' . self::$counter . ': ' . $indexName);
        }

        self::$counter++;

        return $proceed($request);
    }
}
