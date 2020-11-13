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
use PrestaShop\Module\ProductComment\Entity\ProductCommentUsefulness;
use PrestaShop\Module\ProductComment\Repository\ProductCommentRepository;

class ProductCommentsUpdateCommentUsefulnessModuleFrontController extends ModuleFrontController
{
    public function display()
    {
        header('Content-Type: application/json');

        if (!Configuration::get('PRODUCT_COMMENTS_USEFULNESS')) {
            $this->ajaxRender(
                json_encode(
                    [
                        'success' => false,
                        'error' => $this->trans('This feature is not enabled.', [], 'Modules.Productcomments.Shop'),
                    ]
                )
            );

            return false;
        }

        $customerId = (int) $this->context->cookie->id_customer;
        if (!$customerId) {
            $this->ajaxRender(
                json_encode(
                    [
                        'success' => false,
                        'error' => $this->trans(
                            'You need to be [1]logged in[/1] or [2]create an account[/2] to give your appreciation of a review.',
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

        $id_product_comment = (int) Tools::getValue('id_product_comment');
        $usefulness = (bool) Tools::getValue('usefulness');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->container->get('doctrine.orm.entity_manager');

        $productCommentEntityRepository = $entityManager->getRepository(ProductComment::class);
        /** @var ProductComment|null $productComment */
        $productComment = $productCommentEntityRepository->findOneBy(['id' => $id_product_comment]);
        if (!$productComment) {
            $this->ajaxRender(
                json_encode(
                    [
                        'success' => false,
                        'error' => $this->trans('Cannot find the requested product review.', [], 'Modules.Productcomments.Shop'),
                    ]
                )
            );

            return false;
        }

        $productCommentUsefulnesRepository = $entityManager->getRepository(ProductCommentUsefulness::class);
        /** @var ProductCommentUsefulness|null $productCommentUsefulness */
        $productCommentUsefulness = $productCommentUsefulnesRepository->findOneBy([
            'comment' => $id_product_comment,
            'customerId' => $customerId,
        ]);
        if ($productCommentUsefulness) {
            $productCommentUsefulness->setUsefulness($usefulness);
        } else {
            $productCommentUsefulness = new ProductCommentUsefulness(
                $productComment,
                $customerId,
                $usefulness
            );
            $entityManager->persist($productCommentUsefulness);
        }

        $entityManager->flush();

        /** @var ProductCommentRepository $productCommentRepository */
        $productCommentRepository = $this->context->controller->getContainer()->get('product_comment_repository');
        $commentUsefulness = $productCommentRepository->getProductCommentUsefulness($id_product_comment);

        $this->ajaxRender(
            json_encode(
                array_merge(
                    [
                        'success' => true,
                        'id_product_comment' => $id_product_comment,
                    ],
                    $commentUsefulness
                )
            )
        );
    }
}
