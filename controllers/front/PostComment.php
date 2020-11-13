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
use Doctrine\ORM\EntityManagerInterface;
use PrestaShop\Module\ProductComment\Entity\ProductComment;
use PrestaShop\Module\ProductComment\Entity\ProductCommentCriterion;
use PrestaShop\Module\ProductComment\Entity\ProductCommentGrade;
use PrestaShop\Module\ProductComment\Repository\ProductCommentRepository;

class ProductCommentsPostCommentModuleFrontController extends ModuleFrontController
{
    public function display()
    {
        header('Content-Type: application/json');
        if (!(int) $this->context->cookie->id_customer && !Configuration::get('PRODUCT_COMMENTS_ALLOW_GUESTS')) {
            $this->ajaxRender(
                json_encode(
                    [
                        'success' => false,
                        'error' => $this->trans(
                            'You need to be [1]logged in[/1] or [2]create an account[/2] to post your review.',
                            [
                                '[1]' => '<a href="' . $this->context->link->getPageLink('my-account') . '">',
                                '[/1]' => '</a>',
                                '[2]' => '<a href="' . $this->context->link->getPageLink('authentication&create_account=1') . '">',
                                '[/2]' => '</a>',
                            ],
                            'Modules.Productcomments.Shop'
                        ),
                    ]
                )
            );

            return false;
        }

        $id_product = (int) Tools::getValue('id_product');
        $comment_title = Tools::getValue('comment_title');
        $comment_content = Tools::getValue('comment_content');
        $customer_name = Tools::getValue('customer_name');
        $criterions = Tools::getValue('criterion');

        /** @var ProductCommentRepository $productCommentRepository */
        $productCommentRepository = $this->context->controller->getContainer()->get('product_comment_repository');
        $isPostAllowed = $productCommentRepository->isPostAllowed(
            $id_product,
            (int) $this->context->cookie->id_customer,
            (int) $this->context->cookie->id_guest
        );
        if (!$isPostAllowed) {
            $this->ajaxRender(
                json_encode(
                    [
                        'success' => false,
                        'error' => $this->trans('You are not allowed to post a review at the moment, please try again later.', [], 'Modules.Productcomments.Shop'),
                    ]
                )
            );

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
        $errors = $this->validateComment($productComment);
        if (!empty($errors)) {
            $this->ajaxRender(
                json_encode(
                    [
                        'success' => false,
                        'errors' => $errors,
                    ]
                )
            );

            return false;
        }

        $entityManager->flush();

        $this->ajaxRender(
            json_encode(
                [
                    'success' => true,
                    'product_comment' => $productComment->toArray(),
                ]
            )
        );
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
            $criterion = $criterionRepository->findOneBy(['id' => $criterionId]);
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
            $errors[] = $this->trans('Title cannot be empty', [], 'Modules.Productcomments.Shop');
        } elseif (strlen($productComment->getTitle()) > ProductComment::TITLE_MAX_LENGTH) {
            $errors[] = $this->trans('Title cannot be more than %s characters', [ProductComment::TITLE_MAX_LENGTH], 'Modules.Productcomments.Shop');
        }

        if (!$productComment->getCustomerId()) {
            if (empty($productComment->getCustomerName())) {
                $errors[] = $this->trans('Customer name cannot be empty', [], 'Modules.Productcomments.Shop');
            } elseif (strlen($productComment->getCustomerName()) > ProductComment::CUSTOMER_NAME_MAX_LENGTH) {
                $errors[] = $this->trans('Customer name cannot be more than %s characters', [ProductComment::CUSTOMER_NAME_MAX_LENGTH], 'Modules.Productcomments.Shop');
            }
        }

        return $errors;
    }
}
