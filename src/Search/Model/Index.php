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



namespace Mirasvit\Search\Model;

use Magento\Framework\Model\AbstractModel;
use Mirasvit\Core\Service\SerializeService;
use Mirasvit\Search\Api\Data\IndexInterface;

class Index extends AbstractModel implements IndexInterface
{
    protected $serializer;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Serialize\Serializer\Json $serializer,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->serializer = $serializer;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }
    public function getId(): int
    {
        return (int)parent::getData(self::ID);
    }

    public function getTitle(): string
    {
        return (string)parent::getData(self::TITLE);
    }

    public function setTitle(string $input): IndexInterface
    {
        return parent::setData(self::TITLE, $input);
    }

    public function getIdentifier(): ?string
    {
        return parent::getData(self::IDENTIFIER);
    }

    public function setIdentifier(string $input): IndexInterface
    {
        return parent::setData(self::IDENTIFIER, $input);
    }

    public function getPosition(): int
    {
        return (int)parent::getData(self::POSITION);
    }

    public function setPosition(int $value): IndexInterface
    {
        return parent::setData(self::POSITION, $value);
    }

    public function getAttributes(): array
    {
        if (empty(parent::getData(self::ATTRIBUTES_SERIALIZED))) {
            return [];
        }

        try {
            $data = (array)$this->serializer->unserialize(parent::getData(self::ATTRIBUTES_SERIALIZED));
        } catch (\Exception $e) {
            $data = [];
        }

        return $data;
    }

    public function setAttributes(array $input): IndexInterface
    {
        return parent::setData(self::ATTRIBUTES_SERIALIZED, $this->serializer->serialize($input));
    }

    public function setProperties(array $input): IndexInterface
    {
        return parent::setData(self::PROPERTIES_SERIALIZED, $this->serializer->serialize($input));
    }

    public function getStatus(): int
    {
        return (int)parent::getData(self::STATUS);
    }

    public function setStatus(int $value): IndexInterface
    {
        return parent::setData(self::STATUS, $value);
    }

    public function getIsActive(): bool
    {
        return (bool)parent::getData(self::IS_ACTIVE);
    }

    public function setIsActive(bool $value): IndexInterface
    {
        return parent::setData(self::IS_ACTIVE, $value);
    }

    public function getProperty(string $key): string
    {
        $props = $this->getProperties();
        if (isset($props[$key]) && is_array($props[$key])) {
            $props[$key] = $this->serializer->serialize($props[$key]);
        }

        return $props[$key] ?? '';
    }

    public function getProperties(): array
    {
        if (empty(parent::getData(self::PROPERTIES_SERIALIZED))) {
            return [];
        }

        return (array)SerializeService::decode(parent::getData(self::PROPERTIES_SERIALIZED));
    }

    protected function _construct(): void
    {
        $this->_init(ResourceModel\Index::class);

        parent::_construct();
    }
}
