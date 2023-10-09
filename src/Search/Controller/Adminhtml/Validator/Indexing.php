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

namespace Mirasvit\Search\Controller\Adminhtml\Validator;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManager;
use Mirasvit\Search\Api\Data\IndexInterface;
use Mirasvit\Search\Controller\Adminhtml\AbstractValidator;
use Mirasvit\Search\Repository\IndexRepository;
use Magento\Elasticsearch\SearchAdapter\ConnectionManager;
use Magento\Elasticsearch\SearchAdapter\SearchIndexNameResolver;

class Indexing extends AbstractValidator
{
    private $indexRepository;

    private $connectionManager;

    private $indexNameResolver;

    private $storeManager;

    private $resultJsonFactory;

    public function __construct(
        IndexRepository         $indexRepository,
        ConnectionManager       $connectionManager,
        SearchIndexNameResolver $indexNameResolver,
        StoreManager            $storeManager,
        JsonFactory             $resultJsonFactory,
        Context                 $context
    ) {
        $this->indexRepository   = $indexRepository;
        $this->connectionManager = $connectionManager;
        $this->indexNameResolver = $indexNameResolver;
        $this->storeManager      = $storeManager;
        $this->resultJsonFactory = $resultJsonFactory;

        parent::__construct($context);
    }

    public function execute()
    {
        $entityId = (int)$this->getRequest()->getParam('entityID');

        $indexes = $this->indexRepository->getCollection()
            ->addFieldToFilter(IndexInterface::IS_ACTIVE, true);

        $html = '';

        foreach ($this->storeManager->getStores() as $store) {
            if (!$store->getIsActive()) {
                continue;
            }

            $storeId = (int)$store->getId();
            foreach ($indexes as $index) {
                $html .= '<h3>' . $index->getIdentifier() . ' / store: ' . $storeId . '</h3>';

                foreach ($this->getResults($index, $storeId, $entityId) as $item) {
                    $html .= '<pre>';
                    $html .= htmlspecialchars(json_encode($item, JSON_PRETTY_PRINT));
                    $html .= '</pre>';
                }
            }
        }

        $response = $this->resultJsonFactory->create();

        return $response->setData([
            'html' => $html,
        ]);
    }

    private function getResults(IndexInterface $index, int $storeId, int $entityId): array
    {
        $result = [];

        /** @var \Magento\Elasticsearch7\Model\Client\Elasticsearch $connection */
        $connection = $this->connectionManager->getConnection();

        if (!$connection->indexExists($this->getIndexName($index, $storeId))) {
            return [];
        }

        $response = $connection->query($this->entityQuery($index, $storeId, $entityId));

        if (isset($response['hits']['hits'])) {
            foreach ($response['hits']['hits'] as $hit) {
                $result[] = $hit;
            }
        }

        return $result;
    }

    private function getIndexName(IndexInterface $index, int $storeId): string
    {
        return $this->indexNameResolver->getIndexName(
            $storeId,
            $index->getIdentifier()
        );
    }

    private function entityQuery(IndexInterface $index, int $storeId, int $entityId): array
    {
        return [
            'index' => $this->getIndexName($index, $storeId),
            'body'  => [
                'from'          => 0,
                'size'          => 10,
                'stored_fields' => [
                    '_id',
                    '_score',
                    '_source',
                ],
                'sort'          => [
                    [
                        '_score' => [
                            'order' => 'desc',
                        ],
                    ],
                ],
                'query'         => [
                    'terms' => [
                        '_id' => [$entityId],
                    ],
                ],
            ],
        ];
    }

    public function _processUrlKeys(): bool
    {
        return true;
    }
}