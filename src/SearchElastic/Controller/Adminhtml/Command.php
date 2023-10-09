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



namespace Mirasvit\SearchElastic\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Serialize\Serializer\Json;
use Mirasvit\SearchElastic\SearchAdapter\Manager;

abstract class Command extends Action
{
    protected $manager;

    protected $context;

    protected $serializer;

    public function __construct(
        Manager $manager,
        Json $serializer,
        Context $context
    ) {
        $this->manager = $manager;
        $this->context = $context;
        $this->serializer = $serializer;

        parent::__construct($context);
    }
}
