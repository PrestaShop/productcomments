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
use PrestaShop\Module\ProductComment\Repository\ProductCommentRepository;

class ProductCommentsListCommentsModuleFrontController extends ModuleFrontController
{
    public function display()
    {
        $idProduct = (int) Tools::getValue('id_product');
        $page = (int) Tools::getValue('page', 1);
        $isLastNameAnynomus = Configuration::get('PRODUCT_COMMENTS_ANONYMISATION');
        /** @var ProductCommentRepository $productCommentRepository */
        $productCommentRepository = $this->context->controller->getContainer()->get('product_comment_repository');

        $productComments = $productCommentRepository->paginate(
            $idProduct,
            $page,
            (int) Configuration::get('PRODUCT_COMMENTS_COMMENTS_PER_PAGE'),
            (bool) Configuration::get('PRODUCT_COMMENTS_MODERATE')
        );
        $productCommentsNb = $productCommentRepository->getCommentsNumber(
            $idProduct,
            (bool) Configuration::get('PRODUCT_COMMENTS_MODERATE')
        );

        $responseArray = [
            'comments_nb' => $productCommentsNb,
            'comments_per_page' => Configuration::get('PRODUCT_COMMENTS_COMMENTS_PER_PAGE'),
            'comments' => [],
        ];

        foreach ($productComments as $productComment) {
            $dateAdd = new \DateTime($productComment['date_add'], new \DateTimeZone('UTC'));
            $dateAdd->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            $dateFormatter = new \IntlDateFormatter(
                $this->context->language->locale,
                \IntlDateFormatter::SHORT,
                \IntlDateFormatter::SHORT
            );
            $productComment['customer_name'] = htmlentities($productComment['customer_name']);
            $productComment['title'] = htmlentities($productComment['title']);
            $productComment['content'] = htmlentities($productComment['content']);
            $productComment['date_add'] = $dateFormatter->format($dateAdd);

            if ($isLastNameAnynomus) {
                $productComment['lastname'] = substr($productComment['lastname'], 0, 1) . '.';
            }

            $usefulness = $productCommentRepository->getProductCommentUsefulness($productComment['id_product_comment']);
            $productComment = array_merge($productComment, $usefulness);
            if (empty($productComment['customer_name']) && !isset($productComment['firstname']) && !isset($productComment['lastname'])) {
                $productComment['customer_name'] = $this->trans('Deleted account', [], 'Modules.Productcomments.Shop');
            }

            $responseArray['comments'][] = $productComment;
        }

        header('Content-Type: application/json');
        $this->ajaxRender(
            json_encode(
                $responseArray
            )
        );
    }
}
