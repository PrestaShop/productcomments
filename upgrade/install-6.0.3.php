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
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_6_0_3($object)
{
    return Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'product_comment` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci')
        && Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'product_comment_criterion` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci')
        && Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'product_comment_criterion_product` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci')
        && Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'product_comment_criterion_lang` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci')
        && Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'product_comment_criterion_category` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci')
        && Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'product_comment_grade` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci')
        && Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'product_comment_usefulness` CHARACTER SET = utf8mb4 COLLATE utf8mb4_general_ci')
        && Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'product_comment_report` CHARACTER SET = utf8mb4 COLLATE utf8mb4_general_ci');
}
