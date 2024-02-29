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

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table()
 * @ORM\Entity()
 */
class ProductCommentUsefulness
{
    /**
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="ProductComment")
     * @ORM\JoinColumn(name="id_product_comment", referencedColumnName="id_product_comment", onDelete="CASCADE")
     */
    private $comment;

    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(name="id_customer", type="integer")
     */
    private $customerId;

    /**
     * @var bool
     *
     * @ORM\Column(name="usefulness", type="boolean")
     */
    private $usefulness;

    /**
     * @param ProductComment $comment
     * @param int $customerId
     * @param bool $usefulness
     */
    public function __construct(
        ProductComment $comment,
        $customerId,
        $usefulness
    ) {
        $this->comment = $comment;
        $this->customerId = $customerId;
        $this->usefulness = $usefulness;
    }

    /**
     * @return mixed
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * @return int
     */
    public function getCustomerId()
    {
        return $this->customerId;
    }

    /**
     * @return bool
     */
    public function isUsefulness()
    {
        return $this->usefulness;
    }

    /**
     * @param bool $usefulness
     *
     * @return ProductCommentUsefulness
     */
    public function setUsefulness($usefulness)
    {
        $this->usefulness = $usefulness;

        return $this;
    }
}
