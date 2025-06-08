<?php
/**
 * Kontroler administracyjny dla modułu MinWartoscZProduktem,
 * służący jedynie do przekierowania na stronę konfiguracji modułu.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminMinWartoscZProduktemCustomConfigController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct(); // Wywołaj konstruktor rodzica

        Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->module->name])
        );
    }
}