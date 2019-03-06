<?php
/**
 * 2007-2019 PrestaShop SA and Contributors
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
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

namespace PrestaShop\Module\ProductComment\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\Module\ProductComment\Entity\ProductCommentCriterion;
use PrestaShop\PrestaShop\Adapter\Presenter\Product\ProductLazyArray;
use Product;
use Validate;
use Tools;

class ProductCommentRepository
{
    /**
     * @var Connection the Database connection
     */
    private $connection;

    /**
     * @var string the Database prefix
     */
    private $databasePrefix;

    /**
     * @var bool
     */
    private $guestCommentsAllowed;

    /**
     * @var int
     */
    private $commentsMinimalTime;

    /**
     * @param Connection $connection
     * @param string $databasePrefix
     * @param bool $guestCommentsAllowed
     * @param int $commentsMinimalTime
     */
    public function __construct(
        Connection $connection,
        $databasePrefix,
        $guestCommentsAllowed,
        $commentsMinimalTime
    ) {
        $this->connection = $connection;
        $this->databasePrefix = $databasePrefix;
        $this->guestCommentsAllowed = (bool) $guestCommentsAllowed;
        $this->commentsMinimalTime = (int) $commentsMinimalTime;
    }

    /**
     * @param int|Product|ProductLazyArray $product
     * @param bool $validatedOnly
     *
     * @return float
     * @throws \PrestaShopException
     */
    public function getAverageGrade($product, $validatedOnly)
    {
        $idProduct = is_object($product) ? $product->id : (int) $product;

        if (!Validate::isUnsignedId($idProduct)) {
            throw new \Exception(Tools::displayError());
        }

        /** @var QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('SUM(pc.grade) / COUNT(pc.grade) AS averageGrade')
            ->from($this->databasePrefix . 'product_comment', 'pc')
            ->andWhere('pc.id_product = :id_product')
            ->setParameter('id_product', $idProduct)
        ;

        if ($validatedOnly) {
            $qb
                ->andWhere('pc.validate = :validate')
                ->setParameter('validate', 1)
            ;
        }

        return (float) $qb->execute()->fetchColumn();
    }

    /**
     * @param int|Product|ProductLazyArray $product
     * @param bool $validatedOnly
     *
     * @return int
     * @throws \PrestaShopException
     */
    public function getCommentsNumber($product, $validatedOnly)
    {
        $idProduct = is_object($product) ? $product->id : (int) $product;

        if (!Validate::isUnsignedId($idProduct)) {
            throw new \Exception(Tools::displayError());
        }

        /** @var QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('COUNT(pc.id_product_comment) AS commentNb')
            ->from($this->databasePrefix . 'product_comment', 'pc')
            ->andWhere('pc.id_product = :id_product')
            ->setParameter('id_product', $idProduct)
        ;

        if ($validatedOnly) {
            $qb
                ->andWhere('pc.validate = :validate')
                ->setParameter('validate', 1)
            ;
        }

        return (int) $qb->execute()->fetchColumn();
    }

    /**
     * @param $product
     * @param $cookie
     *
     * @return bool
     * @throws \PrestaShopException
     */
    /**
     * @param $product
     * @param $idCustomer
     * @param $idGuest
     *
     * @return bool
     * @throws \PrestaShopException
     */
    public function isPostAllowed($product, $idCustomer, $idGuest)
    {
        $idProduct = is_object($product) ? $product->id : (int) $product;

        if (!Validate::isUnsignedId($idProduct)) {
            throw new \Exception(Tools::displayError());
        }

        if (!$idCustomer && !$this->guestCommentsAllowed) {
            $postAllowed = false;
        } else {
            $lastCustomerComment = null;
            if ($idCustomer) {
                $lastCustomerComment = $this->getLastCustomerComment($idProduct, $idCustomer);
            } elseif ($idGuest) {
                $lastCustomerComment = $this->getLastGuestComment($idProduct, $idGuest);
            }
            $postAllowed = null === $lastCustomerComment
                || !isset($lastCustomerComment['date_add'])
                || time() - strtotime($lastCustomerComment['date_add']) > $this->commentsMinimalTime
            ;
        }

        return $postAllowed;
    }

    /**
     * @param int|Product|ProductLazyArray $product $product
     * @param int $idCustomer
     *
     * @return array
     * @throws \PrestaShopException
     */
    public function getLastCustomerComment($product, $idCustomer)
    {
        $idProduct = is_object($product) ? $product->id : (int) $product;

        if (!Validate::isUnsignedId($idProduct)) {
            throw new \Exception(Tools::displayError());
        }

        return $this->getLastComment(['id_product' => $idProduct, 'id_customer' => $idCustomer]);
    }

    /**
     * @param int|Product|ProductLazyArray $product $product
     * @param int $idGuest
     *
     * @return array
     * @throws \PrestaShopException
     */
    public function getLastGuestComment($product, $idGuest)
    {
        $idProduct = is_object($product) ? $product->id : (int) $product;

        if (!Validate::isUnsignedId($idProduct)) {
            throw new \Exception(Tools::displayError());
        }

        return $this->getLastComment(['id_product' => $idProduct, 'id_guest' => $idGuest]);
    }

    private function getLastComment(array $criteria)
    {
        /** @var QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('pc.*')
            ->from($this->databasePrefix . 'product_comment', 'pc')
            ->addOrderBy('pc.date_add', 'DESC')
            ->setMaxResults(1)
        ;

        foreach ($criteria as $field => $value) {
            $qb
                ->andWhere(sprintf('pc.%s = :%s', $field, $field))
                ->setParameter($field, $value)
            ;
        }

        $comments = $qb->execute()->fetchAll();

        return empty($comments) ? [] : $comments[0];
    }
}
