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
     * @var bool
     */
    private $customerOrdererProductAllowed;

    /**
     * @param Connection $connection
     * @param string $databasePrefix
     * @param bool $guestCommentsAllowed
     * @param int $commentsMinimalTime
     * @param bool $customerOrdererProductAllowed
     */
    public function __construct(
        Connection $connection,
        $databasePrefix,
        $guestCommentsAllowed,
        $commentsMinimalTime,
        $customerOrdererProductAllowed
    ) {
        $this->connection = $connection;
        $this->databasePrefix = $databasePrefix;
        $this->guestCommentsAllowed = (bool) $guestCommentsAllowed;
        $this->commentsMinimalTime = (int) $commentsMinimalTime;
        $this->customerOrdererProductAllowed = (bool) $customerOrdererProductAllowed;
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
            ->select('SUM(pc.grade) / COUNT(pc.grade) AS averageGrade')
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

        return (float) $qb->execute()->fetchColumn();
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

        return (int) $qb->execute()->fetchColumn();
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
        if ((!$idCustomer && !$this->guestCommentsAllowed) || !$this->isCustomerOrdererProductAllowed($productId, $idCustomer)) {
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
            ->set('id_customer', 0)
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
     * @param int $idProduct
     * @param int $idCustomer
     *
     * @return bool
     */
    private function isCustomerOrdererProductAllowed(int $idProduct, int $idCustomer)
    {
        if (!$this->customerOrdererProductAllowed) {
            return true;
        }
        
        //Test if the customer has a product ordered
        /** @var QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->addSelect('od.product_id')
            ->from($this->databasePrefix . 'order_detail', 'od')
            ->leftJoin('od', $this->databasePrefix . 'orders', 'o', 'od.id_order = o.id_order')
            ->where('o.id_customer = :id_customer')
            ->andWhere('od.product_id = :id_product')
            ->setParameter('id_customer', (int) $idCustomer)
            ->setParameter('id_product', (int) $idProduct);

        return $qb->execute()->rowCount() > 0;
    }
}
