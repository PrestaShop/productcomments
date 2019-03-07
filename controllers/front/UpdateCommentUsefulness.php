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

use Doctrine\ORM\EntityManagerInterface;
use PrestaShop\Module\ProductComment\Entity\ProductComment;
use PrestaShop\Module\ProductComment\Entity\ProductCommentUsefulness;
use PrestaShop\Module\ProductComment\Repository\ProductCommentRepository;

class ProductCommentsUpdateCommentUsefulnessModuleFrontController extends ModuleFrontController
{
    public function display()
    {
        if (!Configuration::get('PRODUCT_COMMENTS_USEFULNESS')) {
            $this->ajaxRender(json_encode([
                'success' => false,
                'error' => $this->trans('This feature is not enabled.'),
            ]));

            return false;
        }

        $customerId = (int) $this->context->cookie->id_customer;
        if (!$customerId) {
            $this->ajaxRender(json_encode([
                'success' => false,
                'error' => $this->trans('You need to be logged in to give your appreciation of a review.'),
            ]));

            return false;
        }

        $id_product_comment = Tools::getValue('id_product_comment');
        $usefulness = Tools::getValue('usefulness');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        $productCommentEntityRepository = $entityManager->getRepository(ProductComment::class);

        $productComment = $productCommentEntityRepository->findOneById($id_product_comment);
        if (!$productComment) {
            $this->ajaxRender(json_encode([
                'success' => false,
                'error' => $this->trans('Could not find the requested product review.'),
            ]));

            return false;
        }

        $productCommentUsefulnesRepository = $entityManager->getRepository(ProductCommentUsefulness::class);
        /** @var ProductCommentUsefulness $productCommentUsefulness */
        $productCommentUsefulness = $productCommentUsefulnesRepository->findOneBy([
            'comment' => $id_product_comment,
            'customerId' => $customerId
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

        $this->ajaxRender(json_encode(array_merge([
            'success' => true,
            'id_product_comment' => $id_product_comment,
        ], $commentUsefulness)));
    }
}
