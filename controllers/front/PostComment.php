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
use PrestaShop\Module\ProductComment\Entity\ProductComment;
use PrestaShop\Module\ProductComment\Entity\ProductCommentCriterion;
use PrestaShop\Module\ProductComment\Entity\ProductCommentGrade;
use Doctrine\ORM\EntityManagerInterface;
use PrestaShop\Module\ProductComment\Repository\ProductCommentRepository;

class ProductCommentsPostCommentModuleFrontController extends ModuleFrontController
{
    public function display()
    {
        if (!(int) $this->context->cookie->id_customer && !Configuration::get('PRODUCT_COMMENTS_ALLOW_GUESTS')) {
            $this->ajaxRender(json_encode([
                'success' => false,
                'error' => $this->trans('You need to be logged in to post your review.'),
            ]));

            return false;
        }

        $id_product = Tools::getValue('id_product');
        $comment_title = Tools::getValue('comment_title');
        $comment_content = Tools::getValue('comment_content');
        $customer_name = Tools::getValue('customer_name');
        $criterions = Tools::getValue('criterion');

        /** @var ProductCommentRepository $productCommentRepository */
        $productCommentRepository = $this->context->controller->getContainer()->get('product_comment_repository');
        $isPostAllowed = $productCommentRepository->isPostAllowed($id_product, (int) $this->context->cookie->id_customer, (int) $this->context->cookie->id_guest);
        if (!$isPostAllowed) {
            $this->ajaxRender(json_encode([
                'success' => false,
                'error' => $this->trans('You are not allowed to post a review at the moment, please try again later.'),
            ]));

            return false;
        }

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->container->get('doctrine.orm.entity_manager');

        //Create product comment
        $productComment = new ProductComment();
        $productComment
            ->setProductId($id_product)
            ->setTitle($comment_title)
            ->setContent($comment_content)
            ->setCustomerName($customer_name)
            ->setCustomerId($this->context->cookie->id_customer)
            ->setGuestId($this->context->cookie->id_guest)
            ->setDateAdd(new \DateTime('now', new \DateTimeZone('UTC')))
        ;
        $entityManager->persist($productComment);
        $this->addCommentGrades($productComment, $criterions);

        //Validate comment
        if (!empty($errors = $this->validateComment($productComment))) {
            $this->ajaxRender(json_encode([
                'success' => false,
                'errors' => $errors,
            ]));

            return false;
        }

        $entityManager->flush();

        $this->ajaxRender(json_encode([
            'success' => true,
            'product_comment' => $productComment->toArray(),
        ]));
    }

    /**
     * @param ProductComment $productComment
     * @param array $criterions
     *
     * @throws Exception
     */
    private function addCommentGrades(ProductComment $productComment, array $criterions)
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        $criterionRepository = $entityManager->getRepository(ProductCommentCriterion::class);
        $averageGrade = 0;
        foreach ($criterions as $criterionId => $grade) {
            $criterion = $criterionRepository->findOneById($criterionId);
            $criterionGrade = new ProductCommentGrade(
                $productComment,
                $criterion,
                $grade
            );
            $entityManager->persist($criterionGrade);
            $averageGrade += $grade;
        }
        $averageGrade /= count($criterions);
        $productComment->setGrade($averageGrade);
    }

    /**
     * Manual validation for now, this would be nice to use Symfony validator with the annotation
     *
     * @param ProductComment $productComment
     *
     * @return array
     */
    private function validateComment(ProductComment $productComment)
    {
        $errors = [];
        if (empty($productComment->getTitle())) {
            $errors[] = $this->getTranslator()->trans('Title cannot be empty');
        } elseif (strlen($productComment->getTitle()) > 64) {
            $errors[] = $this->getTranslator()->trans('Title cannot be more than %s characters', [64]);
        }

        if (!$productComment->getCustomerId()) {
            if (empty($productComment->getCustomerName())) {
                $errors[] = $this->getTranslator()->trans('Customer name cannot be empty');
            } elseif (strlen($productComment->getCustomerName()) > 64) {
                $errors[] = $this->getTranslator()->trans('Customer name cannot be more than %s characters', [64]);
            }
        }

        return $errors;
    }
}
