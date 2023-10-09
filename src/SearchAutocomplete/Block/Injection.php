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

namespace Mirasvit\SearchAutocomplete\Block;

use Magento\Framework\Locale\FormatInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Search\Helper\Data as SearchHelper;
use Mirasvit\SearchAutocomplete\Model\ConfigProvider;

class Injection extends Template
{
    protected $storeManager;

    protected $config;

    protected $localeFormat;

    protected $searchHelper;

    protected $serializer;

    public function __construct(
        Context         $context,
        ConfigProvider  $config,
        FormatInterface $localeFormat,
        SearchHelper    $searchHelper,
        Json            $serializer
    ) {
        $this->storeManager = $context->getStoreManager();
        $this->config       = $config;
        $this->localeFormat = $localeFormat;
        $this->searchHelper = $searchHelper;
        $this->serializer   = $serializer;

        parent::__construct($context);
    }

    public function jsonEncode($data)
    {
        return $this->serializer->serialize($data);
    }

    public function getJsConfig(): array
    {
        return [
            'query'              => $this->searchHelper->getEscapedQueryText(),
            'priceFormat'        => $this->localeFormat->getPriceFormat(),
            'minSearchLength'    => $this->config->getMinChars(),
            'url'                => $this->getUrl(
                'searchautocomplete/ajax/suggest',
                ['_secure' => $this->getRequest()->isSecure()]
            ),
            'storeId'            => $this->storeManager->getStore()->getId(),
            'delay'              => $this->config->getDelay(),
            'isAjaxCartButton'   => $this->config->isAjaxCartButton(),
            'isShowCartButton'   => $this->config->isShowCartButton(),
            'isShowImage'        => $this->config->isShowImage(),
            'isShowPrice'        => $this->config->isShowPrice(),
            'isShowSku'          => $this->config->isShowSku(),
            'isShowRating'       => $this->config->isShowRating(),
            'isShowDescription'  => $this->config->isShowShortDescription(),
            'isShowStockStatus'  => $this->config->isShowStockStatus(),
            'layout'             => $this->config->getAutocompleteLayout(),
            'popularTitle'       => (string)__('Popular Suggestions'),
            'popularSearches'    => $this->config->isShowPopularSearches() ? $this->config->getPopularSearches() : [],
            'isTypeaheadEnabled' => $this->config->isTypeAheadEnabled(),
            'typeaheadUrl'       => $this->getUrl(
                'searchautocomplete/ajax/typeahead',
                ['_secure' => $this->getRequest()->isSecure()]
            ),
            'minSuggestLength'   => 2,
            'currency'           => $this->storeManager->getStore()->getCurrentCurrency()->getCode(),
            'limit'              => $this->config->getProductsPerPage(),
        ];

    }

    public function getAutocompleteLayout(): string
    {
        return $this->config->getAutocompleteLayout();
    }

    public function getCssStyles(): string
    {
        return $this->config->getCssStyles();
    }

    public function getTemplateScript(string $template): string
    {
        $filename = pathinfo($template)['filename'];
        $file     = $this->getTemplateFile($template);

        $html = '<script id="mstInPage__' . $filename . '" type="text/x-custom-template">';
        $html .= $this->fetchView($file);
        $html .= '</script>';

        return $html;
    }

    public function getLayeredNavigationPosition(): string
    {
        return $this->config->getLayeredNavigationPosition();
    }

    public function getPaginationPosition(): string
    {
        return $this->config->getPaginationPosition();
    }
}
