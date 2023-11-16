<?php
/**
* 2007-2023 PrestaShop
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
*  @copyright 2007-2023 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class Woovi extends PaymentModule
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'woovi';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Victor';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Woovi Pix');
        $this->description = $this->l('O Woovi API para PrestaShop oferece uma integração rapida e escalável para pagamentos por pix. Instale o conector na sua loja online PrestaShop, crie uma conta Woovi ou conecte a sua conta existente e comece a aceitar pix imediatamente');

        $this->confirmUninstall = $this->l('Tem certeza que deseja desinstalar esse modulo?');

        $this->limited_countries = array('BR');

        $this->limited_currencies = array('BRL');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
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

        $iso_code = Country::getIsoById(Configuration::get('PS_COUNTRY_DEFAULT'));

        if (in_array($iso_code, $this->limited_countries) == false)
        {
            $this->_errors[] = $this->l('This module is not available in your country');
            return false;
        }

        Configuration::updateValue('WOOVI_LIVE_MODE', false);

        include(dirname(__FILE__).'/sql/install.php');
        return parent::install() &&
            $this->registerHook('header') &&
			$this->registerHook('backOfficeHeader') &&
            $this->registerHook('displayOrderDetail') &&
            $this->registerHook('payment') &&
			$this->registerHook('paymentOptions') &&
            $this->registerHook('paymentReturn') &&
            $this->registerHook('actionPaymentConfirmation') &&
            $this->registerHook('displayAdminOrder') &&
            $this->registerHook('displayFooter') &&
            $this->registerHook('displayHeader') &&
            $this->registerHook('displayPayment') &&
            $this->registerHook('displayPaymentReturn');
    }

    public function uninstall()
    {
        Configuration::deleteByName('WOOVI_LIVE_MODE');

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
        if (((bool)Tools::isSubmit('submitWooviModule')) == true) {
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
        $helper->submit_action = 'submitWooviModule';
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
		$config = array(
			'form' => array(
				'legend' => array(
				'title' => $this->l('Configurações'),
				'icon' => 'icon-cogs',
				),
				'input' => array(
					array(
						'col' => 6,
						'type' => 'text',
						'desc' => $this->l('Nome do método de pagamento a exibir ao cliente no checkout (Ex: Pague com Pix).'),
						'name' => 'WOOVI_TITULO',
						'label' => $this->l('Titulo a Exibir'),
					),
					array(
						'col' => 6,
						'type' => 'text',
						'desc' => $this->l('Chave de acesso a API da Woovi.'),
						'name' => 'WOOVI_KEY',
						'label' => $this->l('AppID'),
					),
					array(
						'type' => 'select',
						'name' => 'WOOVI_INICIADA',
						'desc' => $this->l('Status customizado ou já existente!'),
						'label' => $this->l('Status Aguardando Pagamento'),
						'options' => array(
							'query' => $this->nomes_status_pagamentos(),
							'id' => 'id_order_state',
							'name' => 'name'
						)
					),
					array(
						'type' => 'select',
						'name' => 'WOOVI_PAGO',
						'desc' => $this->l('Status customizado ou já existente!'),
						'label' => $this->l('Status Pago'),
						'options' => array(
							'query' => $this->nomes_status_pagamentos(),
							'id' => 'id_order_state',
							'name' => 'name'
						)
					),
					array(
						'type' => 'select',
						'name' => 'WOOVI_DEVOLVIDO',
						'desc' => $this->l('Status customizado ou já existente!'),
						'label' => $this->l('Status Devolvido'),
						'options' => array(
							'query' => $this->nomes_status_pagamentos(),
							'id' => 'id_order_state',
							'name' => 'name'
						)
					),
					array(
						'type' => 'select',
						'name' => 'WOOVI_CANCELADO',
						'desc' => $this->l('Status customizado ou já existente!'),
						'label' => $this->l('Status Cancelado'),
						'options' => array(
							'query' => $this->nomes_status_pagamentos(),
							'id' => 'id_order_state',
							'name' => 'name'
						)
					),
				),
				'submit' => array(
					'title' => $this->l('Salvar'),
				),
			),
		);
		return $config;
    }

      protected function getConfigFormValues()
    {
        $inputs = array();
        $form   = $this->getConfigForm();
        foreach ($form['form']['input'] as $v) {
            $chave          = $v['name'];
            $inputs[$chave] = Configuration::get($chave, '');
        }
        return $inputs;
    }

      protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

   public function hookPaymentOptions($params) {
	
		
		//verifica se e uma moeda aceita
        $currency_id = $params['cart']->id_currency;
        $currency = new Currency((int)$currency_id);
        if (in_array($currency->iso_code, $this->limited_currencies) == false){
            return false;
        }
		
        //dados do pedido
        $carrinho = $params['cart'];
		$cliente = Context::getContext()->customer;
		$endereco_id = $carrinho->id_address_invoice;
        $endereco = new Address((int)($endereco_id));
        $link = Context::getContext()->link;
        $total = $carrinho->getOrderTotal(true, 3);
        $frete = $carrinho->getOrderTotal(true, 5);
        
        //tira o frete
        $total_pix_sem = ($total-$frete);
        

		//captura o cpf ou cnpj
		$numero_fiscal = preg_replace('/\D/', '', $this->cpf_cnpj());
		$array_cobranca = array_merge((array)$cliente,(array)$endereco);
        $campo_fiscal1 = explode('.',Configuration::get("PAGHIPERPIX_FISCAL1"));
		$fiscal1 = isset($campo_fiscal1[1])?$campo_fiscal1[1]:'cpf';
		$campo_fiscal2 = explode('.',Configuration::get("PAGHIPERPIX_FISCAL2"));
		$fiscal2 = isset($campo_fiscal2[1])?$campo_fiscal2[1]:'cnpj';
		$array_cobranca = array_merge((array)$cliente,(array)$endereco);
		if(isset($array_cobranca[$fiscal1]) && !empty($this->so_numeros($array_cobranca[$fiscal1]))){
			$numero_fiscal = $this->so_numeros($array_cobranca[$fiscal1]);
		}elseif(isset($array_cobranca[$fiscal2]) && !empty($this->so_numeros(($array_cobranca[$fiscal2])))){
			$numero_fiscal = $this->so_numeros($array_cobranca[$fiscal2]);
		}
		
		//meio pix
		$opcoes = array();
		$pix = new PaymentOption();
		if($desconto > 0){
			$pix->setCallToActionText($this->trans(Configuration::get('PAGHIPERPIX_TITULO'), array(), 'Modules.PagHiperPix.Pix').' ('.$desconto.'% de desconto)');
		}else{
			$pix->setCallToActionText($this->trans(Configuration::get('PAGHIPERPIX_TITULO'), array(), 'Modules.PagHiperPix.Pix').' (a vista)');
		}
		$pix->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/views/img/icon.png'));
		$pix->setForm($this->generateForm($params,$numero_fiscal,$cliente,($total_pix_sem+$frete),$desconto));
		$opcoes[] = $pix;

        return $opcoes;	
	}
	
	protected function generateForm($params,$numero_fiscal,$cliente,$total_real,$desconto)
	{
        //aplica no template
        $this->context->smarty->assign('module_dir', $this->dirModule());
		$this->context->smarty->assign('pagador', $cliente->firstname.' '.$cliente->lastname);
		$this->context->smarty->assign('desconto', $desconto);
		$this->context->smarty->assign('fiscal', $numero_fiscal);
		$this->context->smarty->assign('fiscal_size', strlen($numero_fiscal));
		$this->context->smarty->assign('total', $total_real);
		$this->context->smarty->assign('total_formatado', Tools::displayPrice($total_real));
		$this->context->smarty->assign('url_modulo', Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/');
		$this->context->smarty->assign('url_loja', Tools::getShopDomainSsl(true, true).__PS_BASE_URI__);

		//layout
		return $this->context->smarty->fetch('module:paghiperpix/views/templates/hook/form.tpl');
    }
	
	public function dirModule()
	{
		return $this->_path;
	}

	public function removerVoucherErro($cart)
	{
		$cart_rules = $cart->getCartRules();
        $rule_cod = 'V0C'.(int)($cart->id_customer).'O'.(int)($cart->id);
		foreach($cart_rules as $rule){
			if($rule['name']==$rule_cod && $cart->id_customer==$rule['id_customer']){
				$cart->removeCartRule($rule['id_cart_rule']);
				$sql = array();
				$sql[] = "DELETE FROM `"._DB_PREFIX_."cart_rule` WHERE id_cart_rule = '".(int)$rule['id_cart_rule']."'";
				$sql[] = "DELETE FROM `"._DB_PREFIX_."cart_rule_lang` WHERE id_cart_rule = '".(int)$rule['id_cart_rule']."'";
				foreach ($sql as $query) {
					Db::getInstance()->execute($query);
				}
			}
		}
	}
	
	public function aplicarDesconto($cart)
	{
		if(CartRule::cartRuleExists('DESCONTOPIXPH'.$cart->id)){
            return false;
        }
        $rule = 'V0C'.(int)($cart->id_customer).'O'.(int)($cart->id);
        if(CartRule::cartRuleExists($rule)){
            return false;
        }
		$total = (float)Configuration::get("PAGHIPERPIX_DESCONTO");
		$name='DESCONTOPIXPH'.$cart->id;
		$tipoDesconto = 1;
		$languages=Language::getLanguages();
		foreach ($languages as $key => $language) {
			$arrayName[$language['id_lang']]= 'V0C'.(int)($cart->id_customer).'O'.(int)($cart->id);
		}
		$voucher=new CartRule();
		$voucher->description=(string)($name);
		$voucher->reduction_amount = ($tipoDesconto == 2 ? $total : '');
		$voucher->reduction_percent = ($tipoDesconto == 1 ? $total : '');
		$voucher->name=$arrayName;
		$voucher->id_customer=(int)($cart->id_customer);
		$voucher->id_currency=(int)($cart->id_currency);
		$voucher->quantity=1;
		$voucher->quantity_per_user=1;
		$voucher->cumulable=0;
		$voucher->cumulable_reduction=0;
		$voucher->minimum_amount=0;
		$voucher->active=1;
		$now=time();
		$voucher->date_from=date("Y-m-d H:i:s",$now);
		$voucher->date_to=date("Y-m-d H:i:s",$now+(3600*24));
        $voucher->code='V'.(int)($voucher->id).'C'.(int)($cart->id_customer).'O'.$cart->id;
		if($voucher->add()){
            $cart->addCartRule((int)$voucher->id);
        }
	}
	
	public function so_numeros($a)
	{
		return preg_replace('/\D/', '', $a);
	}
    
    public function nomes_status_pagamentos() 
    {
		global $cookie;
		return Db::getInstance()->ExecuteS('SELECT * FROM `'._DB_PREFIX_.'order_state` AS a,`'._DB_PREFIX_.'order_state_lang` AS b WHERE b.id_lang = "'.$cookie->id_lang.'" AND a.deleted = "0" AND a.id_order_state=b.id_order_state');
	}



    public function hookPaymentReturn($params)
    {
        
		//pedido
        $order = new Order((int)$_GET['id_order']);
		if(sha1($_GET['transacao'])!=$_GET['hash']){
			die('Acesso negado!');
		}
        
		//dados pix		
		$sql = "SELECT * FROM `"._DB_PREFIX_."paghiperpix_pedidos` WHERE id_pedido = '".(int)$order->id."'";
        $dados = Db::getInstance()->getRow($sql);

		//aplica o layout
        $this->smarty->assign(array(
            'id_order' => $order->id,
            'reference' => $order->reference,
            'params' => $params,
			'dados' => $dados,
            'total' =>Tools::displayPrice(
						$params['order']->getOrdersTotalPaid(),
						new Currency($params['order']->id_currency),
						false
					),
        ));
		return $this->fetch('module:paghiperpix/views/templates/hook/confirmation.tpl');
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'WOOVI_LIVE_MODE' => Configuration::get('WOOVI_LIVE_MODE', true),
            'WOOVI_ACCOUNT_EMAIL' => Configuration::get('WOOVI_ACCOUNT_EMAIL', 'contact@prestashop.com'),
            'WOOVI_ACCOUNT_PASSWORD' => Configuration::get('WOOVI_ACCOUNT_PASSWORD', null),
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
    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
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
     * Return payment options available for PS 1.7+
     *
     * @param array Hook parameters
     *
     * @return array|null
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
        $option = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $option->setCallToActionText($this->l('Pay offline'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true));

        return [
            $option
        ];
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

    public function hookActionPaymentConfirmation()
    {
        /* Place your code here. */
    }

    public function hookDisplayPayment()
    {
        /* Place your code here. */
    }

    public function hookDisplayPaymentReturn()
    {
        /* Place your code here. */
    }
}

