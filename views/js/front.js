// Funkcje updateCheckoutButtonVisibility i checkConditionViaAjaxAndUpdateDOM
// pozostają takie same jak w poprzedniej działającej wersji.
// Poniżej wklejam je dla kompletności, ale bez zmian w ich wewnętrznej logice.

function updateCheckoutButtonVisibility(blockCheckout, messageText) {
    console.log('MWZP JS: updateCheckoutButtonVisibility START. blockCheckout:', blockCheckout, 'Message:', messageText);

    const cartActionsContainer = document.getElementById('cart-actions-container-mwzp'); 
    const originalCheckoutButton = document.getElementById('main-checkout-button-mwzp'); 
    
    console.log('MWZP JS: cartActionsContainer (ID):', cartActionsContainer);
    console.log('MWZP JS: originalCheckoutButton (ID):', originalCheckoutButton);

    let customMessageDiv = document.getElementById('custom-cart-restriction-message-mwzp');
    if (customMessageDiv) {
        customMessageDiv.remove(); 
        customMessageDiv = null;
    }

    if (blockCheckout && cartActionsContainer) {
        // Przycisk powinien być już ukryty przez optymistyczną aktualizację,
        // ale dla pewności ustawiamy styl ponownie, jeśli np. pierwszy stan był inny.
        if (originalCheckoutButton) {
            originalCheckoutButton.style.display = 'none';
            console.log('MWZP JS: Przycisk (ID: main-checkout-button-mwzp) potwierdzony jako ukryty.');
        } else {
            console.warn('MWZP JS: Nie znaleziono przycisku (ID: main-checkout-button-mwzp) do ukrycia.');
        }
        
        customMessageDiv = document.createElement('div');
        customMessageDiv.id = 'custom-cart-restriction-message-mwzp';
        customMessageDiv.className = 'alert alert-warning text-center'; 
        customMessageDiv.setAttribute('role', 'alert');
        customMessageDiv.innerHTML = messageText; 

        const expressCheckoutHookElement = cartActionsContainer.querySelector('div > div#express-checkout-element, div#js-paypal-express-checkout-container, [id*="ps_checkout-express-button"]');
        if (expressCheckoutHookElement && expressCheckoutHookElement.parentNode === cartActionsContainer) {
             cartActionsContainer.insertBefore(customMessageDiv, expressCheckoutHookElement);
        } else {
            cartActionsContainer.appendChild(customMessageDiv);
        }
        console.log('MWZP JS: Komunikat wyświetlony: ', messageText);

    } else {
        if (originalCheckoutButton) {
            originalCheckoutButton.style.display = ''; 
            console.log('MWZP JS: Przycisk (ID: main-checkout-button-mwzp) pokazany.');
        }
        if (cartActionsContainer) {
             console.log('MWZP JS: Blokada nieaktywna lub brak kontenera. Komunikat (jeśli był) usunięty.');
        } else {
            console.warn('MWZP JS: Kontener #cart-actions-container-mwzp nie znaleziony.');
        }
    }
    console.log('MWZP JS: updateCheckoutButtonVisibility KONIEC.');
}

function checkConditionViaAjaxAndUpdateDOM() {
    console.log('MWZP JS: checkConditionViaAjaxAndUpdateDOM START. Wykonywanie zapytania AJAX do:', mwzpAjaxUrl);
    
    if (typeof mwzpAjaxUrl === 'undefined' || mwzpAjaxUrl === null) {
        console.error('MWZP JS: URL do kontrolera AJAX (mwzpAjaxUrl) nie jest zdefiniowany!');
        const alertSourceDiv = document.getElementById('min-wartosc-alert-source-mwzp');
        if (alertSourceDiv) {
            updateCheckoutButtonVisibility(true, alertSourceDiv.innerHTML);
        } else {
            updateCheckoutButtonVisibility(false, '');
        }
        return;
    }

    fetch(mwzpAjaxUrl + '&rand=' + new Date().getTime(), { 
        method: 'GET', 
        headers: {
            'X-Requested-With': 'XMLHttpRequest' 
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.statusText);
        }
        return response.json();
    })
    .then(data => {
        console.log('MWZP JS: Otrzymano odpowiedź AJAX:', data);
        if (data.error) {
            console.error('MWZP JS: Błąd w odpowiedzi AJAX:', data.error);
            updateCheckoutButtonVisibility(false, ''); 
        } else {
            updateCheckoutButtonVisibility(data.blockCheckout, data.message);
        }
    })
    .catch(error => {
        console.error('MWZP JS: Błąd podczas wykonywania zapytania AJAX:', error);
        updateCheckoutButtonVisibility(false, ''); 
    });
}
// KONIEC NIEZMIENIONYCH FUNKCJI


// Inicjalizacja przy pierwszym załadowaniu strony
document.addEventListener('DOMContentLoaded', function() {
    console.log('MWZP JS: DOMContentLoaded.');
    const alertSourceDiv = document.getElementById('min-wartosc-alert-source-mwzp');
    if (alertSourceDiv) {
        console.log('MWZP JS: DOMContentLoaded - Inicjalizacja stanu na podstawie diva z PHP hooka.');
        setTimeout(function() { 
            updateCheckoutButtonVisibility(true, alertSourceDiv.innerHTML);
        }, 150); // Zachowujemy to opóźnienie dla stanu początkowego
    } else {
        console.log('MWZP JS: DOMContentLoaded - Brak diva z PHP hooka, zakładam brak blokady.');
         setTimeout(function() {
            updateCheckoutButtonVisibility(false, '');
        }, 150);
    }
});

// Aktualizacja po zdarzeniu 'updatedCart'
if (typeof prestashop !== 'undefined') {
    prestashop.on('updatedCart', function(event) {
        console.log('MWZP JS: Zdarzenie "updatedCart" wykryte. Dane zdarzenia:', event);

        // --- OPTYMISTYCZNE UKRYCIE PRZYCISKU ---
        // Natychmiast ukrywamy przycisk, aby zapobiec kliknięciu podczas przetwarzania AJAX.
        const originalCheckoutButton = document.getElementById('main-checkout-button-mwzp');
        if (originalCheckoutButton) {
            originalCheckoutButton.style.display = 'none'; 
            console.log('MWZP JS: "updatedCart" - Przycisk OPTYMISTYCZNIE ukryty w oczekiwaniu na AJAX.');
        }
        // Można tu opcjonalnie dodać jakiś prosty komunikat "Przetwarzanie..." lub spinner,
        // ale na razie samo ukrycie przycisku powinno wystarczyć.
        // --- KONIEC OPTYMISTYCZNEGO UKRYCIA ---

        // Kontynuujemy z zapytaniem AJAX z minimalnym opóźnieniem technicznym.
        // Głównym opóźnieniem, które użytkownik zobaczy, będzie teraz czas odpowiedzi AJAX.
        setTimeout(checkConditionViaAjaxAndUpdateDOM, 50); // <-- ZMNIEJSZONO z 100ms do 50ms (lub nawet 0)
    });
} else {
    console.warn('MWZP JS: Obiekt "prestashop" nie jest zdefiniowany. Aktualizacje koszyka AJAX mogą nie być śledzone poprawnie.');
}