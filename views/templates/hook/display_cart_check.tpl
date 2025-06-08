{* Ten szablon jest celowo minimalistyczny. Logika ukrywania/pokazywania przycisku i wiadomości jest w front.js *}
{* Generuje ukryty div, jeśli warunek nie jest spełniony, JS na jego podstawie działa *}
{if $showMinAmountMessageMWZP}
    <div id="min-wartosc-alert-source-mwzp" style="display:none;">{$minAmountMessageMWZP|escape:'htmlall':'UTF-8'}</div>
{/if}