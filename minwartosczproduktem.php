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
        $this->version = '1.0.10'; // Kolejna próba z zakładkami
        $this->author = 'BESTLAB Ernest';
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
            !$this->registerHook('actionFrontControllerSetMedia') ||
            !$this->installTabs()) { // Wywołujemy naszą nową metodę
            return false;
        }
        Configuration::updateValue(self::CONFIG_PRODUCT_ID, 0);
        Configuration::updateValue(self::CONFIG_MIN_AMOUNT, 0);
        return true; 
    }
    
    public function uninstall()
    {
        if (!$this->uninstallTabs()) { // Wywołujemy naszą nową metodę
             PrestaShopLogger::addLog('MWZP: Nie udało się odinstalować zakładek.', 2, null, null, null, true);
        }
        Configuration::deleteByName(self::CONFIG_PRODUCT_ID);
        Configuration::deleteByName(self::CONFIG_MIN_AMOUNT);
        return parent::uninstall();
    }

    protected function installTabs()
    {
        $languages = Language::getLanguages(true); // true aby dostać wszystkie, aktywne i nieaktywne
        
        // Nazwa techniczna dla "dummy" kontrolera (bez "Controller" na końcu)
        $moduleControllerClassName = 'AdminMinWartoscZProduktemCustomConfig';
        $moduleTabDisplayName = $this->l('Limit kwotowy zamówienia');

        // 1. Stwórz zakładkę dla naszego "dummy" kontrolera
        // Sprawdź, czy już nie istnieje
        $tabId = (int)Tab::getIdFromClassName($moduleControllerClassName);
        if (!$tabId) {
            $tab = new Tab();
            $tab->class_name = $moduleControllerClassName;
            $tab->module = $this->name; // Powiązanie z modułem jest ważne dla ModuleAdminController
            $tab->id_parent = 0; // Tymczasowo top-level lub pod sekcją "Ulepszenia" (CONFIGURE)
                                 // $tab->id_parent = (int)Tab::getIdFromClassName('CONFIGURE');
            $tab->active = 1;
            foreach ($languages as $lang) {
                $tab->name[$lang['id_lang']] = $moduleTabDisplayName;
            }
            if (!$tab->add()) {
                $this->_errors[] = $this->l('Nie udało się utworzyć zakładki modułu: ') . $moduleTabDisplayName;
                return false;
            }
            $tabId = $tab->id; // Pobierz ID nowo utworzonej zakładki
        } else {
            // Zakładka już istnieje, upewnij się, że jest aktywna i ma poprawną nazwę
            $tab = new Tab($tabId);
            $tab->active = 1;
            foreach ($languages as $lang) {
                $tab->name[$lang['id_lang']] = $moduleTabDisplayName;
            }
            $tab->update(); // Zaktualizuj istniejącą
        }
        

        // 2. Znajdź lub utwórz grupę "Skrypty własne"
        $parentTabClassName = 'AdminParentOwnScripts';
        $parentTabDisplayName = $this->l('Skrypty własne');
        $parentTabId = (int)Tab::getIdFromClassName($parentTabClassName);

        if (!$parentTabId) {
            $parentTab = new Tab();
            $parentTab->class_name = $parentTabClassName;
            $parentTab->module = ''; // To jest pusta zakładka-kontener
            // Umieśćmy "Skrypty własne" w sekcji "Ulepszenia" (Improve)
            $improveSectionParentId = (int)Tab::getIdFromClassName('CONFIGURE');
            if (!$improveSectionParentId) { // Fallback
                $improveSectionParentId = (int)Tab::getIdFromClassName('AdminParentModulesSf');
                 if (!$improveSectionParentId) { $improveSectionParentId = 0; }
            }
            $parentTab->id_parent = $improveSectionParentId; 
            $parentTab->active = 1;
            $parentTab->icon = 'icon-cogs'; // Ikona dla grupy
            foreach ($languages as $lang) {
                $parentTab->name[$lang['id_lang']] = $parentTabDisplayName;
            }
            if (!$parentTab->add()) {
                $this->_errors[] = $this->l('Nie udało się utworzyć nadrzędnej zakładki grupy: ') . $parentTabDisplayName;
                // Jeśli nie udało się utworzyć grupy, usuń wcześniej utworzoną zakładkę modułu, aby nie została osierocona
                if (isset($tab) && $tab->id) { $tab->delete(); }
                return false;
            }
            $parentTabId = $parentTab->id;
        }

        // 3. Przenieś/upewnij się, że zakładka modułu jest pod grupą "Skrypty własne"
        // Musimy załadować obiekt Tab ponownie, jeśli był tylko ID
        if (!isset($tab) || !$tab->id) { // Jeśli zakładka istniała i nie tworzyliśmy jej obiektu
            $tab = new Tab($tabId);
        }
        
        if ($tab->id_parent != $parentTabId) {
            $tab->id_parent = $parentTabId;
            if (!$tab->update()) {
                $this->_errors[] = $this->l('Nie udało się przenieść zakładki modułu do grupy.');
                return false;
            }
        }
        
        return true;
    }

    protected function uninstallTabs()
    {
        $moduleControllerClassName = 'AdminMinWartoscZProduktemCustomConfig';
        $tabId = (int)Tab::getIdFromClassName($moduleControllerClassName);
        if ($tabId) {
            $tab = new Tab($tabId);
            if (Validate::isLoadedObject($tab)) {
                $tab->delete(); // Błąd nie powinien zatrzymać deinstalacji reszty
            }
        }

        // Opcjonalnie: usuń grupę "Skrypty własne", jeśli jest pusta.
        // Kod z Twojego przykładu jest dobry, ale uproszczę go lekko.
        $parentTabClassName = 'AdminParentOwnScripts';
        $parentTabId = (int)Tab::getIdFromClassName($parentTabClassName);
        if ($parentTabId) {
            // Sprawdź, czy są jakieś inne aktywne zakładki pod tym rodzicem
            $children = Tab::getTabs($this->context->language->id, $parentTabId);
            if (empty($children)) { // Jeśli nie ma dzieci, usuń rodzica
                $parentTab = new Tab($parentTabId);
                if (Validate::isLoadedObject($parentTab)) {
                    $parentTab->delete();
                }
            }
        }
        return true;
    }

    // getContent() i reszta metod (renderForm, hooki) pozostają takie same jak w ostatniej działającej wersji front-endu
    // Poniżej wklejam je dla kompletności, zakładając, że są to te wersje, które dobrze działały na froncie.

    public function getContent()
    {
        // Ta metoda jest teraz wywoływana, gdy PrestaShop kieruje na konfigurację modułu,
        // np. przez AdminModules&configure=minwartosczproduktem.
        // Nasz "dummy" kontroler AdminMinWartoscZProduktemCustomConfigController też tutaj przekierowuje.
        $output = '';
        if (Tools::isSubmit('submit' . $this->name)) {
            $productId = (int)Tools::getValue(self::CONFIG_PRODUCT_ID);
            $minAmount = (float)str_replace(',', '.', Tools::getValue(self::CONFIG_MIN_AMOUNT)); 
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
        if ($this->context->controller->getPageName() === 'cart') {
            $this->context->controller->registerJavascript(
                'module-' . $this->name . '-front',
                'modules/' . $this->name . '/views/js/front.js',
                ['position' => 'bottom', 'priority' => 155]
            );
            $ajaxControllerUrl = $this->context->link->getModuleLink(
                $this->name, 
                'ajax',     
                ['action' => 'checkCartCondition', 'ajax' => 1, '_token' => Tools::getToken(false)] 
            );
            Media::addJsDef(['mwzpAjaxUrl' => $ajaxControllerUrl]);
        }
    }

    public function hookDisplayShoppingCartFooter($params)
    {
        $configuredProductId = (int)Configuration::get(self::CONFIG_PRODUCT_ID);
        $configuredMinAmount = (float)Configuration::get(self::CONFIG_MIN_AMOUNT);
        if ($configuredProductId == 0 || $configuredMinAmount <= 0) {
            return;
        }
        $cart = $this->context->cart;
        if (!Validate::isLoadedObject($cart) || $cart->nbProducts() == 0) {
            return;
        }
        $cartProducts = $cart->getProducts(); 
        $isTargetProductInCart = false;
        $targetProductValueInCartWithTax = 0;
        $cartTotalProductsValueWithTax = $cart->getOrderTotal(true, Cart::ONLY_PRODUCTS);
        foreach ($cartProducts as $product) {
            if ((int)$product['id_product'] == $configuredProductId) {
                $isTargetProductInCart = true;
                $targetProductValueInCartWithTax += (float)$product['total_wt'];
            }
        }
        $showMinAmountMessage = false;
        $message = '';
        if ($isTargetProductInCart) {
            $valueExcludingTargetProductWithTax = $cartTotalProductsValueWithTax - $targetProductValueInCartWithTax;
            if ($valueExcludingTargetProductWithTax < $configuredMinAmount) {
                $showMinAmountMessage = true;
                $message = $this->l('Minimalna wartość pozostałych produktów w koszyku przy zakupie biletu to ') . Tools::displayPrice($configuredMinAmount, $this->context->currency) . $this->l('. Obecnie jest to ') . Tools::displayPrice($valueExcludingTargetProductWithTax, $this->context->currency) . $this->l('. Dodaj więcej produktów, aby kontynuować.');
            }
        }
        $this->context->smarty->assign([
            'showMinAmountMessageMWZP' => $showMinAmountMessage,
            'minAmountMessageMWZP' => $message,
        ]);
        return $this->display(__FILE__, 'views/templates/hook/display_cart_check.tpl');
    }
}