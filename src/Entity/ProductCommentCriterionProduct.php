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
class ProductCommentCriterionProduct
{
    /**
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="ProductCommentCriterion")
     * @ORM\JoinColumn(name="id_product_comment_criterion", referencedColumnName="id_product_comment_criterion")
     */
    private $criterion;

    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(name="id_product", type="integer")
     */
    private $productId;

    /**
     * @return ProductCommentCriterion
     */
    public function getCriterion(): ProductCommentCriterion
    {
        return $this->criterion;
    }

    /**
     * @param mixed $criterion
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
    public function getProductId(): int
    {
        return $this->productId;
    }

    /**
     * @param int $productId
     *
     * @return self
     */
    public function setProductId(int $productId): self
    {
        $this->productId = $productId;

        return $this;
    }
}
