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
if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Adapter\Presenter\Product\ProductLazyArray;
use PrestaShop\Module\ProductComment\Repository\ProductCommentCriterionRepository;
use PrestaShop\Module\ProductComment\Repository\ProductCommentRepository;
use PrestaShop\Module\ProductComment\Addons\CategoryFetcher;

class ProductComments extends Module
{
    const INSTALL_SQL_FILE = 'install.sql';

    private $_html = '';

    private $_productCommentsCriterionTypes = array();
    private $_baseUrl;

    public function __construct()
    {
        $this->name = 'productcomments';
        $this->tab = 'front_office_features';
        $this->version = '4.0.1';
        $this->author = 'PrestaShop';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->secure_key = Tools::encrypt($this->name);

        $this->displayName = $this->trans('Product Comments', [], 'Modules.Productcomments.Admin');
        $this->description = $this->trans('Allows users to post reviews and rate products on specific criteria.', [], 'Modules.Productcomments.Admin');

        $this->ps_versions_compliancy = array('min' => '1.7.6', 'max' => _PS_VERSION_);
    }

    public function install($keep = true)
    {
        if ($keep) {
            if (!file_exists(dirname(__FILE__) . '/' . self::INSTALL_SQL_FILE)) {
                return false;
            } elseif (!$sql = file_get_contents(dirname(__FILE__) . '/' . self::INSTALL_SQL_FILE)) {
                return false;
            }
            $sql = str_replace(array('PREFIX_', 'ENGINE_TYPE'), array(_DB_PREFIX_, _MYSQL_ENGINE_), $sql);
            $sql = preg_split("/;\s*[\r\n]+/", trim($sql));

            foreach ($sql as $query) {
                if (!Db::getInstance()->execute(trim($query))) {
                    return false;
                }
            }
        }

        if (parent::install() == false ||
            !$this->registerHook('displayFooterProduct') || //Product page footer
            !$this->registerHook('header') || //Adds css and javascript on front
            !$this->registerHook('displayProductListReviews') || //Product list miniature
            !$this->registerHook('displayProductAdditionalInfo') || //Display info in checkout column

            !$this->registerHook('registerGDPRConsent') ||
            !$this->registerHook('actionDeleteGDPRCustomer') ||
            !$this->registerHook('actionExportGDPRData') ||

            !Configuration::updateValue('PRODUCT_COMMENTS_MINIMAL_TIME', 30) ||
            !Configuration::updateValue('PRODUCT_COMMENTS_ALLOW_GUESTS', 0) ||
            !Configuration::updateValue('PRODUCT_COMMENTS_USEFULNESS', 1) ||
            !Configuration::updateValue('PRODUCT_COMMENTS_COMMENTS_PER_PAGE', 5) ||
            !Configuration::updateValue('PRODUCT_COMMENTS_MODERATE', 1)) {
            return false;
        }

        return true;
    }

    public function uninstall($keep = true)
    {
        if (!parent::uninstall() || ($keep && !$this->deleteTables()) ||
            !Configuration::deleteByName('PRODUCT_COMMENTS_MODERATE') ||
            !Configuration::deleteByName('PRODUCT_COMMENTS_COMMENTS_PER_PAGE') ||
            !Configuration::deleteByName('PRODUCT_COMMENTS_ALLOW_GUESTS') ||
            !Configuration::deleteByName('PRODUCT_COMMENTS_USEFULNESS') ||
            !Configuration::deleteByName('PRODUCT_COMMENTS_MINIMAL_TIME') ||

            !$this->unregisterHook('registerGDPRConsent') ||
            !$this->unregisterHook('actionDeleteGDPRCustomer') ||
            !$this->unregisterHook('actionExportGDPRData') ||

            !$this->unregisterHook('displayProductAdditionalInfo') ||
            !$this->unregisterHook('header') ||
            !$this->unregisterHook('displayFooterProduct') ||
            !$this->unregisterHook('displayProductListReviews')) {
            return false;
        }

        return true;
    }

    public function reset()
    {
        if (!$this->uninstall(false)) {
            return false;
        }
        if (!$this->install(false)) {
            return false;
        }

        return true;
    }

