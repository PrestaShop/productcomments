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
use Doctrine\ORM\EntityManagerInterface;
use PrestaShop\Module\ProductComment\Entity\ProductComment;
use PrestaShop\Module\ProductComment\Entity\ProductCommentReport;

class ProductCommentsReportCommentModuleFrontController extends ModuleFrontController
{
    public function display()
    {
        $customerId = (int) $this->context->cookie->id_customer;
        if (!$customerId) {
            $this->ajaxRender(json_encode([
                'success' => false,
                'error' => $this->trans('You need to be logged in to report a review.', [], 'Modules.Productcomments.Shop'),
            ]));

            return false;
        }

        $id_product_comment = (int) Tools::getValue('id_product_comment');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        $productCommentEntityRepository = $entityManager->getRepository(ProductComment::class);

        $productComment = $productCommentEntityRepository->findOneById($id_product_comment);
        if (!$productComment) {
            $this->ajaxRender(json_encode([
                'success' => false,
                'error' => $this->trans('Cannot find the requested product review.', [], 'Modules.Productcomments.Shop'),
            ]));

            return false;
        }

        $productCommentAbuseRepository = $entityManager->getRepository(ProductCommentReport::class);
        /** @var ProductCommentReport $productCommentAbuse */
        $productCommentAbuse = $productCommentAbuseRepository->findOneBy([
            'comment' => $id_product_comment,
            'customerId' => $customerId,
        ]);
        if ($productCommentAbuse) {
            $this->ajaxRender(json_encode([
                'success' => false,
                'error' => $this->trans('You already reported this review as abusive.', [], 'Modules.Productcomments.Shop'),
            ]));

            return false;
        }

        $productCommentAbuse = new ProductCommentReport(
            $productComment,
            $customerId
        );
        $entityManager->persist($productCommentAbuse);
        $entityManager->flush();

        $this->ajaxRender(json_encode([
            'success' => true,
            'id_product_comment' => $id_product_comment,
        ]));
    }
}
