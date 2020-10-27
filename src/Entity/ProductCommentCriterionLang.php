<?php
/**
 * 2007-2020 PrestaShop SA and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2020 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

namespace PrestaShop\Module\ProductComment\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table()
 * @ORM\Entity()
 */
class ProductCommentCriterionLang
{
    /**
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="ProductCommentCriterion", inversedBy="languages")
     * @ORM\JoinColumn(name="id_product_comment_criterion", referencedColumnName="id_product_comment_criterion")
     */
    private $criterion;

    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(name="id_lang", type="integer")
     */
    private $langId;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=64)
     */
    private $name;

    /**
     * @return ProductCommentCriterion
     */
    public function getCriterion(): ProductCommentCriterion
    {
        return $this->criterion;
    }

    /**
     * @param ProductCommentCriterion $criterion
     *
     * @return self
     */
    public function setCriterion(ProductCommentCriterion $criterion): self
    {
        $this->criterion = $criterion;

        return $this;
    }

    /**
     * @return int
     */
    public function getLangId(): int
    {
        return $this->langId;
    }

    /**
     * @param int $langId
     *
     * @return self
     */
    public function setLangId(int $langId): self
    {
        $this->langId = $langId;

        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return self
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }
}
