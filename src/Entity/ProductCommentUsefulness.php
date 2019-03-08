<?php
/**
 * 2007-2019 PrestaShop SA and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA and Contributors
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
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
     * @ORM\JoinColumn(name="id_product_comment", referencedColumnName="id_product_comment")
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
