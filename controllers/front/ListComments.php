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

use PrestaShop\Module\ProductComment\Repository\ProductCommentRepository;

class ProductCommentsListCommentsModuleFrontController extends ModuleFrontController
{
    public function display()
    {
        $idProduct = Tools::getValue('id_product');
        $page = Tools::getValue('page', 1);
        /** @var ProductCommentRepository $productCommentRepository */
        $productCommentRepository = $this->context->controller->getContainer()->get('product_comment_repository');

        $productComments = $productCommentRepository->paginate(
            $idProduct,
            $page,
            Configuration::get('PRODUCT_COMMENTS_COMMENTS_PER_PAGE'),
            Configuration::get('PRODUCT_COMMENTS_MODERATE')
        );
        $productCommentsNb = $productCommentRepository->getCommentsNumber($idProduct, Configuration::get('PRODUCT_COMMENTS_MODERATE'));

        $responseArray = [
            'comments_nb' => $productCommentsNb,
            'comments_per_page' => Configuration::get('PRODUCT_COMMENTS_COMMENTS_PER_PAGE'),
            'comments' => [],
        ];

        foreach ($productComments as $productComment) {
            $dateAdd = new \DateTime($productComment['date_add'], new \DateTimeZone('UTC'));
            $dateAdd->setTimezone(new \DateTimeZone(date_default_timezone_get()));

            //todo: use cldr to format the date correctly
            $productComment['date_add'] = $dateAdd->format(\DateTime::ATOM);
            $usefulness = $productCommentRepository->getProductCommentUsefulness($productComment['id_product_comment']);
            $productComment = array_merge($productComment, $usefulness);
            $responseArray['comments'][] = $productComment;
        }

        $this->ajaxRender(json_encode($responseArray));
    }
}
