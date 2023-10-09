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

namespace Mirasvit\Search\Block;

use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Search\Helper\Data as SearchHelper;
use Mirasvit\Search\Model\ConfigProvider;

class CategorySearch extends Template
{
    protected $storeManager;

    protected $config;

    protected $localeFormat;

    protected $searchHelper;

    protected $layerResolver;

    protected $serializer;

    public function __construct(
        Context        $context,
        ConfigProvider $config,
        SearchHelper   $searchHelper,
        LayerResolver  $layerResolver,
        Json $serializer
    ) {
        $this->storeManager  = $context->getStoreManager();
        $this->config        = $config;
        $this->searchHelper  = $searchHelper;
        $this->layerResolver = $layerResolver;
        $this->serializer = $serializer;

        parent::__construct($context);
    }


    public function jsonEncode($data) {
        return $this->serializer->serialize($data);
    }

    public function getJsConfig(): array
    {
        return [
            'isActive'                => $this->config->isCategorySearch(),
            'minSearchLength'         => $this->searchHelper->getMinQueryLength(),
            'minProductsQtyToDisplay' => $this->config->getMinProductsQtyToDisplay(),
            'delay'                   => 300,
        ];
    }

    public function getQueryText(): string
    {
        return (string)$this->searchHelper->getEscapedQueryText();
    }

    public function getCollectionSize(): ?int
    {
        if ($this->layerResolver->get()->getProductCollection()->isLoaded()) {
            return $this->layerResolver->get()->getProductCollection()->getSize();
        } else {
            return null;
        }
    }

    public function getIsVisibleCategorySearch(): bool
    {
        return $this->getCollectionSize() === null || $this->getCollectionSize() > $this->config->getMinProductsQtyToDisplay()
            || strlen($this->getQueryText()) > $this->searchHelper->getMinQueryLength();
    }
}
