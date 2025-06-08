<?php
/**
 * Kontroler AJAX dla modułu MinWartoscZProduktem
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

// Dołącz główny plik modułu, aby mieć dostęp do stałych CONFIG_*
require_once _PS_MODULE_DIR_ . 'minwartosczproduktem/minwartosczproduktem.php';

class MinWartoscZProduktemAjaxModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent(); // Ważne, aby zainicjować kontekst i inne elementy

        // Upewnijmy się, że to żądanie AJAX
        if (!$this->isXmlHttpRequest() || !Tools::isSubmit('action') || Tools::getValue('action') !== 'checkCartCondition') {
            PrestaShopLogger::addLog('MWZP AjaxCtrl: Nieautoryzowane lub niepoprawne żądanie.', 3, null, null, null, true);
            $this->ajaxDie(json_encode(['error' => 'Bad request']));
        }

        PrestaShopLogger::addLog('MWZP AjaxCtrl: Żądanie checkCartCondition odebrane.', 1, null, null, null, true);

        $cart = $this->context->cart;
        $configuredProductId = (int)Configuration::get(MinWartoscZProduktem::CONFIG_PRODUCT_ID);
        $configuredMinAmount = (float)Configuration::get(MinWartoscZProduktem::CONFIG_MIN_AMOUNT);

        $response_data = [
            'blockCheckout' => false,
            'message' => '',
            'debug' => [
                'configuredProductId' => $configuredProductId,
                'configuredMinAmount' => $configuredMinAmount,
                'cartId' => (Validate::isLoadedObject($cart) ? $cart->id : 'null'),
                'nbProducts' => (Validate::isLoadedObject($cart) ? $cart->nbProducts() : 0)
            ]
        ];

        if ($configuredProductId == 0 || $configuredMinAmount <= 0) {
            PrestaShopLogger::addLog('MWZP AjaxCtrl: Moduł nie skonfigurowany lub warunek nieaktywny.', 1, null, null, null, true);
            $this->ajaxRender(json_encode($response_data)); // Zwraca blockCheckout: false
            return;
        }

        if (!Validate::isLoadedObject($cart) || $cart->nbProducts() == 0) {
            PrestaShopLogger::addLog('MWZP AjaxCtrl: Koszyk niezaładowany lub pusty.', 1, null, null, null, true);
            $this->ajaxRender(json_encode($response_data)); // Zwraca blockCheckout: false
            return;
        }

        $cartProducts = $cart->getProducts();
        $isTargetProductInCart = false;
        $targetProductValueInCartWithTax = 0;
        $cartTotalProductsValueWithTax = $cart->getOrderTotal(true, Cart::ONLY_PRODUCTS);

        $response_data['debug']['cartTotalProductsValueWithTax'] = $cartTotalProductsValueWithTax;
        $response_data['debug']['cartProductsCount'] = count($cartProducts);

        foreach ($cartProducts as $product) {
            if ((int)$product['id_product'] == $configuredProductId) {
                $isTargetProductInCart = true;
                $targetProductValueInCartWithTax += (float)$product['total_wt'];
            }
        }
        
        $response_data['debug']['isTargetProductInCart'] = $isTargetProductInCart;
        $response_data['debug']['targetProductValueInCartWithTax'] = $targetProductValueInCartWithTax;

        if ($isTargetProductInCart) {
            $valueExcludingTargetProductWithTax = $cartTotalProductsValueWithTax - $targetProductValueInCartWithTax;
            $response_data['debug']['valueExcludingTargetProductWithTax'] = $valueExcludingTargetProductWithTax;

            if ($valueExcludingTargetProductWithTax < $configuredMinAmount) {
                $response_data['blockCheckout'] = true;
                $response_data['message'] = $this->module->l('Minimalna wartość pozostałych produktów w koszyku (z podatkiem, po rabatach) to ') . Tools::displayPrice($configuredMinAmount, $this->context->currency) . $this->module->l('. Obecnie jest to ') . Tools::displayPrice($valueExcludingTargetProductWithTax, $this->context->currency) . $this->module->l('. Dodaj więcej produktów, aby kontynuować.');
                PrestaShopLogger::addLog('MWZP AjaxCtrl: WARUNEK NIESPEŁNIONY. blockCheckout: true. Message: ' . $response_data['message'], 1, null, null, null, true);
            } else {
                 PrestaShopLogger::addLog('MWZP AjaxCtrl: WARUNEK SPEŁNIONY. blockCheckout: false.', 1, null, null, null, true);
            }
        } else {
            PrestaShopLogger::addLog('MWZP AjaxCtrl: Docelowy produkt nie w koszyku. blockCheckout: false.', 1, null, null, null, true);
        }
        
        // Użyj $this->ajaxRender() dla PrestaShop 1.7+
        // Dla starszych wersji (lub jako alternatywa) można użyć die(json_encode(...)) po ustawieniu nagłówka
        // header('Content-Type: application/json');
        $this->ajaxRender(json_encode($response_data));
        // exit; // ajaxRender powinien zakończyć skrypt, ale dla pewności można dodać exit.
    }

    // W PrestaShop 1.7+ ajaxDie jest preferowane, ale ajaxRender jest częścią ModuleFrontController
    // i bardziej odpowiednie dla zwracania danych bez renderowania szablonu.
    // Jeśli ajaxRender powoduje problemy, można spróbować tak:
    /*
    protected function ajaxDie($value = null, $controller = null, $method = null)
    {
        header('Content-Type: application/json');
        parent::ajaxDie($value, $controller, $method);
    }
    */
}