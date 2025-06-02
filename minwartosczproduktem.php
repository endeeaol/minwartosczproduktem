<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class MinWartoscZProduktem extends Module
{
    const CONFIG_PRODUCT_ID = 'MWZP_PRODUCT_ID';
    const CONFIG_MIN_AMOUNT = 'MWZP_MIN_AMOUNT';

    public function __construct()
    {
        $this->name = 'minwartosczproduktem';
        $this->tab = 'front_office_features';
        $this->version = '1.0.1'; // Zwiększona wersja po zmianach
        $this->author = 'Twoje Imię';
        $this->need_instance = 1;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Minimalna Wartość Koszyka z Produktem');
        $this->description = $this->l('Blokuje zamówienie, jeśli określony produkt jest w koszyku, a reszta zamówienia (z podatkiem, po rabatach) nie osiąga minimalnej kwoty.');

        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        if (!parent::install() ||
            !$this->registerHook('displayShoppingCartFooter') ||
            !$this->registerHook('actionFrontControllerSetMedia')) {
            return false;
        }
        Configuration::updateValue(self::CONFIG_PRODUCT_ID, 0);
        Configuration::updateValue(self::CONFIG_MIN_AMOUNT, 0);
        return true;
    }

    public function uninstall()
    {
        Configuration::deleteByName(self::CONFIG_PRODUCT_ID);
        Configuration::deleteByName(self::CONFIG_MIN_AMOUNT);
        return parent::uninstall();
    }

    public function getContent()
    {
        $output = '';
        if (Tools::isSubmit('submit' . $this->name)) {
            $productId = (int)Tools::getValue(self::CONFIG_PRODUCT_ID);
            $minAmount = (float)str_replace(',', '.', Tools::getValue(self::CONFIG_MIN_AMOUNT)); // Akceptuj przecinek jako separator

            Configuration::updateValue(self::CONFIG_PRODUCT_ID, $productId);
            Configuration::updateValue(self::CONFIG_MIN_AMOUNT, $minAmount);
            $output .= $this->displayConfirmation($this->l('Ustawienia zaktualizowane'));
        }
        return $output . $this->renderForm();
    }

    protected function renderForm()
    {
        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->title = $this->displayName;
        $helper->submit_action = 'submit' . $this->name;

        $products = Product::getProducts($this->context->language->id, 0, 0, 'id_product', 'ASC', false, true);
        $productOptions = [];
        $productOptions[] = ['id_option' => 0, 'name' => $this->l('--- Wybierz produkt ---')];
        foreach ($products as $product) {
            $productOptions[] = [
                'id_option' => $product['id_product'],
                'name' => $product['name'] . ' (ID: ' . $product['id_product'] . ')',
            ];
        }
        
        $fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->l('Ustawienia modułu'),
                'icon' => 'icon-cogs',
            ],
            'input' => [
                [
                    'type' => 'select',
                    'label' => $this->l('Wybierz produkt'),
                    'name' => self::CONFIG_PRODUCT_ID,
                    'desc' => $this->l('Wybierz produkt, którego obecność w koszyku aktywuje sprawdzanie warunku.'),
                    'options' => [
                        'query' => $productOptions,
                        'id' => 'id_option',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Minimalna kwota pozostałych produktów'),
                    'name' => self::CONFIG_MIN_AMOUNT,
                    'desc' => $this->l('Wprowadź minimalną kwotę (Z PODATKIEM, PO RABATACH) jaką muszą mieć pozostałe produkty w koszyku (nie licząc wybranego powyżej). Użyj kropki jako separatora dziesiętnego.'),
                    'suffix' => $this->context->currency->iso_code,
                    'class' => 'fixed-width-sm'
                ],
            ],
            'submit' => [
                'title' => $this->l('Zapisz'),
                'class' => 'btn btn-default pull-right',
            ],
        ];

        $helper->fields_value[self::CONFIG_PRODUCT_ID] = Configuration::get(self::CONFIG_PRODUCT_ID);
        $helper->fields_value[self::CONFIG_MIN_AMOUNT] = Configuration::get(self::CONFIG_MIN_AMOUNT);

        return $helper->generateForm($fields_form);
    }
    
