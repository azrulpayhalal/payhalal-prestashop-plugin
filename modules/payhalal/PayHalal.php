<?php
/**
* 2007-2018 PrestaShop
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
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2018 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PayHalal extends PaymentModule
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'PayHalal';
        $this->tab = 'payments_gateways';
        $this->version = '2.0.0';
        $this->author = 'PayHalal';
        $this->author_uri = 'https://github.com/SouqaFintech/prestashop-plugin';
        $this->controllers = array('payment', 'validation');
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->displayName = $this->l('PayHalal');
        $this->description = $this->l('Payment Without Was-Was');

        // $this->limited_countries = array('MY');

        // $this->limited_currencies = array('MYR');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (extension_loaded('curl') == false)
        {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        // $iso_code = Country::getIsoById(Configuration::get('PS_COUNTRY_DEFAULT'));

        // if (in_array($iso_code, $this->limited_countries) == false)
        // {
        //     $this->_errors[] = $this->l('This module is not available in your country');
        //     return false;
        // }

        Configuration::updateValue('PAYHALAL_LIVE_MODE', true);

        include(dirname(__FILE__).'/sql/install.php');

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('paymentReturn') &&
            $this->registerHook('displayPayment');
    }

    public function uninstall()
    {
        Configuration::deleteByName('PAYHALAL_LIVE_MODE');

        include(dirname(__FILE__).'/sql/uninstall.php');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitPayHalalModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitPayHalalModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Live mode'),
                        'name' => 'PAYHALAL_LIVE_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Use this module in live mode. Default is testing'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 3,
                        // 'type' => 'text',
                        // 'prefix' => '<i class="icon icon-key"></i>',
                        // 'desc' => $this->l('APP API Key'),
                        // 'name' => 'PAYHALAL_API_KEY',
                        'label' => $this->l('Live Mode'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-key"></i>',
                        'desc' => $this->l('APP API Key'),
                        'name' => 'PAYHALAL_API_KEY',
                        'label' => $this->l('API Key'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-key"></i>',
                        'desc' => $this->l('APP SECRET Key'),
                        'name' => 'PAYHALAL_SECRET_KEY',
                        'label' => $this->l('Secret Key'),
                    ),
                    array(
                        'col' => 3,
                        // 'type' => 'text',
                        // 'prefix' => '<i class="icon icon-key"></i>',
                        // 'desc' => $this->l('APP API Key'),
                        // 'name' => 'PAYHALAL_API_KEY',
                        'label' => $this->l('Testing Mode'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-key"></i>',
                        'desc' => $this->l('APP Testing API Key'),
                        'name' => 'PAYHALAL_API_KEY_TESTING',
                        'label' => $this->l('API Key Testing'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-key"></i>',
                        'desc' => $this->l('APP Testing SECRET Key'),
                        'name' => 'PAYHALAL_SECRET_KEY_TESTING',
                        'label' => $this->l('Secret Key Testing'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            // 'PAYHALAL_LIVE_MODE' => Configuration::get('PAYHALAL_LIVE_MODE', true),
            // 'PAYHALAL_ACCOUNT_EMAIL' => Configuration::get('PAYHALAL_ACCOUNT_EMAIL', 'payhalal@payhalal.com'),
            // 'PAYHALAL_MERCHANT_PASSWORD' => Configuration::get('PAYHALAL_MERCHANT_PASSWORD', null),
            'PAYHALAL_LIVE_MODE' => Configuration::get('PAYHALAL_LIVE_MODE'),
            'PAYHALAL_API_KEY' => Configuration::get('PAYHALAL_API_KEY'),
            'PAYHALAL_SECRET_KEY' => Configuration::get('PAYHALAL_SECRET_KEY'),
            'PAYHALAL_API_KEY_TESTING' => Configuration::get('PAYHALAL_API_KEY_TESTING'),
            'PAYHALAL_SECRET_KEY_TESTING' => Configuration::get('PAYHALAL_SECRET_KEY_TESTING'),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    /**
     * This method is used to render the payment button,
     * Take care if the button should be displayed or not.
     */
    public function hookPaymentOptions($params)
    {

        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $this->smarty->assign('module_dir', $this->_path);

        $newOption = new PaymentOption();
        $newOption->setModuleName($this->name)
                ->setCallToActionText($this->trans('Pay by PayHalal', array(), 'Modules.PayHalal.Shop'))
                ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
                ->setAdditionalInformation($this->fetch('module:PayHalal/views/templates/hook/payment.tpl'));
        $payment_options = [
            $newOption,
        ];

        return $payment_options;

    }

    /**
     * This hook is used to display the order confirmation page.
     */
    public function hookPaymentReturn($params)
    {
        if ($this->active){
            return;
        }

        return $this->fetch('module:PayHalal/views/templates/hook/payment_return.tpl');
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }
}
