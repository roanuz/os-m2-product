<?php

namespace Roanuz\Product\Model\Resolver\Product;

use Magento\Catalog\Api\ProductAttributeGroupRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Group\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class AttributeList implements ResolverInterface
{

    public function __construct(
        ProductRepositoryInterface $productRepository,
        ProductAttributeGroupRepositoryInterface $productAttributeGroup,
        CollectionFactory $groupCollection

    ) {
        $this->productRepository = $productRepository;
        $this->productAttributeGroup = $productAttributeGroup;
        $this->groupCollection = $groupCollection;
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        $attributeList = [];
        $no = __('No');

        try {
            $model = $value['model'];
            $product = $this->loadMyProduct($model->getSku());
            $productAttributes = $product->getAttributes();
            $attributeSetId = $product->getAttributeSetId();
            $attributesgroups = $this->getAttributeGroups($attributeSetId);
            foreach ($attributesgroups as $attributesgroup) {
                $attributes = [];
                $groupId = $attributesgroup->getAttributeGroupId();
                $productAttributeGroup = $this->productAttributeGroup->get($groupId);
                $groupName = $productAttributeGroup->getData('attribute_group_name');

                foreach ($productAttributes as $attribute) {
                    if ($attribute->isInGroup($attributeSetId, $groupId)) {
                        if ($attribute->getIsVisibleOnFront()) {
                            $excludeAttr = [];
                            if ($this->isVisibleOnFrontend($attribute, $excludeAttr)) {
                                if ($attribute->getFrontend()->getValue($product) && $attribute->getFrontend()->getValue($product) != '' && $attribute->getFrontend()->getValue($product) != $no) {
                                    $value = $attribute->getFrontend()->getValue($product);
                                    $attributes[] = array(
                                        'attributeLabel' => $attribute->getStoreLabel(),
                                        'attributeCode' => $attribute->getAttributeCode(),
                                        'attributeValue' => $value,
                                    );
                                }
                            }
                        }
                    }
                }
                if (!empty($attributes)) {
                    $attributeList[] = array(
                        'attributeGroup' => $groupName,
                        'attributes' => $attributes,
                    );
                }
            }
        } catch (NoSuchEntityException $exception) {
            throw new GraphQlNoSuchEntityException(__($exception->getMessage()));
        } catch (LocalizedException $exception) {
            throw new GraphQlNoSuchEntityException(__($exception->getMessage()));
        }
        $response = array(
            'attributeList' => $attributeList,
        );

        return $attributeList;
    }

    public function loadMyProduct($sku)
    {
        return $this->productRepository->get($sku);
    }

    public function getAttributeGroups($attributeSetId)
    {
        $groupCollection = $this->groupCollection->create();
        $groupCollection->addFieldToFilter('attribute_set_id', $attributeSetId);
        $groupCollection->setOrder('sort_order', 'ASC');
        return $groupCollection;
    }

    public function isVisibleOnFrontend(AbstractAttribute $attribute, array $excludeAttr)
    {
        return ($attribute->getIsVisibleOnFront() && !in_array($attribute->getAttributeCode(), $excludeAttr));
    }
}
