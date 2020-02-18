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

namespace PrestaShop\Module\ProductComment\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\Module\ProductComment\Entity\ProductCommentCriterion;

class ProductCommentCriterionRepository
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
     * @param Connection $connection
     * @param string $databasePrefix
     */
    public function __construct(Connection $connection, $databasePrefix)
    {
        $this->connection = $connection;
        $this->databasePrefix = $databasePrefix;
    }

    /**
     * @param int $idProduct
     * @param int $idLang
     *
     * @return array
     *
     * @throws \PrestaShopException
     */
    public function getByProduct($idProduct, $idLang)
    {
        /** @var QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('pcc.id_product_comment_criterion, pccl.name')
            ->from($this->databasePrefix . 'product_comment_criterion', 'pcc')
            ->leftJoin('pcc', $this->databasePrefix . 'product_comment_criterion_lang', 'pccl', 'pcc.id_product_comment_criterion = pccl.id_product_comment_criterion')
            ->leftJoin('pcc', $this->databasePrefix . 'product_comment_criterion_product', 'pccp', 'pcc.id_product_comment_criterion = pccp.id_product_comment_criterion')
            ->leftJoin('pcc', $this->databasePrefix . 'product_comment_criterion_category', 'pccc', 'pcc.id_product_comment_criterion = pccc.id_product_comment_criterion')
            ->leftJoin('pccc', $this->databasePrefix . 'category', 'c', 'pccc.id_category = c.id_category')
            ->leftJoin('c', $this->databasePrefix . 'category_product', 'cp', 'c.id_category = cp.id_category')
            ->andWhere($qb->expr()->orX(
                $qb->expr()->eq('pcc.id_product_comment_criterion_type', ':catalog_type'),
                $qb->expr()->eq('pccp.id_product', ':id_product'),
                $qb->expr()->eq('cp.id_product', ':id_product')
            ))
            ->andWhere('pccl.id_lang = :id_lang')
            ->andWhere('pcc.active = :active')
            ->setParameter('catalog_type', ProductCommentCriterion::ENTIRE_CATALOG_TYPE)
            ->setParameter('active', 1)
            ->setParameter('id_product', $idProduct)
            ->setParameter('id_lang', $idLang)
            ->addGroupBy('pcc.id_product_comment_criterion')
        ;

        return $qb->execute()->fetchAll();
    }

    /**
     * @param bool $active
     *
     * @return int
     */
    public function count($active = false)
    {
        /** @var QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->from($this->databasePrefix . 'product_comment_criterion', 'pcc')
        ;
        if ($active) {
            $qb->andWhere('pcc.active = 1');
        }

        return $qb->execute()->rowCount();
    }
}
