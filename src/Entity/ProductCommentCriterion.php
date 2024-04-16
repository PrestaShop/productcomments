<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace PrestaShop\Module\ProductComment\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Validate;

/**
 * @ORM\Table()
 * @ORM\Entity()
 */
class ProductCommentCriterion
{
    const NAME_MAX_LENGTH = 64;
    const ENTIRE_CATALOG_TYPE = 1;
    const CATEGORIES_TYPE = 2;
    const PRODUCTS_TYPE = 3;

    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(name="id_product_comment_criterion", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="id_product_comment_criterion_type", type="integer")
     */
    private $type;

    /**
     * @var bool
     *
     * @ORM\Column(name="active", type="boolean")
     */
    private $active = false;

    /**
     * @var array
     *
     * @deprecated 7.0.0 - use criterionLangs instead
     */
    private $names;

    /**
     * @ORM\OneToMany(targetEntity="PrestaShop\Module\ProductComment\Entity\ProductCommentCriterionLang", cascade={"persist", "remove"}, mappedBy="productcommentcriterion")
     */
    private $criterionLangs;

    /**
     * @var array
     *
     * @todo implement as ORM\OneToMany in the future
     */
    private $categories;

    /**
     * @var array
     *
     * @todo implement as ORM\OneToMany in the future
     */
    private $products;

    public function __construct()
    {
        $this->criterionLangs = new ArrayCollection();
    }

    /**
     * @return ArrayCollection
     */
    public function getCriterionLangs()
    {
        return $this->criterionLangs;
    }

    /**
     * @return ProductCommentCriterionLang|null
     */
    public function getCriterionLangByLangId(int $langId)
    {
        foreach ($this->criterionLangs as $criterionLang) {
            if ($langId === $criterionLang->getLang()->getId()) {
                return $criterionLang;
            }
        }

        return null;
    }

    public function addCriterionLang(ProductCommentCriterionLang $criterionLang): self
    {
        $criterionLang->setProductCommentCriterion($this);
        $this->criterionLangs->add($criterionLang);

        return $this;
    }

    public function getCriterionName(): string
    {
        if ($this->criterionLangs->count() <= 0) {
            return '';
        }

        $criterionLang = $this->criterionLangs->first();

        return $criterionLang->getName();
    }

    /**
     * @return array
     *
     * @deprecated 7.0.0 - migrated to Form\ProductCommentCriterionFormDataProvider
     */
    public function getNames()
    {
        return $this->names;
    }

    /**
     * @param array $langNames
     *
     * @return ProductCommentCriterion
     *
     * @deprecated 7.0.0
     */
    public function setNames($langNames)
    {
        $this->names = $langNames;

        return $this;
    }

    /**
     * @return array
     */
    public function getCategories()
    {
        return $this->categories;
    }

    /**
     * @param array $selectedCategories
     */
    public function setCategories($selectedCategories): self
    {
        $this->categories = $selectedCategories;

        return $this;
    }

    /**
     * @return array
     */
    public function getProducts()
    {
        return $this->products;
    }

    /**
     * @param array $selectedProducts
     */
    public function setProducts($selectedProducts): self
    {
        $this->products = $selectedProducts;

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function setType(int $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function isValid(): bool
    {
        foreach ($this->criterionLangs as $criterionLang) {
            if (!Validate::isGenericName($criterionLang->getName())) {
                return false;
            }
        }

        return true;
    }
}