public function hookActionFrontControllerSetMedia()
{
    PrestaShopLogger::addLog('MWZP hookActionFrontControllerSetMedia - Strona: ' . $this->context->controller->getPageName() . ' | Controller: ' . get_class($this->context->controller), 1, null, null, null, true);

    if ($this->context->controller->getPageName() === 'cart') {
        $this->context->controller->registerJavascript(
            'module-' . $this->name . '-front',
            'modules/' . $this->name . '/views/js/front.js',
            ['position' => 'bottom', 'priority' => 155]
        );
        PrestaShopLogger::addLog('MWZP hookActionFrontControllerSetMedia - JS (' . $this->name . '/views/js/front.js) zarejestrowany dla strony koszyka.', 1, null, null, null, true);

        // Definiowanie zmiennej JavaScript z URL-em do naszego kontrolera AJAX
        $ajaxControllerUrl = $this->context->link->getModuleLink(
            $this->name, // Nazwa modułu
            'ajax',     // Nazwa naszego kontrolera (bez 'ModuleFrontController' i 'MinWartoscZProduktem')
            ['action' => 'checkCartCondition', 'ajax' => 1] // Dodatkowe parametry
        );

        Media::addJsDef(['mwzpAjaxUrl' => $ajaxControllerUrl]);
        PrestaShopLogger::addLog('MWZP hookActionFrontControllerSetMedia - mwzpAjaxUrl zdefiniowany: ' . $ajaxControllerUrl, 1, null, null, null, true);
    }
}

    public function hookDisplayShoppingCartFooter($params)
    {
        PrestaShopLogger::addLog('MWZP hookDisplayShoppingCartFooter - START.', 1, null, null, null, true);

        $configuredProductId = (int)Configuration::get(self::CONFIG_PRODUCT_ID);
        $configuredMinAmount = (float)Configuration::get(self::CONFIG_MIN_AMOUNT);

        PrestaShopLogger::addLog(sprintf('MWZP hookDisplayShoppingCartFooter - Configured Product ID: %d, Configured Min Amount: %.2f', $configuredProductId, $configuredMinAmount), 1, null, null, null, true);

        if ($configuredProductId == 0 || $configuredMinAmount <= 0) {
            PrestaShopLogger::addLog('MWZP hookDisplayShoppingCartFooter - Moduł nie skonfigurowany lub warunek nieaktywny. Kończenie.', 1, null, null, null, true);
            return;
        }

        $cart = $this->context->cart;
        if (!Validate::isLoadedObject($cart) || $cart->nbProducts() == 0) {
             PrestaShopLogger::addLog('MWZP hookDisplayShoppingCartFooter - Koszyk niezaładowany lub pusty. Kończenie.', 1, null, null, null, true);
            return;
        }

        $cartProducts = $cart->getProducts(); // Pobierz aktualne produkty z koszyka
        $isTargetProductInCart = false;
        $targetProductValueInCartWithTax = 0;

        // Pobierz całkowitą wartość produktów w koszyku Z PODATKIEM, po rabatach
        $cartTotalProductsValueWithTax = $cart->getOrderTotal(true, Cart::ONLY_PRODUCTS);
        PrestaShopLogger::addLog(sprintf('MWZP hookDisplayShoppingCartFooter - Cart Total (with tax, products only): %.2f', $cartTotalProductsValueWithTax), 1, null, null, null, true);

        PrestaShopLogger::addLog('MWZP hookDisplayShoppingCartFooter - Iteracja po produktach w koszyku:', 1, null, null, null, true);
        foreach ($cartProducts as $product) {
            PrestaShopLogger::addLog(sprintf('MWZP hookDisplayShoppingCartFooter - Produkt w koszyku: ID: %d, Nazwa: %s, Ilość: %d, Cena jednostkowa (z tax): %.2f, Suma (z tax): %.2f, total_wt (z getProducts): %.2f', 
                $product['id_product'], 
                $product['name'], 
                $product['quantity'],
                $product['price_wt'], // Cena jednostkowa z podatkiem
                $product['total_wt'], // Całkowita cena linii produktu z podatkiem (uwzględnia rabaty specyficzne dla produktu)
                $product['total_wt'] // Dla spójności logu, to ta sama wartość
            ), 1, null, null, null, true);

            if ((int)$product['id_product'] == $configuredProductId) {
                $isTargetProductInCart = true;
                // Używamy 'total_wt', która jest sumą dla linii produktu Z PODATKIEM, po rabatach specyficznych dla produktu
                $targetProductValueInCartWithTax += (float)$product['total_wt'];
            }
        }
        PrestaShopLogger::addLog(sprintf('MWZP hookDisplayShoppingCartFooter - Docelowy produkt (ID: %d) jest w koszyku: %s, Jego wartość (z tax): %.2f', $configuredProductId, $isTargetProductInCart ? 'Tak' : 'Nie', $targetProductValueInCartWithTax), 1, null, null, null, true);
        
        $showMinAmountMessage = false;
        $message = '';

        if ($isTargetProductInCart) {
            $valueExcludingTargetProductWithTax = $cartTotalProductsValueWithTax - $targetProductValueInCartWithTax;
            PrestaShopLogger::addLog(sprintf('MWZP hookDisplayShoppingCartFooter - Wartość pozostałych produktów (z tax): %.2f, Skonfigurowana min. kwota: %.2f', $valueExcludingTargetProductWithTax, $configuredMinAmount), 1, null, null, null, true);

            if ($valueExcludingTargetProductWithTax < $configuredMinAmount) {
                $showMinAmountMessage = true;
                $message = $this->l('Minimalna wartość pozostałych produktów w koszyku (z podatkiem, po rabatach) to ') . Tools::displayPrice($configuredMinAmount, $this->context->currency) . $this->l('. Obecnie jest to ') . Tools::displayPrice($valueExcludingTargetProductWithTax, $this->context->currency) . $this->l('. Dodaj więcej produktów, aby kontynuować.');
                PrestaShopLogger::addLog('MWZP hookDisplayShoppingCartFooter - WARUNEK NIESPEŁNIONY. Wyświetlanie wiadomości.', 1, null, null, null, true);
            } else {
                PrestaShopLogger::addLog('MWZP hookDisplayShoppingCartFooter - WARUNEK SPEŁNIONY.', 1, null, null, null, true);
            }
        } else {
             PrestaShopLogger::addLog('MWZP hookDisplayShoppingCartFooter - Docelowy produkt NIE ZNALEZIONY w koszyku. Brak restrykcji.', 1, null, null, null, true);
        }
        
        $this->context->smarty->assign([
            'showMinAmountMessageMWZP' => $showMinAmountMessage,
            'minAmountMessageMWZP' => $message,
        ]);
        PrestaShopLogger::addLog('MWZP hookDisplayShoppingCartFooter - Zmienne przypisane do Smarty. showMinAmountMessageMWZP: ' . ($showMinAmountMessage ? 'true' : 'false'), 1, null, null, null, true);
        PrestaShopLogger::addLog('MWZP hookDisplayShoppingCartFooter - KONIEC.', 1, null, null, null, true);

        return $this->display(__FILE__, 'views/templates/hook/display_cart_check.tpl');
    }
}