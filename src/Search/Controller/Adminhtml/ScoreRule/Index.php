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



namespace Mirasvit\Search\Controller\Adminhtml\ScoreRule;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\Filter\Date;
use Mirasvit\Search\Controller\Adminhtml\AbstractScoreRule;
use Mirasvit\Search\Repository\ScoreRuleRepository;
use Mirasvit\Search\Service\ScoreRuleService;

class Index extends AbstractScoreRule
{
    private $scoreRuleService;

    public function __construct(
        ScoreRuleService    $scoreRuleService,
        ScoreRuleRepository $scoreRuleRepository,
        Registry            $registry,
        ForwardFactory      $resultForwardFactory,
        Date                $dateFilter,
        Context             $context
    ) {
        $this->scoreRuleService = $scoreRuleService;

        parent::__construct($scoreRuleRepository, $registry, $resultForwardFactory, $dateFilter, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);

        $message = $this->scoreRuleService->checkConflicts();
        if ($message != '') {
            $this->messageManager->addWarningMessage($message);
        }

        $this->initPage($resultPage);

        return $resultPage;
    }
}
