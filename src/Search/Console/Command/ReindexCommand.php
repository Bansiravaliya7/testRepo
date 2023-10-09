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



namespace Mirasvit\Search\Console\Command;

use Magento\Framework\App\State as AppState;
use Magento\Framework\ObjectManagerInterface;
use Mirasvit\Search\Api\Data\IndexInterface;
use Mirasvit\Search\Repository\IndexRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ReindexCommand extends Command
{
    private $indexRepository;

    private $appState;

    private $objectManager;

    public function __construct(
        IndexRepository        $indexRepository,
        AppState               $appState,
        ObjectManagerInterface $objectManager
    ) {
        $this->indexRepository = $indexRepository;
        $this->appState        = $appState;
        $this->objectManager   = $objectManager;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('mirasvit:search:reindex')
            ->setDescription('Reindex all search indexes')
            ->setDefinition([]);

        $this->addOption('index', 'i', InputOption::VALUE_REQUIRED, 'Reindex particular index');
        $this->addOption('store', 's', InputOption::VALUE_REQUIRED, 'Reindex particular store');
        $this->addOption('per-product', 'p', InputOption::VALUE_NONE, 'Reindex products one by one');

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ts = microtime(true);

        try {
            $this->appState->setAreaCode('adminhtml');
        } catch (\Exception $e) {
        }

        if ($input->getOption('per-product')) {
            /** @var \Magento\Framework\App\ResourceConnection $resource */
            $resource = $this->objectManager->get(\Magento\Framework\App\ResourceConnection::class);
            /** @var \Magento\CatalogSearch\Model\Indexer\Fulltext $fulltextIndexer */
            $fulltextIndexer = $this->objectManager->create(\Magento\CatalogSearch\Model\Indexer\Fulltext::class, ['data' => [
                'indexer_id' => 'catalogsearch_fulltext',
            ]]);
            $productIds      = $resource->getConnection()
                ->fetchCol('SELECT entity_id FROM ' . $resource->getTableName('catalog_product_entity'));

            foreach ($productIds as $idx => $productId) {
                $fulltextIndexer->executeRow($productId);
                $output->write($idx . '/' . count($productIds) . PHP_EOL);
            }

            return 0;
        }

        $collection = $this->indexRepository->getCollection()
            ->addFieldToFilter('is_active', 1);

        /** @var IndexInterface $index */
        foreach ($collection as $index) {
            $output->write($index->getTitle() . ' [' . $index->getIdentifier() . ']....');

            if ($input->getOption('index') && $input->getOption('index') !== $index->getIdentifier()) {
                $output->writeln('skip');
                continue;
            }

            try {
                /** @var \Mirasvit\Search\Model\Index\AbstractIndex $instance */
                $instance = $this->indexRepository->getInstance($index);

                $instance->reindexAll($input->getOption('store'));

                $output->writeln("<info>Done</info>");
            } catch (\Exception $e) {
                $this->handleError($e, $output, (bool)$input->getOption('verbose'));
            }
        }

        $output->writeln(round(microtime(true) - $ts, 0) . ' sec');

        return 0;
    }

    private function handleError(\Exception $e, OutputInterface $output, bool $verboseOutput): void
    {
        $output->writeln(PHP_EOL);
        $output->writeln("<error>{$e->getMessage()} in {$e->getFile()} on line {$e->getLine()}</error>");
        if ($verboseOutput) {
            $output->writeln("<error>{$e->getTraceAsString()}</error>");
        }
    }
}
