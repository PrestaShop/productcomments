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

namespace PrestaShop\Module\ProductComment\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\Module\ProductComment\Entity\ProductCommentCriterion;

/**
 * @extends ServiceEntityRepository<ProductCommentCriterion>
 *
 * @method ProductCommentCriterion|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductCommentCriterion|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductCommentCriterion[]    findAll()
 * @method ProductCommentCriterion[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductCommentCriterionRepository extends ServiceEntityRepository
{
    /**
     * @var ManagerRegistry the EntityManager
     */
    private $registry;

    /**
     * @var Connection the Database connection
     */
    private $connection;

    /**
     * @var string the Database prefix
     */
    private $databasePrefix;

    /**
     * @param ManagerRegistry $registry
     * @param Connection $connection
     * @param string $databasePrefix
     */
    public function __construct($registry, $connection, $databasePrefix)
    {
        parent::__construct($registry, ProductCommentCriterion::class);
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
     * @param ProductCommentCriterion $entity
     * 
     * @return array
     * 
     */
    public function getProducts($entity)
    {
        $sql = '
			SELECT pccp.id_product, pccp.id_product_comment_criterion
			FROM `' . _DB_PREFIX_ . 'product_comment_criterion_product` pccp
			WHERE pccp.id_product_comment_criterion = ' . $entity->getId();

        $res = $this->connection->executeQuery($sql)->fetchAll();

        $products = [];
        if ($res) {
            foreach ($res as $row) {
                $products[] = (int) $row['id_product'];
            }
        }

        return $products;
    }

    /**
     * @param ProductCommentCriterion $entity
     * 
     * @return array
     * 
     */
    public function getCategories(ProductCommentCriterion $entity)
    {
        $sql = '
			SELECT pccc.id_category, pccc.id_product_comment_criterion
			FROM `' . _DB_PREFIX_ . 'product_comment_criterion_category` pccc
			WHERE pccc.id_product_comment_criterion = ' . $entity->getId();

        $res = $this->connection->executeQuery($sql)->fetchAll();

        $criterions = [];
        if ($res) {
            foreach ($res as $row) {
                $criterions[] = (int) $row['id_category'];
            }
        }

        return $criterions;
    }
}
