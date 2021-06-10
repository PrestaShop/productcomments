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
     */
    public function addProduct($id_product)
    {
        if (!Validate::isUnsignedId($id_product)) {
            exit(Tools::displayError());
        }

        return Db::getInstance()->execute('
			INSERT INTO `' . _DB_PREFIX_ . 'product_comment_criterion_product` (`id_product_comment_criterion`, `id_product`)
			VALUES(' . (int) $this->id . ',' . (int) $id_product . ')
		');
    }

    /**
     * Link a Comment Criterion to a category
     *
     * @return bool succeed
     */
    public function addCategory($id_category)
    {
        if (!Validate::isUnsignedId($id_category)) {
            exit(Tools::displayError());
        }

        return Db::getInstance()->execute('
			INSERT INTO `' . _DB_PREFIX_ . 'product_comment_criterion_category` (`id_product_comment_criterion`, `id_category`)
			VALUES(' . (int) $this->id . ',' . (int) $id_category . ')
		');
    }

    /**
     * Add grade to a criterion
     *
     * @return bool succeed
     */
    public function addGrade($id_product_comment, $grade)
    {
        if (!Validate::isUnsignedId($id_product_comment)) {
            exit(Tools::displayError());
        }
        if ($grade < 0) {
            $grade = 0;
        } elseif ($grade > 10) {
            $grade = 10;
        }

        return Db::getInstance()->execute('
		INSERT INTO `' . _DB_PREFIX_ . 'product_comment_grade`
		(`id_product_comment`, `id_product_comment_criterion`, `grade`) VALUES(
		' . (int) ($id_product_comment) . ',
		' . (int) $this->id . ',
		' . (int) ($grade) . ')');
    }

    /**
     * Get criterion by Product
     *
     * @return array Criterion
     */
    public static function getByProduct($id_product, $id_lang)
    {
        if (!Validate::isUnsignedId($id_product) ||
            !Validate::isUnsignedId($id_lang)) {
            exit(Tools::displayError());
        }
        $alias = 'p';
        $table = '';
        // check if version > 1.5 to add shop association
        if (version_compare(_PS_VERSION_, '1.5', '>')) {
            $table = '_shop';
            $alias = 'ps';
        }

        $cache_id = 'ProductCommentCriterion::getByProduct_' . (int) $id_product . '-' . (int) $id_lang;
        if (!Cache::isStored($cache_id)) {
            $result = Db::getInstance()->executeS('
				SELECT pcc.`id_product_comment_criterion`, pccl.`name`
				FROM `' . _DB_PREFIX_ . 'product_comment_criterion` pcc
				LEFT JOIN `' . _DB_PREFIX_ . 'product_comment_criterion_lang` pccl
					ON (pcc.id_product_comment_criterion = pccl.id_product_comment_criterion)
				LEFT JOIN `' . _DB_PREFIX_ . 'product_comment_criterion_product` pccp
					ON (pcc.`id_product_comment_criterion` = pccp.`id_product_comment_criterion` AND pccp.`id_product` = ' . (int) $id_product . ')
				LEFT JOIN `' . _DB_PREFIX_ . 'product_comment_criterion_category` pccc
					ON (pcc.`id_product_comment_criterion` = pccc.`id_product_comment_criterion`)
				LEFT JOIN `' . _DB_PREFIX_ . 'product' . $table . '` ' . $alias . '
					ON (' . $alias . '.id_category_default = pccc.id_category AND ' . $alias . '.id_product = ' . (int) $id_product . ')
				WHERE pccl.`id_lang` = ' . (int) ($id_lang) . '
				AND (
					pccp.id_product IS NOT NULL
					OR ps.id_product IS NOT NULL
					OR pcc.id_product_comment_criterion_type = 1
				)
				AND pcc.active = 1
				GROUP BY pcc.id_product_comment_criterion
			');
            Cache::store($cache_id, $result);
        }

        return Cache::retrieve($cache_id);
    }

    /**
     * Get Criterions
     *
     * @return array Criterions
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
			WHERE pccl.`id_lang` = ' . (int) $id_lang . ($active ? ' AND active = 1' : '') . ($type ? ' AND id_product_comment_criterion_type = ' . (int) $type : '') . '
			ORDER BY pccl.`name` ASC';
        $criterions = Db::getInstance()->executeS($sql);

        $types = self::getTypes();
        foreach ($criterions as $key => $data) {
            $criterions[$key]['type_name'] = $types[$data['id_product_comment_criterion_type']];
        }

        return $criterions;
    }

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

    public function deleteCategories()
    {
        return Db::getInstance()->execute('
			DELETE FROM `' . _DB_PREFIX_ . 'product_comment_criterion_category`
			WHERE `id_product_comment_criterion` = ' . (int) $this->id);
    }

    public function deleteProducts()
    {
        return Db::getInstance()->execute('
			DELETE FROM `' . _DB_PREFIX_ . 'product_comment_criterion_product`
			WHERE `id_product_comment_criterion` = ' . (int) $this->id);
    }

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
