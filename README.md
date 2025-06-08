
<p><strong>Minimalna wartość koszyka, gdy znajduje się w nim wskazany produkt. Prestashop 8.2, PHP 8.1</strong></p>
<p>Minimoduł umożliwia wybranie z listy rozwijanej w panelu administracyjnym prestashop jeden produkt i podanie wartości zamówienia. </p>
<p>W koszyku klienta moduł nie dopuszcza złożenie zamówienia, jeśli łączna wartość koszyka bez kosztów dostawy i nie wliczając wybranego produktu będzie niższa niż zadana w panelu administratora, a w koszyku znajdzie się ten wybrany produkt. </p>
<p>Limit zadziała tylko wtedy, gdy w koszyku znajduje się ten jeden wskazany produkt, którego limit dotyczy, gdy go usuwamy, limity koszyka nie dotyczą.</p>
<p>Wtedy wyświetla stosowne komunikaty w koszyku i ukrywa przycisk 'realizuj zamówienie'. Jeśli wartość w koszyku zwiększamy, po osiągnięciu minimum komunikat znika, a przycisk 'realizuj' się pojawia. </p>
<p>W pliku motywu /checkout/_partials/cart-detailed-actions.tpl, wskutek logistyki dodaliśmy ID:<br>
id="cart-actions-container-mwzp"<br>
id="main-checkout-button-mwzp"</p>


```smarty
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
 ```