    public function deleteTables()
    {
        return Db::getInstance()->execute('
			DROP TABLE IF EXISTS
			`' . _DB_PREFIX_ . 'product_comment`,
			`' . _DB_PREFIX_ . 'product_comment_criterion`,
			`' . _DB_PREFIX_ . 'product_comment_criterion_product`,
			`' . _DB_PREFIX_ . 'product_comment_criterion_lang`,
			`' . _DB_PREFIX_ . 'product_comment_criterion_category`,
			`' . _DB_PREFIX_ . 'product_comment_grade`,
			`' . _DB_PREFIX_ . 'product_comment_usefulness`,
			`' . _DB_PREFIX_ . 'product_comment_report`');
    }

    public function getCacheId($id_product = null)
    {
        return parent::getCacheId() . '|' . (int) $id_product;
    }

    protected function _postProcess()
    {
        if (Tools::isSubmit('submitModerate')) {
            Configuration::updateValue('PRODUCT_COMMENTS_MODERATE', (int) Tools::getValue('PRODUCT_COMMENTS_MODERATE'));
            Configuration::updateValue('PRODUCT_COMMENTS_ALLOW_GUESTS', (int) Tools::getValue('PRODUCT_COMMENTS_ALLOW_GUESTS'));
            Configuration::updateValue('PRODUCT_COMMENTS_USEFULNESS', (int) Tools::getValue('PRODUCT_COMMENTS_USEFULNESS'));
            Configuration::updateValue('PRODUCT_COMMENTS_COMMENTS_PER_PAGE', (int) Tools::getValue('PRODUCT_COMMENTS_COMMENTS_PER_PAGE'));
            Configuration::updateValue('PRODUCT_COMMENTS_MINIMAL_TIME', (int) Tools::getValue('PRODUCT_COMMENTS_MINIMAL_TIME'));
            $this->_html .= '<div class="conf confirm alert alert-success">' . $this->trans('Settings updated', [], 'Modules.Productcomments.Admin') . '</div>';
        } elseif (Tools::isSubmit('productcomments')) {
            $id_product_comment = (int) Tools::getValue('id_product_comment');
            $comment = new ProductComment($id_product_comment);
            $comment->validate();
            ProductComment::deleteReports($id_product_comment);
        } elseif (Tools::isSubmit('deleteproductcomments')) {
            $id_product_comment = (int) Tools::getValue('id_product_comment');
            $comment = new ProductComment($id_product_comment);
            $comment->delete();
        } elseif (Tools::isSubmit('submitEditCriterion')) {
            $criterion = new ProductCommentCriterion((int) Tools::getValue('id_product_comment_criterion'));
            $criterion->id_product_comment_criterion_type = Tools::getValue('id_product_comment_criterion_type');
            $criterion->active = Tools::getValue('active');

            $languages = Language::getLanguages();
            $name = array();
            foreach ($languages as $key => $value) {
                $name[$value['id_lang']] = Tools::getValue('name_' . $value['id_lang']);
            }
            $criterion->name = $name;

            if (!$criterion->validateFields(false) || !$criterion->validateFieldsLang(false)) {
                $this->_html .= '<div class="conf confirm alert alert-danger">' . $this->trans('The criterion cannot be saved', [], 'Modules.Productcomments.Admin') . '</div>';
            } else {
                $criterion->save();

                // Clear before reinserting data
                $criterion->deleteCategories();
                $criterion->deleteProducts();
                if ($criterion->id_product_comment_criterion_type == 2) {
                    if ($categories = Tools::getValue('categoryBox')) {
                        if (count($categories)) {
                            foreach ($categories as $id_category) {
                                $criterion->addCategory((int) $id_category);
                            }
                        }
                    }
                } elseif ($criterion->id_product_comment_criterion_type == 3) {
                    if ($products = Tools::getValue('ids_product')) {
                        if (count($products)) {
                            foreach ($products as $product) {
                                $criterion->addProduct((int) $product);
                            }
                        }
                    }
                }
                if ($criterion->save()) {
                    Tools::redirectAdmin(Context::getContext()->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name, 'conf' => 4]));
                } else {
                    $this->_html .= '<div class="conf confirm alert alert-danger">' . $this->trans('The criterion cannot be saved', [], 'Modules.Productcomments.Admin') . '</div>';
                }
            }
        } elseif (Tools::isSubmit('deleteproductcommentscriterion')) {
            $productCommentCriterion = new ProductCommentCriterion((int) Tools::getValue('id_product_comment_criterion'));
            if ($productCommentCriterion->id) {
                if ($productCommentCriterion->delete()) {
                    $this->_html .= '<div class="conf confirm alert alert-success">' . $this->trans('Criterion deleted', [], 'Modules.Productcomments.Admin') . '</div>';
                }
            }
        } elseif (Tools::isSubmit('statusproductcommentscriterion')) {
            $criterion = new ProductCommentCriterion((int) Tools::getValue('id_product_comment_criterion'));
            if ($criterion->id) {
                $criterion->active = (int) (!$criterion->active);
                $criterion->save();
            }
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name, 'tab_module' => $this->tab, 'conf' => 4, 'module_name' => $this->name]));
        } elseif ($id_product_comment = (int) Tools::getValue('approveComment')) {
            $comment = new ProductComment($id_product_comment);
            $comment->validate();
        } elseif ($id_product_comment = (int) Tools::getValue('noabuseComment')) {
            ProductComment::deleteReports($id_product_comment);
        }

        $this->_clearcache('productcomments_reviews.tpl');
    }

    public function getContent()
    {
        include_once dirname(__FILE__) . '/ProductComment.php';
        include_once dirname(__FILE__) . '/ProductCommentCriterion.php';

        $this->_html = '';
        if (Tools::isSubmit('updateproductcommentscriterion')) {
            $this->_html .= $this->renderCriterionForm((int) Tools::getValue('id_product_comment_criterion'));
        } else {
            $this->_postProcess();
            $this->_html .= $this->renderConfigForm();
            $this->_html .= $this->renderModerateLists();
            $this->_html .= $this->renderCriterionList();
            $this->_html .= $this->renderCommentsList();

            $this->context->controller->addCss($this->_path . 'views/css/module-addons-suggestion.css');
            $this->_html .= $this->renderAddonsSuggestion();
        }

        $this->_setBaseUrl();
        $this->_productCommentsCriterionTypes = ProductCommentCriterion::getTypes();

        $this->context->controller->addJs($this->_path . 'js/moderate.js');

        return $this->_html;
    }

    private function _setBaseUrl()
    {
        $this->_baseUrl = 'index.php?';
        foreach ($_GET as $k => $value) {
            if (!in_array($k, array('deleteCriterion', 'editCriterion'))) {
                $this->_baseUrl .= $k . '=' . $value . '&';
            }
        }
        $this->_baseUrl = rtrim($this->_baseUrl, '&');
    }

    public function renderConfigForm()
    {
        $fields_form_1 = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('Configuration', [], 'Modules.Productcomments.Admin'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'is_bool' => true, //retro compat 1.5
                        'label' => $this->trans('All reviews must be validated by an employee', [], 'Modules.Productcomments.Admin'),
                        'name' => 'PRODUCT_COMMENTS_MODERATE',
                        'values' => array(
                                        array(
                                            'id' => 'active_on',
                                            'value' => 1,
                                            'label' => $this->trans('Enabled', [], 'Modules.Productcomments.Admin'),
                                        ),
                                        array(
                                            'id' => 'active_off',
                                            'value' => 0,
                                            'label' => $this->trans('Disabled', [], 'Modules.Productcomments.Admin'),
                                        ),
                                    ),
                    ),
                    array(
                        'type' => 'switch',
                        'is_bool' => true, //retro compat 1.5
                        'label' => $this->trans('Allow guest reviews', [], 'Modules.Productcomments.Admin'),
                        'name' => 'PRODUCT_COMMENTS_ALLOW_GUESTS',
                        'values' => array(
                                        array(
                                            'id' => 'active_on',
                                            'value' => 1,
                                            'label' => $this->trans('Enabled', [], 'Modules.Productcomments.Admin'),
                                        ),
                                        array(
                                            'id' => 'active_off',
                                            'value' => 0,
                                            'label' => $this->trans('Disabled', [], 'Modules.Productcomments.Admin'),
                                        ),
                                    ),
                    ),
                    array(
                        'type' => 'switch',
                        'is_bool' => true, //retro compat 1.5
                        'label' => $this->trans('Enable upvotes / downvotes on reviews', [], 'Modules.Productcomments.Admin'),
                        'name' => 'PRODUCT_COMMENTS_USEFULNESS',
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Enabled', [], 'Modules.Productcomments.Admin'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('Disabled', [], 'Modules.Productcomments.Admin'),
                            ),
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Minimum time between 2 reviews from the same user', [], 'Modules.Productcomments.Admin'),
                        'name' => 'PRODUCT_COMMENTS_MINIMAL_TIME',
                        'class' => 'fixed-width-xs',
                        'suffix' => 'seconds',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Number of comments per page', [], 'Modules.Productcomments.Admin'),
                        'name' => 'PRODUCT_COMMENTS_COMMENTS_PER_PAGE',
                        'class' => 'fixed-width-xs',
                        'suffix' => 'comments',
                    ),
                ),
            'submit' => array(
                'title' => $this->trans('Save', [], 'Modules.Productcomments.Admin'),
                'class' => 'btn btn-default pull-right',
                'name' => 'submitModerate',
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->name;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->module = $this;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitProducCommentsConfiguration';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false, [], ['configure' => $this->name, 'tab_module' => $this->tab, 'module_name' => $this->name]);
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form_1));
    }

    public function renderModerateLists()
    {
        require_once dirname(__FILE__) . '/ProductComment.php';
        $return = null;

        if (Configuration::get('PRODUCT_COMMENTS_MODERATE')) {
            $comments = ProductComment::getByValidate(0, false);

            $fields_list = $this->getStandardFieldList();

            if (version_compare(_PS_VERSION_, '1.6', '<')) {
                $return .= '<h1>' . $this->trans('Reviews waiting for approval', [], 'Modules.Productcomments.Admin') . '</h1>';
                $actions = array('enable', 'delete');
            } else {
                $actions = array('approve', 'delete');
            }

            $helper = new HelperList();
            $helper->list_id = 'form-productcomments-moderate-list';
            $helper->shopLinkType = '';
            $helper->simple_header = true;
            $helper->actions = $actions;
            $helper->show_toolbar = false;
            $helper->module = $this;
            $helper->listTotal = count($comments);
            $helper->identifier = 'id_product_comment';
            $helper->title = $this->trans('Reviews waiting for approval', [], 'Modules.Productcomments.Admin');
            $helper->table = $this->name;
            $helper->token = Tools::getAdminTokenLite('AdminModules');
            $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

            $return .= $helper->generateList($comments, $fields_list);
        }

        $comments = ProductComment::getReportedComments();

        $fields_list = $this->getStandardFieldList();

        $actions = array('delete', 'noabuse');

        $helper = new HelperList();
        $helper->list_id = 'form-productcomments-reported-list';
        $helper->shopLinkType = '';
        $helper->simple_header = true;
        $helper->actions = $actions;
        $helper->show_toolbar = false;
        $helper->module = $this;
        $helper->listTotal = count($comments);
        $helper->identifier = 'id_product_comment';
        $helper->title = $this->trans('Reported Reviews', [], 'Modules.Productcomments.Admin');
        $helper->table = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        $return .= $helper->generateList($comments, $fields_list);

        return $return;
    }

    /**
     * Method used by the HelperList to render the approve link
     *
     * @param $token
     * @param $id
     * @param null $name
     *
     * @return mixed
     */
    public function displayApproveLink($token, $id, $name = null)
    {
        $this->smarty->assign(array(
            'href' => $this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name, 'module_name' => $this->name, 'approveComment' => $id]),
            'action' => $this->trans('Approve', [], 'Modules.Productcomments.Admin'),
        ));

        return $this->display(__FILE__, 'views/templates/admin/list_action_approve.tpl');
    }

    /**
     * Method used by the HelperList to render the approve link
     *
     * @param $token
     * @param $id
     * @param null $name
     *
     * @return mixed
     */
    public function displayNoabuseLink($token, $id, $name = null)
    {
        $this->smarty->assign(array(
            'href' => $this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name, 'module_name' => $this->name, 'noabuseComment' => $id]),
            'action' => $this->trans('Not abusive', [], 'Modules.Productcomments.Admin'),
        ));

        return $this->display(__FILE__, 'views/templates/admin/list_action_noabuse.tpl');
    }

    public function renderCriterionList()
    {
        include_once dirname(__FILE__) . '/ProductCommentCriterion.php';

        $criterions = ProductCommentCriterion::getCriterions($this->context->language->id, false, false);

        $fields_list = array(
            'id_product_comment_criterion' => array(
                'title' => $this->trans('ID', [], 'Modules.Productcomments.Admin'),
                'type' => 'text',
            ),
            'name' => array(
                'title' => $this->trans('Name', [], 'Modules.Productcomments.Admin'),
                'type' => 'text',
            ),
            'type_name' => array(
                'title' => $this->trans('Type', [], 'Modules.Productcomments.Admin'),
                'type' => 'text',
            ),
            'active' => array(
                'title' => $this->trans('Status', [], 'Modules.Productcomments.Admin'),
                'active' => 'status',
                'type' => 'bool',
            ),
        );

        $helper = new HelperList();
        $helper->shopLinkType = '';
        $helper->simple_header = false;
        $helper->actions = array('edit', 'delete');
        $helper->show_toolbar = true;
        $helper->toolbar_btn['new'] = array(
            'href' => $this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name, 'module_name' => $this->name, 'updateproductcommentscriterion' => '']),
            'desc' => $this->trans('Add New Criterion', [], 'Modules.Productcomments.Admin'),
        );
        $helper->module = $this;
        $helper->identifier = 'id_product_comment_criterion';
        $helper->title = $this->trans('Review Criteria', [], 'Modules.Productcomments.Admin');
        $helper->table = $this->name . 'criterion';
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        return $helper->generateList($criterions, $fields_list);
    }

    public function renderCommentsList()
    {
        require_once dirname(__FILE__) . '/ProductComment.php';

        $comments = ProductComment::getByValidate(1, false);
        $moderate = Configuration::get('PRODUCT_COMMENTS_MODERATE');
        if (empty($moderate)) {
            $comments = array_merge($comments, ProductComment::getByValidate(0, false));
        }

        $fields_list = $this->getStandardFieldList();

        $helper = new HelperList();
        $helper->list_id = 'form-productcomments-list';
        $helper->shopLinkType = '';
        $helper->simple_header = true;
        $helper->actions = array('delete');
        $helper->show_toolbar = false;
        $helper->module = $this;
        $helper->listTotal = count($comments);
        $helper->identifier = 'id_product_comment';
        $helper->title = $this->trans('Approved Reviews', [], 'Modules.Productcomments.Admin');
        $helper->table = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        return $helper->generateList($comments, $fields_list);
    }

    public function renderAddonsSuggestion()
    {
        $categoryFetcher = new CategoryFetcher(
            480,
            [
                'name' => 'Customer reviews',
                'link' => '/en/480-customer-reviews',
                'description' => '<h2>Display customer reviews on your store!</h2>Customer reviews reassure your visitors and help you improve conversion! Encourage your customers to leave a review, display them, and do not forget to use rich snippets to show your products’ satisfaction ratings on search engines: they will be more visible!',
            ]
        );
        $category = $categoryFetcher->getData($this->context->language->iso_code);
        $this->context->smarty->assign(array(
            'addons_category' => $category,
        ));

        return $this->context->smarty->fetch('module:productcomments/views/templates/admin/addons-suggestion.tpl');
    }

    public function getConfigFieldsValues()
    {
        return array(
            'PRODUCT_COMMENTS_MODERATE' => Tools::getValue('PRODUCT_COMMENTS_MODERATE', Configuration::get('PRODUCT_COMMENTS_MODERATE')),
            'PRODUCT_COMMENTS_ALLOW_GUESTS' => Tools::getValue('PRODUCT_COMMENTS_ALLOW_GUESTS', Configuration::get('PRODUCT_COMMENTS_ALLOW_GUESTS')),
            'PRODUCT_COMMENTS_USEFULNESS' => Tools::getValue('PRODUCT_COMMENTS_USEFULNESS', Configuration::get('PRODUCT_COMMENTS_USEFULNESS')),
            'PRODUCT_COMMENTS_MINIMAL_TIME' => Tools::getValue('PRODUCT_COMMENTS_MINIMAL_TIME', Configuration::get('PRODUCT_COMMENTS_MINIMAL_TIME')),
            'PRODUCT_COMMENTS_COMMENTS_PER_PAGE' => Tools::getValue('PRODUCT_COMMENTS_COMMENTS_PER_PAGE', Configuration::get('PRODUCT_COMMENTS_COMMENTS_PER_PAGE')),
        );
    }

    public function getCriterionFieldsValues($id = 0)
    {
        $criterion = new ProductCommentCriterion($id);

        return array(
                    'name' => $criterion->name,
                    'id_product_comment_criterion_type' => $criterion->id_product_comment_criterion_type,
                    'active' => $criterion->active,
                    'id_product_comment_criterion' => $criterion->id,
                );
    }

    public function getStandardFieldList()
    {
        return array(
            'id_product_comment' => array(
                'title' => $this->trans('ID', [], 'Modules.Productcomments.Admin'),
                'type' => 'text',
            ),
            'title' => array(
                'title' => $this->trans('Review title', [], 'Modules.Productcomments.Admin'),
                'type' => 'text',
            ),
            'content' => array(
                'title' => $this->trans('Review', [], 'Modules.Productcomments.Admin'),
                'type' => 'text',
            ),
            'grade' => array(
                'title' => $this->trans('Rating', [], 'Modules.Productcomments.Admin'),
                'type' => 'text',
                'suffix' => '/5',
            ),
            'customer_name' => array(
                'title' => $this->trans('Author', [], 'Modules.Productcomments.Admin'),
                'type' => 'text',
            ),
            'name' => array(
                'title' => $this->trans('Product', [], 'Modules.Productcomments.Admin'),
                'type' => 'text',
            ),
            'date_add' => array(
                'title' => $this->trans('Time of publication', [], 'Modules.Productcomments.Admin'),
                'type' => 'date',
            ),
        );
    }

    public function renderCriterionForm($id_criterion = 0)
    {
        $types = ProductCommentCriterion::getTypes();
        $query = array();
        foreach ($types as $key => $value) {
            $query[] = array(
                    'id' => $key,
                    'label' => $value,
                );
        }

        $criterion = new ProductCommentCriterion((int) $id_criterion);
        $selected_categories = $criterion->getCategories();

        $product_table_values = Product::getSimpleProducts($this->context->language->id);
        $selected_products = $criterion->getProducts();
        foreach ($product_table_values as $key => $product) {
            if (false !== array_search($product['id_product'], $selected_products)) {
                $product_table_values[$key]['selected'] = 1;
            }
        }

        if (version_compare(_PS_VERSION_, '1.6', '<')) {
            $field_category_tree = array(
                                    'type' => 'categories_select',
                                    'name' => 'categoryBox',
                                    'label' => $this->trans('Criterion will be restricted to the following categories', [], 'Modules.Productcomments.Admin'),
                                    'category_tree' => $this->initCategoriesAssociation(null, $id_criterion),
                                );
        } else {
            $field_category_tree = array(
                            'type' => 'categories',
                            'label' => $this->trans('Criterion will be restricted to the following categories', [], 'Modules.Productcomments.Admin'),
                            'name' => 'categoryBox',
                            'desc' => $this->trans('Mark the boxes of categories to which this criterion applies.', [], 'Modules.Productcomments.Admin'),
                            'tree' => array(
                                'use_search' => false,
                                'id' => 'categoryBox',
                                'use_checkbox' => true,
                                'selected_categories' => $selected_categories,
                            ),
                            //retro compat 1.5 for category tree
                            'values' => array(
                                'trads' => array(
                                    'Root' => Category::getTopCategory(),
                                    'selected' => $this->trans('Selected', [], 'Modules.Productcomments.Admin'),
                                    'Collapse All' => $this->trans('Collapse All', [], 'Modules.Productcomments.Admin'),
                                    'Expand All' => $this->trans('Expand All', [], 'Modules.Productcomments.Admin'),
                                    'Check All' => $this->trans('Check All', [], 'Modules.Productcomments.Admin'),
                                    'Uncheck All' => $this->trans('Uncheck All', [], 'Modules.Productcomments.Admin'),
                                ),
                                'selected_cat' => $selected_categories,
                                'input_name' => 'categoryBox[]',
                                'use_radio' => false,
                                'use_search' => false,
                                'disabled_categories' => array(),
                                'top_category' => Category::getTopCategory(),
                                'use_context' => true,
                            ),
                        );
        }

        $fields_form_1 = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('Add new criterion', [], 'Modules.Productcomments.Admin'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'hidden',
                        'name' => 'id_product_comment_criterion',
                    ),
                    array(
                        'type' => 'text',
                        'lang' => true,
                        'label' => $this->trans('Criterion name', [], 'Modules.Productcomments.Admin'),
                        'name' => 'name',
                        'desc' => $this->trans('Maximum length: %s characters', [ProductCommentCriterion::NAME_MAX_LENGTH], 'Modules.Productcomments.Admin'),
                    ),
                    array(
                        'type' => 'select',
                        'name' => 'id_product_comment_criterion_type',
                        'label' => $this->trans('Application scope of the criterion', [], 'Modules.Productcomments.Admin'),
                        'options' => array(
                                        'query' => $query,
                                        'id' => 'id',
                                        'name' => 'label',
                                    ),
                    ),
                    $field_category_tree,
                    array(
                        'type' => 'products',
                        'label' => $this->trans('The criterion will be restricted to the following products', [], 'Modules.Productcomments.Admin'),
                        'name' => 'ids_product',
                        'values' => $product_table_values,
                    ),
                    array(
                        'type' => 'switch',
                        'is_bool' => true, //retro compat 1.5
                        'label' => $this->trans('Active', [], 'Modules.Productcomments.Admin'),
                        'name' => 'active',
                        'values' => array(
                                        array(
                                            'id' => 'active_on',
                                            'value' => 1,
                                            'label' => $this->trans('Enabled', [], 'Modules.Productcomments.Admin'),
                                        ),
                                        array(
                                            'id' => 'active_off',
                                            'value' => 0,
                                            'label' => $this->trans('Disabled', [], 'Modules.Productcomments.Admin'),
                                        ),
                                    ),
                    ),
                ),
            'submit' => array(
                'title' => $this->trans('Save', [], 'Modules.Productcomments.Admin'),
                'class' => 'btn btn-default pull-right',
                'name' => 'submitEditCriterion',
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->name;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->module = $this;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitEditCriterion';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false, [], ['configure' => $this->name, 'tab_module' => $this->tab, 'module_name' => $this->name]);
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getCriterionFieldsValues($id_criterion),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form_1));
    }

    public function initCategoriesAssociation($id_root = null, $id_criterion = 0)
    {
        if (is_null($id_root)) {
            $id_root = Configuration::get('PS_ROOT_CATEGORY');
        }
        $id_shop = (int) Tools::getValue('id_shop');
        $shop = new Shop($id_shop);
        if ($id_criterion == 0) {
            $selected_cat = array();
        } else {
            $pdc_object = new ProductCommentCriterion($id_criterion);
            $selected_cat = $pdc_object->getCategories();
        }

        if (Shop::getContext() == Shop::CONTEXT_SHOP && Tools::isSubmit('id_shop')) {
            $root_category = new Category($shop->id_category);
        } else {
            $root_category = new Category($id_root);
        }
        $root_category = array('id_category' => $root_category->id, 'name' => $root_category->name[$this->context->language->id]);

        $helper = new Helper();

        return $helper->renderCategoryTree($root_category, $selected_cat, 'categoryBox', false, true);
    }

    public function hookActionDeleteGDPRCustomer($customer)
    {
        if (isset($customer['id'])) {
            /** @var ProductCommentRepository $productCommentRepository */
            $productCommentRepository = $this->context->controller->getContainer()->get('product_comment_repository');
            $productCommentRepository->cleanCustomerData($customer['id']);
        }

        return true;
    }

    public function hookActionExportGDPRData($customer)
    {
        if (isset($customer['id'])) {
            /** @var ProductCommentRepository $productCommentRepository */
            $productCommentRepository = $this->context->controller->getContainer()->get('product_comment_repository');
            $langId = isset($customer['id_lang']) ? $customer['id_lang'] : $this->context->language->id;

            return json_encode($productCommentRepository->getCustomerData($customer['id'], $langId));
        }
    }

    /**
     *  Inject the needed javascript and css files in the appropriate pages
     */
    public function hookHeader()
    {
        $jsList = [];
        $cssList = [];
        if ($this->context->controller instanceof ProductControllerCore ||
            $this->context->controller instanceof ProductListingFrontControllerCore ||
            $this->context->controller instanceof IndexControllerCore) {
            $cssList[] = '/modules/productcomments/views/css/productcomments.css';
            $jsList[] = '/modules/productcomments/views/js/jquery.rating.plugin.js';
        }
        if ($this->context->controller instanceof ProductControllerCore) {
            $jsList[] = '/modules/productcomments/views/js/post-comment.js';
            $jsList[] = '/modules/productcomments/views/js/list-comments.js';
            $jsList[] = '/modules/productcomments/views/js/jquery.simplePagination.js';
        }
        foreach ($cssList as $cssUrl) {
            $this->context->controller->registerStylesheet(sha1($cssUrl), $cssUrl, ['media' => 'all', 'priority' => 80]);
        }
        foreach ($jsList as $jsUrl) {
            $this->context->controller->registerJavascript(sha1($jsUrl), $jsUrl, ['position' => 'bottom', 'priority' => 80]);
        }
    }

    /**
     * Display the comment list with the post modal at the bottom of the page
     *
     * @param $params
     *
     * @return string
     *
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function hookDisplayFooterProduct($params)
    {
        return $this->renderProductCommentsList($params['product']) . $this->renderProductCommentModal($params['product']);
    }

    /**
     * Used to render the product comments list
     *
     * @param $product
     *
     * @return string
     *
     * @throws PrestaShopException
     * @throws SmartyException
     */
    private function renderProductCommentsList($product)
    {
        /** @var ProductCommentRepository $productCommentRepository */
        $productCommentRepository = $this->context->controller->getContainer()->get('product_comment_repository');

        $averageGrade = $productCommentRepository->getAverageGrade($product->getId(), Configuration::get('PRODUCT_COMMENTS_MODERATE'));
        $commentsNb = $productCommentRepository->getCommentsNumber($product->getId(), Configuration::get('PRODUCT_COMMENTS_MODERATE'));
        $isPostAllowed = $productCommentRepository->isPostAllowed($product->getId(), (int) $this->context->cookie->id_customer, (int) $this->context->cookie->id_guest);

        $this->context->smarty->assign(array(
            'post_allowed' => $isPostAllowed,
            'usefulness_enabled' => Configuration::get('PRODUCT_COMMENTS_USEFULNESS'),
            'average_grade' => $averageGrade,
            'nb_comments' => $commentsNb,
            'list_comments_url' => $this->context->link->getModuleLink('productcomments', 'ListComments', ['id_product' => $product->getId()]),
            'update_comment_usefulness_url' => $this->context->link->getModuleLink('productcomments', 'UpdateCommentUsefulness'),
            'report_comment_url' => $this->context->link->getModuleLink('productcomments', 'ReportComment'),
        ));

        return $this->context->smarty->fetch('module:productcomments/views/templates/hook/product-comments-list.tpl');
    }

    /**
     * Used to render the product modal
     *
     * @param ProductLazyArray $product
     *
     * @return string
     *
     * @throws PrestaShopException
     * @throws SmartyException
     */
    private function renderProductCommentModal($product)
    {

        /** @var ProductCommentCriterionRepository $criterionRepository */
        $criterionRepository = $this->context->controller->getContainer()->get('product_comment_criterion_repository');
        $criterions = $criterionRepository->getByProduct($product->getId(), $this->context->language->id);

        $this->context->smarty->assign(array(
            'logged' => (bool) $this->context->cookie->id_customer,
            'post_comment_url' => $this->context->link->getModuleLink('productcomments', 'PostComment', ['id_product' => $product->getId()]),
            'moderation_active' => (int) Configuration::get('PRODUCT_COMMENTS_MODERATE'),
            'criterions' => $criterions,
            'product' => $product,
        ));

        return $this->context->smarty->fetch('module:productcomments/views/templates/hook/post-comment-modal.tpl');
    }

    /**
     * Display the review in the product miniatures
     *
     * @param $params
     *
     * @return string
     *
     * @throws SmartyException
     */
    public function hookDisplayProductListReviews($params)
    {
        /** @var ProductLazyArray $product */
        $product = $params['product'];
        /** @var ProductCommentRepository $productCommentRepository */
        $productCommentRepository = $this->context->controller->getContainer()->get('product_comment_repository');
        $commentsNb = $productCommentRepository->getCommentsNumber($product->getId(), Configuration::get('PRODUCT_COMMENTS_MODERATE'));
        $averageGrade = $productCommentRepository->getAverageGrade($product->getId(), Configuration::get('PRODUCT_COMMENTS_MODERATE'));

        $this->context->smarty->assign(array(
            'product' => $product,
            'product_comment_grade_url' => $this->context->link->getModuleLink('productcomments', 'CommentGrade'),
            'nb_comments' => $commentsNb,
            'average_grade' => $averageGrade,
        ));

        return $this->context->smarty->fetch('module:productcomments/views/templates/hook/product-list-reviews.tpl');
    }

    /**
     * Display average grade and buttons in the product page under checkout and share buttons
     *
     * @param $params
     *
     * @return string
     *
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function hookDisplayProductAdditionalInfo($params)
    {
        /** @var ProductLazyArray $product */
        $product = $params['product'];
        /** @var ProductCommentRepository $productCommentRepository */
        $productCommentRepository = $this->context->controller->getContainer()->get('product_comment_repository');

        $averageGrade = $productCommentRepository->getAverageGrade($product->getId(), Configuration::get('PRODUCT_COMMENTS_MODERATE'));
        $commentsNb = $productCommentRepository->getCommentsNumber($product->getId(), Configuration::get('PRODUCT_COMMENTS_MODERATE'));
        $isPostAllowed = $productCommentRepository->isPostAllowed($product->getId(), (int) $this->context->cookie->id_customer, (int) $this->context->cookie->id_guest);

        $this->context->smarty->assign(array(
            'average_grade' => $averageGrade,
            'nb_comments' => $commentsNb,
            'post_allowed' => $isPostAllowed,
        ));

        if ('quickview' === Tools::getValue('action')) {
            return $this->context->smarty->fetch('module:productcomments/views/templates/hook/product-additional-info-quickview.tpl');
        }

        return $this->context->smarty->fetch('module:productcomments/views/templates/hook/product-additional-info.tpl');
    }
}
