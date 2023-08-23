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
use Hook;
use PrestaShop\Module\ProductComment\Entity\ProductComment;

/**
 * @extends ServiceEntityRepository<ProductComment>
 *
 * @method ProductComment|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductComment|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductComment[] findAll()
 * @method ProductComment[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductCommentRepository extends ServiceEntityRepository
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
     * @var bool
     */
    private $guestCommentsAllowed;

    /**
     * @var int
     */
    private $commentsMinimalTime;

    const DEFAULT_COMMENTS_PER_PAGE = 5;

    /**
     * @param ManagerRegistry $registry
     * @param Connection $connection
     * @param string $databasePrefix
     * @param bool $guestCommentsAllowed
     * @param int $commentsMinimalTime
     */
    public function __construct(
        $registry,
        $connection,
        $databasePrefix,
        $guestCommentsAllowed,
        $commentsMinimalTime
    ) {
        parent::__construct($registry, ProductComment::class);
        $this->connection = $connection;
        $this->databasePrefix = $databasePrefix;
        $this->guestCommentsAllowed = (bool) $guestCommentsAllowed;
        $this->commentsMinimalTime = (int) $commentsMinimalTime;
    }

    public function add(ProductComment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ProductComment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function delete(ProductComment $entity): void
    {
        $entityId = $entity->getId();

        $this->remove($entity, true);

        $this->deleteGrades($entityId);
        $this->deleteReports($entityId);
        $this->deleteUsefulness($entityId);
    }

    /**
     * @param int $productId
     * @param int $page
     * @param int $commentsPerPage
     * @param bool $validatedOnly
     *
     * @return array
     */
    public function paginate($productId, $page, $commentsPerPage, $validatedOnly)
    {
        if (empty($commentsPerPage)) {
            $commentsPerPage = self::DEFAULT_COMMENTS_PER_PAGE;
        }
        /** @var QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->addSelect('pc.id_product, pc.id_product_comment, pc.title, pc.content, pc.customer_name, pc.date_add, pc.grade')
            ->addSelect('c.firstname, c.lastname')
            ->from($this->databasePrefix . 'product_comment', 'pc')
            ->leftJoin('pc', $this->databasePrefix . 'customer', 'c', 'pc.id_customer = c.id_customer AND c.deleted = :not_deleted')
            ->andWhere('pc.id_product = :id_product')
            ->andWhere('pc.deleted = :not_deleted')
            ->setParameter('not_deleted', 0)
            ->setParameter('id_product', $productId)
            ->setMaxResults($commentsPerPage)
            ->setFirstResult(($page - 1) * $commentsPerPage)
            ->addGroupBy('pc.id_product_comment')
            ->addOrderBy('pc.date_add', 'DESC')
        ;

        if ($validatedOnly) {
            $qb
                ->andWhere('pc.validate = :validate')
                ->setParameter('validate', 1)
            ;
        }

        return $qb->execute()->fetchAll();
    }

    /**
     * @param int $productCommentId
     *
     * @return array
     */
    public function getProductCommentUsefulness($productCommentId)
    {
        /** @var QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->addSelect('pcu.usefulness')
            ->from($this->databasePrefix . 'product_comment_usefulness', 'pcu')
            ->andWhere('pcu.id_product_comment = :id_product_comment')
            ->setParameter('id_product_comment', $productCommentId)
        ;

        $usefulnessInfos = [
            'usefulness' => 0,
            'total_usefulness' => 0,
        ];
        $customerAppreciations = $qb->execute()->fetchAll();
        foreach ($customerAppreciations as $customerAppreciation) {
            if ((int) $customerAppreciation['usefulness']) {
                ++$usefulnessInfos['usefulness'];
            }
            ++$usefulnessInfos['total_usefulness'];
        }

        return $usefulnessInfos;
    }

    /**
     * @param int $productId
     * @param bool $validatedOnly
     *
     * @return float
     */
    public function getAverageGrade($productId, $validatedOnly)
    {
        /** @var QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('AVG(pc.grade) AS averageGrade')
            ->from($this->databasePrefix . 'product_comment', 'pc')
            ->andWhere('pc.id_product = :id_product')
            ->andWhere('pc.deleted = :deleted')
            ->setParameter('deleted', 0)
            ->setParameter('id_product', $productId)
        ;

        if ($validatedOnly) {
            $qb
                ->andWhere('pc.validate = :validate')
                ->setParameter('validate', 1)
            ;
        }

        return (float) $qb->execute()->fetch(\PDO::FETCH_COLUMN);
    }

    /**
     * @param int $langId
     * @param int $shopId
     * @param int $validate
     * @param bool $deleted
     * @param int $page
     * @param int $limit
     * @param bool $skip_validate
     *
     * @return array
     */
    public function getByValidate($langId, $shopId, $validate = 0, $deleted = false, $page = null, $limit = null, $skip_validate = false)
    {
        /** @var QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('pc.`id_product_comment`, pc.`id_product`, c.id_customer AS customer_id, 
                IF(c.id_customer, CONCAT(c.`firstname`, \' \',  c.`lastname`), pc.customer_name) customer_name, 
                pc.`title`, pc.`content`, pc.`grade`, pc.`date_add`, pl.`name`')
            ->from($this->databasePrefix . 'product_comment', 'pc')
            ->leftJoin('pc', $this->databasePrefix . 'customer', 'c', 'pc.id_customer = c.id_customer')
            ->leftJoin('pc', $this->databasePrefix . 'product_lang', 'pl', 'pc.id_product = pl.id_product')
            ->andWhere('pc.deleted = :deleted')
            ->setParameter('deleted', $deleted)
            ->andWhere('pl.id_lang = :id_lang')
            ->setParameter('id_lang', $langId)
            ->andWhere('pl.id_shop = :id_shop')
            ->setParameter('id_shop', $shopId)
            ->addOrderBy('pc.date_add', 'DESC')
        ;

        if (!$skip_validate) {
            $qb
                ->andWhere('pc.validate = :validate')
                ->setParameter('validate', $validate)
            ;
        }
        if ($page && $limit) {
            $limit = (int) $limit;
            $offset = ($page - 1) * $limit;
            $qb
                ->setFirstResult($offset)
                ->setMaxResults($limit);
        }

        return $this->connection->executeQuery(
            $qb->getSQL(), $qb->getParameters(), $qb->getParameterTypes()
        )->fetchAll();
    }

    /**
     * @param int $validate
     * @param bool $skip_validate
     *
     * @return int
     */
    public function getCountByValidate($validate = 0, $skip_validate = false)
    {
        /** @var QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('COUNT(*)')
            ->from($this->databasePrefix . 'product_comment', 'pc')
        ;

        if (!$skip_validate) {
            $qb
                ->andWhere('pc.validate = :validate')
                ->setParameter('validate', $validate)
            ;
        }

        return (int) $this->connection->executeQuery(
            $qb->getSQL(), $qb->getParameters(), $qb->getParameterTypes()
        )->fetch(\PDO::FETCH_COLUMN);
    }

    /**
     * @param array $productIds
     * @param bool $validatedOnly
     *
     * @return array
     */
    public function getAverageGrades(array $productIds, $validatedOnly)
    {
        $sql = 'SELECT';

        $count = count($productIds);

        foreach ($productIds as $index => $id) {
            $esqID = (int) $id;

            $sql .= ' SUM(IF(id_product = ' . $esqID . ' AND deleted = 0';
            if ($validatedOnly) {
                $sql .= ' AND validate = 1';
            }
            $sql .= ',grade, 0))';
            $sql .= ' / SUM(IF(id_product = ' . $esqID . ' AND deleted = 0';
            if ($validatedOnly) {
                $sql .= ' AND validate = 1';
            }
            $sql .= ',1, 0)) AS "' . $esqID . '"';

            if ($count - 1 > $index) {
                $sql .= ',';
            }
        }

        $sql .= ' FROM ' . $this->databasePrefix . 'product_comment';

        return $this->connection->executeQuery($sql)->fetch();
    }

    /**
     * @param int $productId
     * @param bool $validatedOnly
     *
     * @return int
     */
    public function getCommentsNumber($productId, $validatedOnly)
    {
        /** @var QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('COUNT(pc.id_product_comment) AS commentNb')
            ->from($this->databasePrefix . 'product_comment', 'pc')
            ->andWhere('pc.id_product = :id_product')
            ->andWhere('pc.deleted = :deleted')
            ->setParameter('deleted', 0)
            ->setParameter('id_product', $productId)
        ;

        if ($validatedOnly) {
            $qb
                ->andWhere('pc.validate = :validate')
                ->setParameter('validate', 1)
            ;
        }

        return (int) $qb->execute()->fetch(\PDO::FETCH_COLUMN);
    }

    /**
     * @param array $productIds
     * @param bool $validatedOnly
     *
     * @return array
     */
    public function getCommentsNumberForProducts(array $productIds, $validatedOnly)
    {
        $sql = 'SELECT';

        $count = count($productIds);

        foreach ($productIds as $index => $id) {
            $esqID = (int) $id;

            $sql .= ' SUM(IF(id_product = ' . $esqID . ' AND deleted = 0';
            if ($validatedOnly) {
                $sql .= ' AND validate = 1';
            }
            $sql .= ' ,1, 0)) AS "' . $esqID . '"';

            if ($count - 1 > $index) {
                $sql .= ',';
            }
        }

        $sql .= ' FROM ' . $this->databasePrefix . 'product_comment';

        return (array) $this->connection->executeQuery($sql)->fetch();
    }

    /**
     * @param int $productId
     * @param int $idCustomer
     * @param int $idGuest
     *
     * @return bool
     */
    public function isPostAllowed($productId, $idCustomer, $idGuest)
    {
        if (!$idCustomer && !$this->guestCommentsAllowed) {
            $postAllowed = false;
        } else {
            $lastCustomerComment = null;
            if ($idCustomer) {
                $lastCustomerComment = $this->getLastCustomerComment($productId, $idCustomer);
            } elseif ($idGuest) {
                $lastCustomerComment = $this->getLastGuestComment($productId, $idGuest);
            }
            $postAllowed = true;
            if (null !== $lastCustomerComment && isset($lastCustomerComment['date_add'])) {
                $postDate = new \DateTime($lastCustomerComment['date_add'], new \DateTimeZone('UTC'));
                if (time() - $postDate->getTimestamp() < $this->commentsMinimalTime) {
                    $postAllowed = false;
                }
            }
        }

        return $postAllowed;
    }

    /**
     * @param int $productId
     * @param int $idCustomer
     *
     * @return array
     */
    public function getLastCustomerComment($productId, $idCustomer)
    {
        return $this->getLastComment(['id_product' => $productId, 'id_customer' => $idCustomer]);
    }

    /**
     * @param int $productId
     * @param int $idGuest
     *
     * @return array
     */
    public function getLastGuestComment($productId, $idGuest)
    {
        return $this->getLastComment(['id_product' => $productId, 'id_guest' => $idGuest]);
    }

    /**
     * @param int $customerId
     */
    public function cleanCustomerData($customerId)
    {
        //We anonymize the customer comment by unlinking them (the name won't be visible any more but the grade and comment are still visible)
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->update($this->databasePrefix . 'product_comment', 'pc')
            ->set('id_customer', (string) 0)
            ->andWhere('pc.id_customer = :id_customer')
            ->setParameter('id_customer', $customerId)
        ;
        $qb->execute();

        //But we remove every report and votes for comments
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->delete($this->databasePrefix . 'product_comment_report')
            ->andWhere('id_customer = :id_customer')
            ->setParameter('id_customer', $customerId)
        ;
        $qb->execute();

        $qb = $this->connection->createQueryBuilder();
        $qb
            ->delete($this->databasePrefix . 'product_comment_usefulness')
            ->andWhere('id_customer = :id_customer')
            ->setParameter('id_customer', $customerId)
        ;
        $qb->execute();
    }

    /**
     * @param int $customerId
     * @param int $langId
     *
     * @return array
     */
    public function getCustomerData($customerId, $langId)
    {
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('pl.name, pc.id_product, pc.id_product_comment, pc.title, pc.content, pc.grade, pc.validate, pc.deleted, pcu.usefulness, pc.date_add')
            ->from($this->databasePrefix . 'product_comment', 'pc')
            ->leftJoin('pc', $this->databasePrefix . 'product_comment_usefulness', 'pcu', 'pc.id_product_comment = pcu.id_product_comment')
            ->leftJoin('pc', $this->databasePrefix . 'product', 'p', 'pc.id_product = p.id_product')
            ->leftJoin('p', $this->databasePrefix . 'product_lang', 'pl', 'p.id_product = pl.id_product')
            ->leftJoin('pl', $this->databasePrefix . 'lang', 'l', 'pl.id_lang = l.id_lang')
            ->andWhere('pc.id_customer = :id_customer')
            ->andWhere('l.id_lang = :id_lang')
            ->setParameter('id_customer', $customerId)
            ->setParameter('id_lang', $langId)
            ->addGroupBy('pc.id_product_comment')
            ->addOrderBy('pc.date_add', 'ASC')
        ;

        return $qb->execute()->fetchAll();
    }

    /**
     * @param array $criteria
     *
     * @return array
     */
    private function getLastComment(array $criteria)
    {
        /** @var QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('pc.*')
            ->from($this->databasePrefix . 'product_comment', 'pc')
            ->andWhere('pc.deleted = :deleted')
            ->setParameter('deleted', 0)
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

    /**
     * @param ProductComment $productComment
     * @param int $validate
     *
     * @return bool
     */
    public function validate($productComment, $validate = 1)
    {
        $success = $this->connection->executeUpdate('
            UPDATE `' . _DB_PREFIX_ . 'product_comment` SET
            `validate` = ' . $validate . '
            WHERE `id_product_comment` = ' . $productComment->getId());

        Hook::exec('actionObjectProductCommentValidateAfter', ['object' => $productComment]);

        return (bool) $success;
    }

    /**
     * @param int $id_product_comment
     *
     * @return bool
     */
    public function deleteGrades($id_product_comment)
    {
        $success = $this->connection->executeUpdate('
		DELETE FROM `' . _DB_PREFIX_ . 'product_comment_grade`
		WHERE `id_product_comment` = ' . $id_product_comment);

        return (bool) $success;
    }

    /**
     * @param int $id_product_comment
     *
     * @return bool
     */
    public function deleteReports($id_product_comment)
    {
        $success = $this->connection->executeUpdate('
		DELETE FROM `' . $this->databasePrefix . 'product_comment_report`
		WHERE `id_product_comment` = ' . $id_product_comment);

        return (bool) $success;
    }

    /**
     * @param int $id_product_comment
     *
     * @return bool
     */
    public function deleteUsefulness($id_product_comment)
    {
        $success = $this->connection->executeUpdate('
		DELETE FROM `' . _DB_PREFIX_ . 'product_comment_usefulness`
		WHERE `id_product_comment` = ' . $id_product_comment);

        return (bool) $success;
    }

    /**
     * @param int $langId
     * @param int $shopId
     *
     * @return array
     */
    public function getReportedComments($langId, $shopId)
    {
        $sql = 'SELECT DISTINCT(pc.`id_product_comment`), pc.`id_product`, pc.`content`, pc.`grade`, pc.`date_add`, pc.`title`
        , IF(c.id_customer, CONCAT(c.`firstname`, \' \',  c.`lastname`), pc.customer_name) customer_name, pl.`name`
		FROM `' . $this->databasePrefix . 'product_comment_report` pcr
		LEFT JOIN `' . $this->databasePrefix . 'product_comment` pc
			ON pcr.id_product_comment = pc.id_product_comment
		LEFT JOIN `' . $this->databasePrefix . 'customer` c ON (c.`id_customer` = pc.`id_customer`)
		LEFT JOIN `' . $this->databasePrefix . 'product_lang` pl ON ' .
        '(pl.`id_product` = pc.`id_product` ' .
        ' AND pl.`id_lang` = ' . $langId .
        ' AND pl.`id_shop` = ' . $shopId .
        ') 
        ORDER BY pc.`date_add` DESC';

        return $this->connection->executeQuery($sql)->fetchAll();
    }
}
