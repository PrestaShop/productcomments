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
use Doctrine\Persistence\ManagerRegistry;
use PrestaShop\Module\ProductComment\Entity\ProductCommentCriterion;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

/**
 * @extends ServiceEntityRepository<ProductCommentCriterion>
 *
 * @method ProductCommentCriterion|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductCommentCriterion|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductCommentCriterion[] findAll()
 * @method ProductCommentCriterion[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductCommentCriterionRepository extends ServiceEntityRepository
{
    /**
     * @var ManagerRegistry the Doctrine Registry
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

    public function add(ProductCommentCriterion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ProductCommentCriterion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @deprecated 7.0.0 - cascade remove by Entity setting instead
     */
    private function deleteLangs($criterion): int
    {
        return $this->connection->executeUpdate('
            DELETE FROM `' . _DB_PREFIX_ . 'product_comment_criterion_lang`
            WHERE `id_product_comment_criterion` = ' . $criterion->getId());
    }

    private function deleteCategories($criterion): int
    {
        return $this->connection->executeUpdate('
            DELETE FROM `' . _DB_PREFIX_ . 'product_comment_criterion_category`
            WHERE `id_product_comment_criterion` = ' . $criterion->getId());
    }

    private function deleteProducts($criterion): int
    {
        return $this->connection->executeUpdate('
            DELETE FROM `' . _DB_PREFIX_ . 'product_comment_criterion_product`
            WHERE `id_product_comment_criterion` = ' . $criterion->getId());
    }

    private function deleteGrades($criterion): int
    {
        return $this->connection->executeUpdate('
            DELETE FROM `' . _DB_PREFIX_ . 'product_comment_grade`
            WHERE `id_product_comment_criterion` = ' . $criterion->getId());
    }

    /* Remove a criterion and Delete its manual relation _category, _product, _grade */
    public function delete(ProductCommentCriterion $criterion): int
    {
        $res = 0;

        $criterionType = $criterion->getType();

        if ($criterionType == ProductCommentCriterion::CATEGORIES_TYPE) {
            $res += $this->deleteCategories($criterion);
        } elseif ($criterionType == ProductCommentCriterion::PRODUCTS_TYPE) {
            $res += $this->deleteProducts($criterion);
        } else {
            $res = 1;
        }

        $res += $this->deleteGrades($criterion);

        $this->remove($criterion, true);

        // todo: return void, and use try catch Exception instead
        return $res;
    }

    /* Update a criterion and Update its manual relation _category, _product */
    public function update(ProductCommentCriterion $criterion): int
    {
        $res = 0;

        $criterionType = $criterion->getType();

        $this->getEntityManager()->persist($criterion);
        $this->getEntityManager()->flush();

        if ($criterionType == ProductCommentCriterion::CATEGORIES_TYPE) {
            $res += $this->deleteCategories($criterion);
            $res += $this->updateCategories($criterion);
        } elseif ($criterionType == ProductCommentCriterion::PRODUCTS_TYPE) {
            $res += $this->deleteProducts($criterion);
            $res += $this->updateProducts($criterion);
        } else {
            $res = 1;
        }

        // todo: return void, and use try catch Exception instead
        return $res;
    }

    /**
     * @deprecated 7.0.0 - migrated to Form\ProductCommentCriterionFormDataHandler
     */
    private function updateLangs($criterion): int
    {
        $res = 0;
        $criterionId = $criterion->getId();
        foreach ($criterion->getNames() as $key => $value) {
            $qb = $this->connection->createQueryBuilder();
            $qb
            ->insert(_DB_PREFIX_ . 'product_comment_criterion_lang')
            ->values(
                [
                    'id_product_comment_criterion' => '?',
                    'id_lang' => '?',
                    'name' => '?',
                ]
            )
            ->setParameter(0, $criterionId)
            ->setParameter(1, $key)
            ->setParameter(2, $value)
            ;
            $res += $this->connection->executeUpdate($qb->getSQL(), $qb->getParameters(), $qb->getParameterTypes());
        }

        return $res;
    }

    private function updateCategories($criterion): int
    {
        $res = 0;
        $criterionId = $criterion->getId();
        foreach ($criterion->getCategories() as $id_category) {
            $res += $this->connection->executeUpdate(
                'INSERT INTO `' .
                _DB_PREFIX_ . 'product_comment_criterion_category` (`id_product_comment_criterion`, `id_category`)
                VALUES(' . $criterionId . ',' . $id_category . ')'
            );
        }

        return $res;
    }

    private function updateProducts($criterion): int
    {
        $res = 0;
        $criterionId = $criterion->getId();
        foreach ($criterion->getProducts() as $id_product) {
            $res += $this->connection->executeUpdate(
                'INSERT INTO `' .
                _DB_PREFIX_ . 'product_comment_criterion_product` (`id_product_comment_criterion`, `id_product`)
                VALUES(' . $criterionId . ',' . $id_product . ')'
            );
        }

        return $res;
    }

    public function updateGeneral(ProductCommentCriterion $criterion): void
    {
        $this->getEntityManager()->persist($criterion);
        $this->getEntityManager()->flush();
    }

    /**
     * @return array
     *
     * @throws \PrestaShopException
     */
    public function getByProduct(int $idProduct, int $idLang)
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
     * @return array Criterions
     */
    public function getCriterions(int $id_lang, $type = false, $active = false)
    {
        $sql = '
            SELECT pcc.`id_product_comment_criterion`, pcc.id_product_comment_criterion_type, pccl.`name`, pcc.active
            FROM `' . _DB_PREFIX_ . 'product_comment_criterion` pcc
            JOIN `' . _DB_PREFIX_ . 'product_comment_criterion_lang` pccl ON (pcc.id_product_comment_criterion = pccl.id_product_comment_criterion)
            WHERE pccl.`id_lang` = ' . $id_lang . ($active ? ' AND active = 1' : '') . ($type ? ' AND id_product_comment_criterion_type = ' . (int) $type : '') . '
            ORDER BY pccl.`name` ASC';
        $criterions = $this->connection->executeQuery($sql)->fetchAll();

        $types = self::getTypes();
        foreach ($criterions as $key => $data) {
            $criterions[$key]['type_name'] = $types[$data['id_product_comment_criterion_type']];
        }

        return $criterions;
    }

    /**
     * @return array
     */
    public function getProducts(int $id_criterion)
    {
        $sql = '
            SELECT pccp.id_product, pccp.id_product_comment_criterion
            FROM `' . _DB_PREFIX_ . 'product_comment_criterion_product` pccp
            WHERE pccp.id_product_comment_criterion = ' . $id_criterion;

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
     * @return array
     */
    public function getCategories(int $id_criterion)
    {
        $sql = '
            SELECT pccc.id_category, pccc.id_product_comment_criterion
            FROM `' . _DB_PREFIX_ . 'product_comment_criterion_category` pccc
            WHERE pccc.id_product_comment_criterion = ' . $id_criterion;

        $res = $this->connection->executeQuery($sql)->fetchAll();

        $criterions = [];
        if ($res) {
            foreach ($res as $row) {
                $criterions[] = (int) $row['id_category'];
            }
        }

        return $criterions;
    }

    /**
     * @return array
     */
    public function getTypes()
    {
        $sfTranslator = SymfonyContainer::getInstance()->get('translator');

        return [
            1 => $sfTranslator->trans('Valid for the entire catalog', [], 'Modules.Productcomments.Admin'),
            2 => $sfTranslator->trans('Restricted to some categories', [], 'Modules.Productcomments.Admin'),
            3 => $sfTranslator->trans('Restricted to some products', [], 'Modules.Productcomments.Admin'),
        ];
    }

    /**
     * @return ProductCommentCriterion
     *
     * @deprecated 7.0.0 - use standard find() instead
     */
    public function findRelation($id_criterion)
    {
        if ($id_criterion > 0) {
            $criterion = $this->find($id_criterion);
        } else {
            $criterion = new ProductCommentCriterion();
        }

        return $criterion;
    }
}
