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
class ProductCommentCriterion extends ObjectModel
{
    const NAME_MAX_LENGTH = 64;

    public $id;
    public $id_product_comment_criterion_type;
    public $name;
    public $active = true;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'product_comment_criterion',
        'primary' => 'id_product_comment_criterion',
        'multilang' => true,
        'fields' => [
            'id_product_comment_criterion_type' => ['type' => self::TYPE_INT],
            'active' => ['type' => self::TYPE_BOOL],
            // Lang fields
            'name' => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isGenericName', 'required' => true, 'size' => self::NAME_MAX_LENGTH],
        ],
    ];

    /**
     * @deprecated 6.0.0 - migrated to src/Repository/ProductCommentCriterionRepository
     */
    public function delete()
    {
        if (!parent::delete()) {
            return false;
        }
        if ($this->id_product_comment_criterion_type == 2) {
            if (!Db::getInstance()->execute('
					DELETE FROM ' . _DB_PREFIX_ . 'product_comment_criterion_category
					WHERE id_product_comment_criterion=' . (int) $this->id)) {
                return false;
            }
        } elseif ($this->id_product_comment_criterion_type == 3) {
            if (!Db::getInstance()->execute('
					DELETE FROM ' . _DB_PREFIX_ . 'product_comment_criterion_product
					WHERE id_product_comment_criterion=' . (int) $this->id)) {
                return false;
            }
        }

        return Db::getInstance()->execute('
			DELETE FROM `' . _DB_PREFIX_ . 'product_comment_grade`
			WHERE `id_product_comment_criterion` = ' . (int) $this->id);
    }

    public function update($nullValues = false)
    {
        $previousUpdate = new self((int) $this->id);
        if (!parent::update($nullValues)) {
            return false;
        }
        if ($previousUpdate->id_product_comment_criterion_type != $this->id_product_comment_criterion_type) {
            if ($previousUpdate->id_product_comment_criterion_type == 2) {
                return Db::getInstance()->execute('
					DELETE FROM ' . _DB_PREFIX_ . 'product_comment_criterion_category
					WHERE id_product_comment_criterion = ' . (int) $previousUpdate->id);
            } elseif ($previousUpdate->id_product_comment_criterion_type == 3) {
                return Db::getInstance()->execute('
					DELETE FROM ' . _DB_PREFIX_ . 'product_comment_criterion_product
					WHERE id_product_comment_criterion = ' . (int) $previousUpdate->id);
            }
        }

        return true;
    }

    /**
     * Link a Comment Criterion to a product
     *
     * @return bool succeed
     *
     * @deprecated 6.0.0 - migrated to src/Repository/ProductCommentCriterionRepository
     */
    public function addProduct($id_product)
    {
        if (!Validate::isUnsignedId($id_product)) {
            exit(Tools::displayError());
        }

        return Db::getInstance()->execute('
			INSERT INTO `' . _DB_PREFIX_ . 'product_comment_criterion_product` (`id_product_comment_criterion`, `id_product`)
			VALUES(' . (int) $this->id . ',' . $id_product . ')
		');
    }

    /**
     * Link a Comment Criterion to a category
     *
     * @return bool succeed
     *
     * @deprecated 6.0.0 - migrated to src/Repository/ProductCommentCriterionRepository
     */
    public function addCategory($id_category)
    {
        if (!Validate::isUnsignedId($id_category)) {
            exit(Tools::displayError());
        }

        return Db::getInstance()->execute('
			INSERT INTO `' . _DB_PREFIX_ . 'product_comment_criterion_category` (`id_product_comment_criterion`, `id_category`)
			VALUES(' . (int) $this->id . ',' . $id_category . ')
		');
    }

    /**
     * Get Criterions
     *
     * @return array Criterions
     *
     * @deprecated 6.0.0
     */
    public static function getCriterions($id_lang, $type = false, $active = false)
    {
        if (!Validate::isUnsignedId($id_lang)) {
            exit(Tools::displayError());
        }

        $sql = '
			SELECT pcc.`id_product_comment_criterion`, pcc.id_product_comment_criterion_type, pccl.`name`, pcc.active
			FROM `' . _DB_PREFIX_ . 'product_comment_criterion` pcc
			JOIN `' . _DB_PREFIX_ . 'product_comment_criterion_lang` pccl ON (pcc.id_product_comment_criterion = pccl.id_product_comment_criterion)
			WHERE pccl.`id_lang` = ' . $id_lang . ($active ? ' AND active = 1' : '') . ($type ? ' AND id_product_comment_criterion_type = ' . (int) $type : '') . '
			ORDER BY pccl.`name` ASC';
        $criterions = Db::getInstance()->executeS($sql);

        $types = self::getTypes();
        foreach ($criterions as $key => $data) {
            $criterions[$key]['type_name'] = $types[$data['id_product_comment_criterion_type']];
        }

        return $criterions;
    }

    /**
     * @deprecated 6.0.0
     */
    public function getProducts()
    {
        $res = Db::getInstance()->executeS('
			SELECT pccp.id_product, pccp.id_product_comment_criterion
			FROM `' . _DB_PREFIX_ . 'product_comment_criterion_product` pccp
			WHERE pccp.id_product_comment_criterion = ' . (int) $this->id);
        $products = [];
        if ($res) {
            foreach ($res as $row) {
                $products[] = (int) $row['id_product'];
            }
        }

        return $products;
    }

    /**
     * @deprecated 6.0.0
     */
    public function getCategories()
    {
        $res = Db::getInstance()->executeS('
			SELECT pccc.id_category, pccc.id_product_comment_criterion
			FROM `' . _DB_PREFIX_ . 'product_comment_criterion_category` pccc
			WHERE pccc.id_product_comment_criterion = ' . (int) $this->id);
        $criterions = [];
        if ($res) {
            foreach ($res as $row) {
                $criterions[] = (int) $row['id_category'];
            }
        }

        return $criterions;
    }

    /**
     * @deprecated 6.0.0
     */
    public function deleteCategories()
    {
        return Db::getInstance()->execute('
			DELETE FROM `' . _DB_PREFIX_ . 'product_comment_criterion_category`
			WHERE `id_product_comment_criterion` = ' . (int) $this->id);
    }

    /**
     * @deprecated 6.0.0
     */
    public function deleteProducts()
    {
        return Db::getInstance()->execute('
			DELETE FROM `' . _DB_PREFIX_ . 'product_comment_criterion_product`
			WHERE `id_product_comment_criterion` = ' . (int) $this->id);
    }

    /**
     * @deprecated 6.0.0
     */
    public static function getTypes()
    {
        // Instance of module class for translations
        $module = new ProductComments();

        return [
            1 => $module->getTranslator()->trans('Valid for the entire catalog', [], 'Modules.Productcomments.Admin'),
            2 => $module->getTranslator()->trans('Restricted to some categories', [], 'Modules.Productcomments.Admin'),
            3 => $module->getTranslator()->trans('Restricted to some products', [], 'Modules.Productcomments.Admin'),
        ];
    }
}
