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

use PrestaShop\Module\ProductComment\Entity\ProductCommentCriterion;
use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class ProductComments extends Module implements WidgetInterface
{
    const INSTALL_SQL_FILE = 'install.sql';

    private $_html = '';

    private $_productCommentsCriterionTypes = [];
    private $_baseUrl;

    private $langId;
    private $shopId;

    public function __construct()
    {
        $this->name = 'productcomments';
        $this->tab = 'front_office_features';
        $this->version = '7.0.0';
        $this->author = 'PrestaShop';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Product Comments', [], 'Modules.Productcomments.Admin');
        $this->description = $this->trans('Allow users to post reviews on your products and/or rate them based on specific criteria.', [], 'Modules.Productcomments.Admin');

        $this->langId = $this->context->language->id;
        $this->shopId = $this->context->shop->id ? $this->context->shop->id : Configuration::get('PS_SHOP_DEFAULT');

        $this->ps_versions_compliancy = ['min' => '1.7.8', 'max' => _PS_VERSION_];
    }

    public function install($keep = true)
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        if ($keep) {
            if (!file_exists(dirname(__FILE__) . '/' . self::INSTALL_SQL_FILE)) {
                return false;
            } elseif (!$sql = file_get_contents(dirname(__FILE__) . '/' . self::INSTALL_SQL_FILE)) {
                return false;
            }
            $sql = str_replace(['PREFIX_', 'ENGINE_TYPE'], [_DB_PREFIX_, _MYSQL_ENGINE_], $sql);
            $sql = preg_split("/;\s*[\r\n]+/", trim($sql));

            foreach ($sql as $query) {
                if (!Db::getInstance()->execute(trim($query))) {
                    return false;
                }
            }
        }

        if (
            parent::install() == false ||
            !$this->registerHook('displayFooterProduct') || //Product page footer
            !$this->registerHook('displayHeader') || //Adds css and javascript on front
            !$this->registerHook('displayProductListReviews') || //Product list miniature
            !$this->registerHook('displayProductAdditionalInfo') || //Display info in checkout column
            !$this->registerHook('filterProductContent') || // Add infos to Product page
            !$this->registerHook('registerGDPRConsent') ||
            !$this->registerHook('actionDeleteGDPRCustomer') ||
            !$this->registerHook('actionExportGDPRData') ||

            !Configuration::updateValue('PRODUCT_COMMENTS_MINIMAL_TIME', 30) ||
            !Configuration::updateValue('PRODUCT_COMMENTS_ALLOW_GUESTS', 0) ||
            !Configuration::updateValue('PRODUCT_COMMENTS_USEFULNESS', 1) ||
            !Configuration::updateValue('PRODUCT_COMMENTS_COMMENTS_PER_PAGE', 5) ||
            !Configuration::updateValue('PRODUCT_COMMENTS_ANONYMISATION', 0) ||
            !Configuration::updateValue('PRODUCT_COMMENTS_MODERATE', 1)
        ) {
            return false;
        }

        return true;
    }

    public function uninstall($keep = true)
    {
        if (
            !parent::uninstall() || ($keep && !$this->deleteTables()) ||
            !Configuration::deleteByName('PRODUCT_COMMENTS_MODERATE') ||
            !Configuration::deleteByName('PRODUCT_COMMENTS_COMMENTS_PER_PAGE') ||
            !Configuration::deleteByName('PRODUCT_COMMENTS_ANONYMISATION') ||
            !Configuration::deleteByName('PRODUCT_COMMENTS_ALLOW_GUESTS') ||
            !Configuration::deleteByName('PRODUCT_COMMENTS_USEFULNESS') ||
            !Configuration::deleteByName('PRODUCT_COMMENTS_MINIMAL_TIME') ||

            !$this->unregisterHook('registerGDPRConsent') ||
            !$this->unregisterHook('actionDeleteGDPRCustomer') ||
            !$this->unregisterHook('actionExportGDPRData') ||

            !$this->unregisterHook('displayProductAdditionalInfo') ||
            !$this->unregisterHook('displayHeader') ||
            !$this->unregisterHook('displayFooterProduct') ||
            !$this->unregisterHook('displayProductListReviews')
        ) {
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
        $id_product_comment = (int) Tools::getValue('id_product_comment');
        $id_product_comment_criterion = (int) Tools::getValue('id_product_comment_criterion');
        $commentRepository = $this->get('product_comment_repository');
        $criterionRepository = $this->get('product_comment_criterion_repository');
        $criterionFormHandler = $this->get('product_comment_criterion_form_data_handler');

        if (Tools::isSubmit('submitModerate')) {
            $errors = [];
            $productCommentsMinimalTime = Tools::getValue('PRODUCT_COMMENTS_MINIMAL_TIME');
            if (!Validate::isUnsignedInt($productCommentsMinimalTime) || 0 >= $productCommentsMinimalTime) {
                $errors[] = $this->trans(
                    '%s is invalid. Please enter an integer greater than %s.',
                    [$this->trans('Minimum time between 2 reviews from the same user', [], 'Modules.Productcomments.Admin'), '0'],
                    'Admin.Notifications.Error'
                );
            }
            $productCommentsPerPage = Tools::getValue('PRODUCT_COMMENTS_COMMENTS_PER_PAGE');
            if (!Validate::isUnsignedInt($productCommentsPerPage) || 0 >= $productCommentsPerPage) {
                $errors[] = $this->trans(
                    '%s is invalid. Please enter an integer greater than %s.',
                    [$this->trans('Number of comments per page', [], 'Modules.Productcomments.Admin'), '0'],
                    'Admin.Notifications.Error'
                );
            }
            if (count($errors)) {
                $this->_html .= $this->displayError(implode('<br />', $errors));
            } else {
                Configuration::updateValue('PRODUCT_COMMENTS_MODERATE', (int) Tools::getValue('PRODUCT_COMMENTS_MODERATE'));
                Configuration::updateValue('PRODUCT_COMMENTS_ALLOW_GUESTS', (int) Tools::getValue('PRODUCT_COMMENTS_ALLOW_GUESTS'));
                Configuration::updateValue('PRODUCT_COMMENTS_USEFULNESS', (int) Tools::getValue('PRODUCT_COMMENTS_USEFULNESS'));
                Configuration::updateValue('PRODUCT_COMMENTS_ANONYMISATION', (int) Tools::getValue('PRODUCT_COMMENTS_ANONYMISATION'));
                Configuration::updateValue('PRODUCT_COMMENTS_MINIMAL_TIME', $productCommentsMinimalTime);
                Configuration::updateValue('PRODUCT_COMMENTS_COMMENTS_PER_PAGE', $productCommentsPerPage);
                $this->_html .= $this->displayConfirmation($this->trans('Settings updated', [], 'Modules.Productcomments.Admin'));
            }
        } elseif (Tools::isSubmit('productcomments')) {
            $comment = $commentRepository->find($id_product_comment);
            $commentRepository->validate($comment, 1);
            $commentRepository->deleteReports($id_product_comment);
        } elseif (Tools::isSubmit('deleteproductcomments')) {
            $comment = $commentRepository->find($id_product_comment);

            if ($comment === null) {
                $this->_html .= $this->displayError($this->trans('The comment cannot be deleted', [], 'Modules.Productcomments.Admin'));
            } else {
                $commentRepository->delete($comment);
                Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name]));
            }
        } elseif (Tools::isSubmit('submitEditCriterion')) {
            if ($id_product_comment_criterion > 0) {
                $criterion = $criterionRepository->find($id_product_comment_criterion);
            } else {
                $criterion = new ProductCommentCriterion();
            }

            $criterion->setType((int) Tools::getValue('id_product_comment_criterion_type'));
            $criterion->setActive(Tools::getValue('active'));

            $languages = Language::getLanguages();
            $name = [];
            foreach ($languages as $key => $value) {
                $name[$value['id_lang']] = Tools::getValue('name_' . $value['id_lang']);
            }

            if ($id_product_comment_criterion > 0) {
                $criterionFormHandler->updateLangs($criterion, $name);
            } else {
                $criterionFormHandler->createLangs($criterion, $name);
            }

            if (!$criterion->isValid()) {
                $this->_html .= $this->displayError($this->trans('The criterion cannot be saved', [], 'Modules.Productcomments.Admin'));
            } else {
                $criterion->setCategories(Tools::getValue('categoryBox'));
                $criterion->setProducts(Tools::getValue('ids_product'));
                if ($criterionRepository->update($criterion)) {
                    Tools::redirectAdmin(Context::getContext()->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name, 'conf' => 4]));
                } else {
                    $this->_html .= $this->displayError($this->trans('The criterion cannot be saved', [], 'Modules.Productcomments.Admin'));
                }
            }
        } elseif (Tools::isSubmit('deleteproductcommentscriterion')) {
            $criterion = $criterionRepository->find($id_product_comment_criterion);
            if ($criterionRepository->delete($criterion)) {
                Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name]));
            } else {
                $this->_html .= $this->displayError($this->trans('Criterion cannot be deleted', [], 'Modules.Productcomments.Admin'));
            }
        } elseif (Tools::isSubmit('statusproductcommentscriterion')) {
            $criterion = $criterionRepository->find($id_product_comment_criterion);
            $criterion->setActive(!$criterion->isActive());
            $criterionRepository->updateGeneral($criterion);

            Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name]));
        } elseif ($id_product_comment = (int) Tools::getValue('approveComment')) {
            $comment = $commentRepository->find($id_product_comment);
            $commentRepository->validate($comment, 1);
        } elseif ($id_product_comment = (int) Tools::getValue('noabuseComment')) {
            $commentRepository->deleteReports($id_product_comment);
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name]));
        }

        $this->_clearcache('productcomments_reviews.tpl');
    }

    public function getContent()
    {
        $this->_html = '';
        if (Tools::isSubmit('updateproductcommentscriterion')) {
            $this->_html .= $this->renderCriterionForm((int) Tools::getValue('id_product_comment_criterion'));
        } else {
            $this->_postProcess();
            $this->_html .= $this->renderConfigForm();
            $this->_html .= $this->renderModerateLists();
            $this->_html .= $this->renderCriterionList();
            $this->_html .= $this->renderCommentsList();
        }

        $this->_setBaseUrl();
        $this->_productCommentsCriterionTypes = $this->get('product_comment_criterion_repository')->getTypes();

        $this->context->controller->addJs($this->_path . 'js/moderate.js');

        return $this->_html;
    }

    private function _setBaseUrl()
    {
        $this->_baseUrl = 'index.php?';
        foreach ($_GET as $k => $value) {
            if (!in_array($k, ['deleteCriterion', 'editCriterion'])) {
                $this->_baseUrl .= $k . '=' . $value . '&';
            }
        }
        $this->_baseUrl = rtrim($this->_baseUrl, '&');
    }

    public function renderConfigForm()
    {
        $fields_form_1 = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Configuration', [], 'Modules.Productcomments.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'is_bool' => true, //retro compat 1.5
                        'label' => $this->trans('All reviews must be validated by an employee', [], 'Modules.Productcomments.Admin'),
                        'name' => 'PRODUCT_COMMENTS_MODERATE',
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Yes', [], 'Modules.Productcomments.Admin'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('No', [], 'Modules.Productcomments.Admin'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'is_bool' => true, //retro compat 1.5
                        'label' => $this->trans('Allow guest reviews', [], 'Modules.Productcomments.Admin'),
                        'name' => 'PRODUCT_COMMENTS_ALLOW_GUESTS',
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Yes', [], 'Modules.Productcomments.Admin'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('No', [], 'Modules.Productcomments.Admin'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'is_bool' => true, //retro compat 1.5
                        'label' => $this->trans('Enable upvotes / downvotes on reviews', [], 'Modules.Productcomments.Admin'),
                        'name' => 'PRODUCT_COMMENTS_USEFULNESS',
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Yes', [], 'Modules.Productcomments.Admin'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('No', [], 'Modules.Productcomments.Admin'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'is_bool' => true, //retro compat 1.5
                        'label' => $this->trans('Anonymize the user\'s last name', [], 'Modules.Productcomments.Admin'),
                        'name' => 'PRODUCT_COMMENTS_ANONYMISATION',
                        'desc' => $this->trans('Display only initials, e.g. John D.', [], 'Modules.Productcomments.Admin'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Yes', [], 'Modules.Productcomments.Admin'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('No', [], 'Modules.Productcomments.Admin'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Minimum time between 2 reviews from the same user', [], 'Modules.Productcomments.Admin'),
                        'name' => 'PRODUCT_COMMENTS_MINIMAL_TIME',
                        'class' => 'fixed-width-xs',
                        'suffix' => 'seconds',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Number of comments per page', [], 'Modules.Productcomments.Admin'),
                        'name' => 'PRODUCT_COMMENTS_COMMENTS_PER_PAGE',
                        'class' => 'fixed-width-xs',
                        'suffix' => 'comments',
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Modules.Productcomments.Admin'),
                    'class' => 'btn btn-default pull-right',
                    'name' => 'submitModerate',
                ],
            ],
        ];

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
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->langId,
        ];

        return $helper->generateForm([$fields_form_1]);
    }

    public function renderModerateLists()
    {
        $return = null;
        $commentRepository = $this->get('product_comment_repository');

        if (Configuration::get('PRODUCT_COMMENTS_MODERATE')) {
            $comments = $commentRepository->getByValidate($this->langId, $this->shopId, 0, false);

            $fields_list = $this->getStandardFieldList();

            $actions = ['approve', 'delete'];

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
            $helper->table_id = 'waiting-approval-productcomments-list';
            $helper->token = Tools::getAdminTokenLite('AdminModules');
            $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
            $helper->no_link = true;

            $return .= $helper->generateList($comments, $fields_list);
        }

        $comments = $commentRepository->getReportedComments($this->langId, $this->shopId);

        $fields_list = $this->getStandardFieldList();

        $actions = ['delete', 'noabuse'];

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
        $helper->table_id = 'reported-productcomments-list';
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->no_link = true;

        $return .= $helper->generateList($comments, $fields_list);

        return $return;
    }

    /**
     * Method used by the HelperList to render the approve link
     *
     * @param string $token
     * @param int $id
     * @param string|null $name
     *
     * @return false|string
     */
    public function displayApproveLink($token, $id, $name = null)
    {
        $this->smarty->assign([
            'href' => $this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name, 'module_name' => $this->name, 'approveComment' => $id]),
            'action' => $this->trans('Approve', [], 'Modules.Productcomments.Admin'),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/list_action_approve.tpl');
    }

    /**
     * Method used by the HelperList to render the approve link
     *
     * @param string $token
     * @param int $id
     * @param string|null $name
     *
     * @return false|string
     */
    public function displayNoabuseLink($token, $id, $name = null)
    {
        $this->smarty->assign([
            'href' => $this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name, 'module_name' => $this->name, 'noabuseComment' => $id]),
            'action' => $this->trans('Not abusive', [], 'Modules.Productcomments.Admin'),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/list_action_noabuse.tpl');
    }

    public function renderCriterionList()
    {
        $criterions = $this->get('product_comment_criterion_repository')->getCriterions($this->langId, false, false);

        $fields_list = [
            'id_product_comment_criterion' => [
                'title' => $this->trans('ID', [], 'Modules.Productcomments.Admin'),
                'type' => 'text',
            ],
            'name' => [
                'title' => $this->trans('Name', [], 'Modules.Productcomments.Admin'),
                'type' => 'text',
            ],
            'type_name' => [
                'title' => $this->trans('Type', [], 'Modules.Productcomments.Admin'),
                'type' => 'text',
            ],
            'active' => [
                'title' => $this->trans('Status', [], 'Modules.Productcomments.Admin'),
                'active' => 'status',
                'type' => 'bool',
            ],
        ];

        $helper = new HelperList();
        $helper->shopLinkType = '';
        $helper->simple_header = false;
        $helper->actions = ['edit', 'delete'];
        $helper->show_toolbar = true;
        $helper->toolbar_btn['new'] = [
            'href' => $this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name, 'module_name' => $this->name, 'updateproductcommentscriterion' => '']),
            'desc' => $this->trans('Add New Criterion', [], 'Modules.Productcomments.Admin'),
        ];
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
        $fields_list = $this->getStandardFieldList();

        $helper = new HelperList();
        $helper->list_id = 'form-productcomments-list';
        $helper->shopLinkType = '';
        $helper->simple_header = false;
        $helper->actions = ['delete'];
        $helper->show_toolbar = false;
        $helper->module = $this;
        $helper->identifier = 'id_product_comment';
        $helper->title = $this->trans('Approved Reviews', [], 'Modules.Productcomments.Admin');
        $helper->table = $this->name;
        $helper->table_id = 'approved-productcomments-list';
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->no_link = true;

        $page = ($page = Tools::getValue('submitFilter' . $helper->list_id)) ? (int) $page : 1;
        $pagination = ($pagination = Tools::getValue($helper->list_id . '_pagination')) ? (int) $pagination : 50;

        $moderate = Configuration::get('PRODUCT_COMMENTS_MODERATE');
        $commentRepository = $this->get('product_comment_repository');
        if (empty($moderate)) {
            $comments = $commentRepository->getByValidate($this->langId, $this->shopId, 0, false, $page, $pagination, true);
            $count = $commentRepository->getCountByValidate(0, true);
        } else {
            $comments = $commentRepository->getByValidate($this->langId, $this->shopId, 1, false, $page, $pagination);
            $count = $commentRepository->getCountByValidate(1);
        }

        $helper->listTotal = $count;

        return $helper->generateList($comments, $fields_list);
    }

    public function getConfigFieldsValues()
    {
        return [
            'PRODUCT_COMMENTS_MODERATE' => Tools::getValue('PRODUCT_COMMENTS_MODERATE', Configuration::get('PRODUCT_COMMENTS_MODERATE')),
            'PRODUCT_COMMENTS_ALLOW_GUESTS' => Tools::getValue('PRODUCT_COMMENTS_ALLOW_GUESTS', Configuration::get('PRODUCT_COMMENTS_ALLOW_GUESTS')),
            'PRODUCT_COMMENTS_USEFULNESS' => Tools::getValue('PRODUCT_COMMENTS_USEFULNESS', Configuration::get('PRODUCT_COMMENTS_USEFULNESS')),
            'PRODUCT_COMMENTS_MINIMAL_TIME' => Tools::getValue('PRODUCT_COMMENTS_MINIMAL_TIME', Configuration::get('PRODUCT_COMMENTS_MINIMAL_TIME')),
            'PRODUCT_COMMENTS_COMMENTS_PER_PAGE' => Tools::getValue('PRODUCT_COMMENTS_COMMENTS_PER_PAGE', Configuration::get('PRODUCT_COMMENTS_COMMENTS_PER_PAGE')),
            'PRODUCT_COMMENTS_ANONYMISATION' => Tools::getValue('PRODUCT_COMMENTS_ANONYMISATION', Configuration::get('PRODUCT_COMMENTS_ANONYMISATION')),
        ];
    }

    public function getCriterionFieldsValues(int $id = 0)
    {
        $criterionRepos = $this->get('product_comment_criterion_repository');
        $criterionFormProvider = $this->get('product_comment_criterion_form_data_provider');

        if ($id > 0) {
            $criterionData = $criterionFormProvider->getData($id);
        } else {
            $criterionData = $criterionFormProvider->getDefaultData();
        }

        return [
            'name' => $criterionData['name'],
            'id_product_comment_criterion_type' => $criterionData['type'],
            'active' => $criterionData['active'],
            'id_product_comment_criterion' => $id,
        ];
    }

    public function getStandardFieldList()
    {
        return [
            'id_product_comment' => [
                'title' => $this->trans('ID', [], 'Modules.Productcomments.Admin'),
                'type' => 'text',
                'search' => false,
                'class' => 'product-comment-id',
            ],
            'title' => [
                'title' => $this->trans('Review title', [], 'Modules.Productcomments.Admin'),
                'type' => 'text',
                'search' => false,
                'class' => 'product-comment-title',
            ],
            'content' => [
                'title' => $this->trans('Review', [], 'Modules.Productcomments.Admin'),
                'type' => 'text',
                'search' => false,
                'class' => 'product-comment-content',
            ],
            'grade' => [
                'title' => $this->trans('Rating', [], 'Modules.Productcomments.Admin'),
                'type' => 'text',
                'suffix' => '/5',
                'search' => false,
                'class' => 'product-comment-rating',
            ],
            'customer_name' => [
                'title' => $this->trans('Author', [], 'Modules.Productcomments.Admin'),
                'type' => 'text',
                'search' => false,
                'class' => 'product-comment-author',
                'callback' => 'renderAuthorName',
                'callback_object' => $this,
            ],
            'name' => [
                'title' => $this->trans('Product', [], 'Modules.Productcomments.Admin'),
                'type' => 'text',
                'search' => false,
                'class' => 'product-comment-product-name',
            ],
            'date_add' => [
                'title' => $this->trans('Time of publication', [], 'Modules.Productcomments.Admin'),
                'type' => 'date',
                'search' => false,
                'class' => 'product-comment-date',
            ],
        ];
    }

    /**
     * Renders author name for the list, with the link if the author is a customer.
     *
     * @param string $value
     * @param array $row
     *
     * @return string
     */
    public function renderAuthorName($value, $row)
    {
        $value = htmlentities($value);
        if (!empty($row['customer_id'])) {
            $linkToCustomerProfile = $this->context->link->getAdminLink('AdminCustomers', false, [], [
                'id_customer' => $row['customer_id'],
                'viewcustomer' => 1,
            ]);

            return '<a href="' . $linkToCustomerProfile . '">' . $value . '</a>';
        }

        return $value;
    }

    public function renderCriterionForm($id_criterion = 0)
    {
        $types = $this->get('product_comment_criterion_repository')->getTypes();
        $query = [];
        foreach ($types as $key => $value) {
            $query[] = [
                'id' => $key,
                'label' => $value,
            ];
        }

        $criterionRepository = $this->get('product_comment_criterion_repository');

        $criterion = $criterionRepository->find($id_criterion);
        $selected_categories = $criterionRepository->getCategories($id_criterion);

        $product_table_values = Product::getSimpleProducts($this->langId);
        $selected_products = $criterionRepository->getProducts($id_criterion);

        foreach ($product_table_values as $key => $product) {
            if (false !== array_search($product['id_product'], $selected_products)) {
                $product_table_values[$key]['selected'] = 1;
            }
        }

        $field_category_tree = [
            'type' => 'categories',
            'label' => $this->trans('Criterion will be restricted to the following categories', [], 'Modules.Productcomments.Admin'),
            'name' => 'categoryBox',
            'desc' => $this->trans('Mark the boxes of categories to which this criterion applies.', [], 'Modules.Productcomments.Admin'),
            'tree' => [
                'use_search' => false,
                'id' => 'categoryBox',
                'use_checkbox' => true,
                'selected_categories' => $selected_categories,
            ],
            //retro compat 1.5 for category tree
            'values' => [
                'trads' => [
                    'Root' => Category::getTopCategory(),
                    'selected' => $this->trans('Selected', [], 'Modules.Productcomments.Admin'),
                    'Collapse All' => $this->trans('Collapse All', [], 'Modules.Productcomments.Admin'),
                    'Expand All' => $this->trans('Expand All', [], 'Modules.Productcomments.Admin'),
                    'Check All' => $this->trans('Check All', [], 'Modules.Productcomments.Admin'),
                    'Uncheck All' => $this->trans('Uncheck All', [], 'Modules.Productcomments.Admin'),
                ],
                'selected_cat' => $selected_categories,
                'input_name' => 'categoryBox[]',
                'use_radio' => false,
                'use_search' => false,
                'disabled_categories' => [],
                'top_category' => Category::getTopCategory(),
                'use_context' => true,
            ],
        ];

        $fields_form_1 = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Add new criterion', [], 'Modules.Productcomments.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'hidden',
                        'name' => 'id_product_comment_criterion',
                    ],
                    [
                        'type' => 'text',
                        'lang' => true,
                        'label' => $this->trans('Criterion name', [], 'Modules.Productcomments.Admin'),
                        'name' => 'name',
                        'desc' => $this->trans('Maximum length: %s characters', [ProductCommentCriterion::NAME_MAX_LENGTH], 'Modules.Productcomments.Admin'),
                    ],
                    [
                        'type' => 'select',
                        'name' => 'id_product_comment_criterion_type',
                        'label' => $this->trans('Application scope of the criterion', [], 'Modules.Productcomments.Admin'),
                        'options' => [
                            'query' => $query,
                            'id' => 'id',
                            'name' => 'label',
                        ],
                    ],
                    $field_category_tree,
                    [
                        'type' => 'products',
                        'label' => $this->trans('The criterion will be restricted to the following products', [], 'Modules.Productcomments.Admin'),
                        'name' => 'ids_product',
                        'values' => $product_table_values,
                    ],
                    [
                        'type' => 'switch',
                        'is_bool' => true, //retro compat 1.5
                        'label' => $this->trans('Active', [], 'Modules.Productcomments.Admin'),
                        'name' => 'active',
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Yes', [], 'Modules.Productcomments.Admin'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('No', [], 'Modules.Productcomments.Admin'),
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Modules.Productcomments.Admin'),
                    'class' => 'btn btn-default pull-right',
                    'name' => 'submitEditCriterion',
                ],
            ],
        ];

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
        $helper->tpl_vars = [
            'fields_value' => $this->getCriterionFieldsValues($id_criterion),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->langId,
        ];

        return $helper->generateForm([$fields_form_1]);
    }

    public function initCategoriesAssociation($id_root = null, $id_criterion = 0)
    {
        if (is_null($id_root)) {
            $id_root = Configuration::get('PS_ROOT_CATEGORY');
        }
        $id_shop = (int) Tools::getValue('id_shop');
        $shop = new Shop($id_shop);
        if ($id_criterion == 0) {
            $selected_cat = [];
        } else {
            $criterionRepository = $this->get('product_comment_criterion_repository');
            $criterion = $criterionRepository->find((int) $id_criterion);
            $selected_cat = $criterionRepository->getCategories($criterion);
        }

        if (Shop::getContext() == Shop::CONTEXT_SHOP && Tools::isSubmit('id_shop')) {
            $root_category = new Category($shop->id_category);
        } else {
            $root_category = new Category($id_root);
        }
        $root_category = ['id_category' => $root_category->id, 'name' => $root_category->name[$this->langId]];

        $helper = new Helper();

        return $helper->renderCategoryTree($root_category, $selected_cat, 'categoryBox', false, true);
    }

    public function hookActionDeleteGDPRCustomer($customer)
    {
        if (isset($customer['id'])) {
            $productCommentRepository = $this->get('product_comment_repository');
            $productCommentRepository->cleanCustomerData($customer['id']);
        }

        return true;
    }

    public function hookActionExportGDPRData($customer)
    {
        if (isset($customer['id'])) {
            $productCommentRepository = $this->get('product_comment_repository');
            $langId = isset($customer['id_lang']) ? $customer['id_lang'] : $this->langId;

            return json_encode($productCommentRepository->getCustomerData($customer['id'], $langId));
        }
    }

    /**
     *  Inject the needed javascript and css files in the appropriate pages
     */
    public function hookDisplayHeader()
    {
        $jsList = [];
        $cssList = [];

        $cssList[] = '/modules/productcomments/views/css/productcomments.css';
        $jsList[] = '/modules/productcomments/views/js/jquery.rating.plugin.js';
        $jsList[] = '/modules/productcomments/views/js/productListingComments.js';
        if ($this->context->controller instanceof ProductControllerCore) {
            $jsList[] = '/modules/productcomments/views/js/post-comment.js';
            $jsList[] = '/modules/productcomments/views/js/list-comments.js';
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
     * @param array $params
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
     * Inject data about productcomments in the product object for frontoffice
     *
     * @param array $params
     *
     * @return array
     */
    public function hookFilterProductContent(array $params)
    {
        if (empty($params['object']->id)) {
            return $params;
        }
        $commentRepository = $this->get('product_comment_repository');
        $averageRating = $commentRepository->getAverageGrade($params['object']->id, (bool) Configuration::get('PRODUCT_COMMENTS_MODERATE'));
        $nbComments = $commentRepository->getCommentsNumber($params['object']->id, (bool) Configuration::get('PRODUCT_COMMENTS_MODERATE'));

        /* @phpstan-ignore-next-line */
        $params['object']->productComments = [
            'averageRating' => $averageRating,
            'nbComments' => $nbComments,
        ];

        return $params;
    }

    /**
     * Used to render the product comments list
     *
     * @param Product $product
     *
     * @return string
     *
     * @throws PrestaShopException
     * @throws SmartyException
     */
    private function renderProductCommentsList($product)
    {
        $commentRepository = $this->get('product_comment_repository');
        $averageGrade = $commentRepository->getAverageGrade($product->id, (bool) Configuration::get('PRODUCT_COMMENTS_MODERATE'));
        $commentsNb = $commentRepository->getCommentsNumber($product->id, (bool) Configuration::get('PRODUCT_COMMENTS_MODERATE'));
        $isPostAllowed = $commentRepository->isPostAllowed($product->id, (int) $this->context->cookie->id_customer, (int) $this->context->cookie->id_guest);

        /* configure pagination */
        $commentsTotalPages = 0;
        $commentsPerPage = (int) Configuration::get('PRODUCT_COMMENTS_COMMENTS_PER_PAGE');
        if ($commentsNb > 0) {
            $commentsTotalPages = ceil($commentsNb / $commentsPerPage);
        }

        $this->context->smarty->assign([
            'post_allowed' => $isPostAllowed,
            'usefulness_enabled' => Configuration::get('PRODUCT_COMMENTS_USEFULNESS'),
            'average_grade' => $averageGrade,
            'nb_comments' => $commentsNb,
            'list_comments_url' => $this->context->link->getModuleLink(
                'productcomments',
                'ListComments',
                ['id_product' => $product->id]
            ),
            'update_comment_usefulness_url' => $this->context->link->getModuleLink(
                'productcomments',
                'UpdateCommentUsefulness'
            ),
            'report_comment_url' => $this->context->link->getModuleLink(
                'productcomments',
                'ReportComment'
            ),
            'list_total_pages' => $commentsTotalPages,
        ]);

        return $this->context->smarty->fetch('module:productcomments/views/templates/hook/product-comments-list.tpl');
    }

    /**
     * Used to render the product modal
     *
     * @param Product $product
     *
     * @return string
     *
     * @throws PrestaShopException
     * @throws SmartyException
     */
    private function renderProductCommentModal($product)
    {
        $criterionRepository = $this->get('product_comment_criterion_repository');
        $criterions = $criterionRepository->getByProduct($product->id, $this->langId);

        $this->context->smarty->assign([
            'logged' => (bool) $this->context->cookie->id_customer,
            'post_comment_url' => $this->context->link->getModuleLink(
                'productcomments',
                'PostComment',
                ['id_product' => $product->id]
            ),
            'moderation_active' => (int) Configuration::get('PRODUCT_COMMENTS_MODERATE'),
            'criterions' => $criterions,
            'product' => $product,
            'id_module' => $this->id,
        ]);

        return $this->context->smarty->fetch('module:productcomments/views/templates/hook/post-comment-modal.tpl');
    }

    public function getWidgetVariables($hookName = null, array $configuration = [])
    {
        $commentRepository = $this->get('product_comment_repository');
        $averageGrade = $commentRepository->getAverageGrade($configuration['id_product'], Configuration::get('PRODUCT_COMMENTS_MODERATE'));
        $commentsNb = $commentRepository->getCommentsNumber($configuration['id_product'], Configuration::get('PRODUCT_COMMENTS_MODERATE'));
        $isPostAllowed = $commentRepository->isPostAllowed($configuration['id_product'], (int) $this->context->cookie->id_customer, (int) $this->context->cookie->id_guest);

        return [
            'average_grade' => $averageGrade,
            'nb_comments' => $commentsNb,
            'post_allowed' => $isPostAllowed,
        ];
    }

    public function renderWidget($hookName = null, array $configuration = [])
    {
        $variables = [];
        $tplHookPath = 'module:productcomments/views/templates/hook/';

        if ('displayProductListReviews' === $hookName || isset($configuration['type']) && 'product_list' === $configuration['type']) {
            $product = $configuration['product'];
            $idProduct = $product['id_product'];
            $variables = $this->getWidgetVariables($hookName, ['id_product' => $idProduct]);

            $variables = array_merge($variables, [
                'product' => $product,
                'product_comment_grade_url' => $this->context->link->getModuleLink('productcomments', 'CommentGrade'),
            ]);

            $filePath = $tplHookPath . 'product-list-reviews.tpl';
        } elseif ($this->context->controller instanceof ProductControllerCore) {
            $idProduct = $this->context->controller->getProduct()->id;
            $variables = $this->getWidgetVariables($hookName, ['id_product' => $idProduct]);

            switch (Tools::getValue('action')) {
                case 'quickview':
                    $filePath = $tplHookPath . 'product-additional-info-quickview.tpl';
                    break;
                case '':
                    $filePath = $tplHookPath . 'product-additional-info.tpl';
                    break;
                default:    // 'refresh' and other unpredicted cases
                    $filePath = '';
            }
        }

        if (empty($variables) || empty($filePath)) {
            return false;
        }

        $this->smarty->assign($variables);

        return $this->fetch($filePath);
    }

    /**
     * empty listener for registerGDPRConsent hook
     */
    public function hookRegisterGDPRConsent()
    {
        /* registerGDPRConsent is a special kind of hook that doesn't need a listener, see :
           https://build.prestashop.com/howtos/module/how-to-make-your-module-compliant-with-prestashop-official-gdpr-compliance-module/
          However since Prestashop 1.7.8, modules must implement a listener for all the hooks they register: a check is made
          at module installation.
        */
    }
}
