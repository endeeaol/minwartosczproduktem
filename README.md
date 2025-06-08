Prestashop 8.2, PHP 8.1
Minimoduł umożliwia wybranie z listy rozwijanej w panelu administracyjnym prestashop jeden produkt i podanie wartości zamówienia. W koszyku klienta moduł nie dopuszcza złożenie zamówienia, jeśli łączna wartość koszyka bez kosztów dostawy i nie wliczając wybranego produktu będzie niższa niż zadana w panelu administratora. 
Wtedy wyświetla stosowne komunikaty w koszyku i ukrywa przycisk 'realizuj zamówienie'. Jeśli wartość w koszyku zwiększamy, po osiągnięciu minimum komunikat znika, a przycisk 'realizuj' się pojawia. 
W pliku motywu /checkout/_partials/cart-detailed-actions.tpl, wskutek logistyki dodaliśmy ID:
id="cart-actions-container-mwzp"
id="main-checkout-button-mwzp"

{block name='cart_detailed_actions'}
  <div id="cart-actions-container-mwzp" class="checkout cart-detailed-actions js-cart-detailed-actions card-block">
    {if $cart.minimalPurchaseRequired}
      <div class="alert alert-warning" role="alert">
        {$cart.minimalPurchaseRequired}
      </div>
      <div class="text-center">
        <button type="button" class="btn btn-primary disabled" disabled>{l s='Proceed to checkout' d='Shop.Theme.Actions'}</button>
      </div>
    {elseif empty($cart.products) }
      <div class="text-center">
        <button type="button" class="btn btn-primary disabled" disabled>{l s='Proceed to checkout' d='Shop.Theme.Actions'}</button>
      </div>
    {else}
      <div class="text-center">
        <a id="main-checkout-button-mwzp" href="{$urls.pages.order}" class="btn btn-primary">{l s='Finalize the order' d='Shop.Theme.Actions'}</a>
        {hook h='displayExpressCheckout'}
      </div>
    {/if}
  </div>
{/block}
 
