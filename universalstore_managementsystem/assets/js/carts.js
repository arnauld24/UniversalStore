
// CSRF Token 
//const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
const csrfToken = document.getElementById('csrf_token');
//declare constants
 const errorDiv = document.getElementById('cart-error');
 const msgDiv = document.getElementById('cart-message');
// CSRF TOKEN Regeneration
function UpdateCsrfToken(newToken) {
    if (csrfToken && newToken) {
        csrfToken.value = newToken;
        // console.log("CSRF Token updated successfully.");
    } else {
        console.error("CSRF Token update failed or not found");
    }
}
//Function to Check Csrf token is not empty
function checkEmptyCsrfToken(Token){
    if(!Token){
        const errorDiv = document.getElementById('cart-error');
        console.error('Erreur: No CSRF token found.');
        errorDiv.textContent = 'Session expired. Please reload page.';
        var icon = document.createElement('i');
        icon.className = 'fa-regular fa-bell';
        errorDiv.prepend(icon);
        errorDiv.style.display = 'block';
        setTimeout(() => errorDiv.style.display = 'none', 5000);
        window.scrollTo({
        top: 500,
        behavior: 'smooth'
        });
        return;
    }
}

// Function to check new Csrf Token
function CheckNewCsrfToken(NewToken, oldToken){
     if(NewToken){
    if(NewToken === oldToken){
        //if token = to old token error
        console.error("CSRF Token did not change, potential issue.");
        return false;
    }
    }
    return true;
}
// Function to update the cart count in the navbar
    function updateCartCount(newCount) {
        const cartLink = document.querySelector('a.cart');
        if (!cartLink) return;

        let countSpan = cartLink.querySelector('.cart-item');
        
        if (newCount > 0) {
            if (countSpan) {
                countSpan.firstChild.textContent = newCount;
            } else {
                countSpan = document.createElement('span');
                countSpan.className = 'cart-item';
                countSpan.style.fontSize = '0.75rem';
                countSpan.innerHTML = `${newCount}<span class="visually-hidden">Items in cart</span>`;
                cartLink.appendChild(countSpan);
            }
        } else if (countSpan) {
            countSpan.remove();
        }
    }

    // Update the cart summary badge for a single product (right-hand summary)
    function updateSummaryBadge(productId, newCount) {
        if (!productId) return;
        const summaryItem = document.querySelector(`.sub-cart-items[data-product-id="${productId}"]`);
        if (!summaryItem) return;

        const imageWrapper = summaryItem.querySelector('.image-wrapper');
        if (!imageWrapper) return;

        let badge = imageWrapper.querySelector('.cart-count-badge');
        newCount = parseInt(newCount, 10) || 0;

        if (newCount > 0) {
            if (badge) {
                badge.textContent = newCount;
            } else {
                badge = document.createElement('span');
                badge.className = 'cart-count-badge';
                badge.setAttribute('aria-hidden', 'true');
                badge.textContent = newCount;
                imageWrapper.insertBefore(badge, imageWrapper.firstChild);
            }
        } else if (badge) {
            badge.remove();
        }
    }

    // Update multiple badges using an object map of productId => quantity
    function updateAllSummaryBadges(quantities) {
        if (!quantities || typeof quantities !== 'object') return;
        for (const id in quantities) {
            if (!Object.prototype.hasOwnProperty.call(quantities, id)) continue;
            updateSummaryBadge(id, quantities[id]);
        }
    }

    // Expose to global so other scripts (e.g., add-to-cart) can call it
    window.updateCartSummaryBadge = updateSummaryBadge;
    window.updateAllCartSummaryBadges = updateAllSummaryBadges;

// Cart update functionality
document.getElementById('update-cart-btn').addEventListener('click', function(){
    const quantities = {};
    document.querySelectorAll('input[name^="quantities["]').forEach(input => {
        const productId = input.name.match(/quantities\[(\d+)\]/)[1];
        quantities[productId] = parseInt(input.value);
    });
    
    const currentCsrfToken = csrfToken ? csrfToken.value : '';
    //Check empty token
    checkEmptyCsrfToken(currentCsrfToken);
    
    // Show loading
   const cartload = document.getElementById('cart-loading');
    cartload.style.display = 'flex';
    setTimeout(() => cartload.style.display = 'none', 500); // hide after 1s if no response

    fetch('cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': currentCsrfToken

        },
        credentials: 'same-origin',
        body: JSON.stringify({
            update_cart: '1',
            ajax: '1',
            quantities: quantities
        })
    })
    .then(response => response.json())
    .then(data => {
        // Hide loading
      //  document.getElementById('cart-loading').style.display = 'none';

        if(data.success){
            //check for csrf token receive
            if(CheckNewCsrfToken(data.csrf_token, currentCsrfToken)){
                //update token on success
                UpdateCsrfToken(data.csrf_token);
            } else {
                // CSRF token error, show error and stop
                errorDiv.textContent = 'Something happen while updating Cart. Please reload page.';
                var icon = document.createElement('i');
                icon.className = 'fa-regular fa-bell';
                errorDiv.prepend(icon);
                errorDiv.style.display = 'block';
                setTimeout(() => errorDiv.style.display = 'none', 5000);
                window.scrollTo({
                top: 500,
                behavior: 'smooth'
                });
                return;
            }
            // Update subtotals
            for(const id in data.subtotals){
                document.getElementById(`subtotal-${id}`).textContent = data.subtotals[id] + ' FCFA';
            }
            // Update per-item quantity badges in the cart summary using the quantities we sent
            if (typeof quantities !== 'undefined') {
                updateAllSummaryBadges(quantities);
            }
            // Update total
            document.querySelector('.sub-total p').textContent = data.total + ' FCFA';
            document.querySelector('.total p').textContent = data.total + ' FCFA';
            // Show message
            msgDiv.textContent = data.message;
            msgDiv.style.display = 'block';
            var icon = document.createElement('i');
            icon.className = 'fa-regular fa-bell';
            msgDiv.prepend(icon);
            setTimeout(() => msgDiv.style.display = 'none', 5000);
              if (typeof data.items !== 'undefined') {
            updateCartCount(data.items);
        }
            window.scrollTo({
        top: 500,
        behavior: 'smooth'
        });
        } else {
             //check for csrf token receive
            if(CheckNewCsrfToken(data.csrf_token, currentCsrfToken)){
                //update token on error
                UpdateCsrfToken(data.csrf_token);
            }
            errorDiv.textContent = data.message;
            var icon = document.createElement('i');
            icon.className = 'fa-regular fa-bell';
            errorDiv.prepend(icon);
            errorDiv.style.display = 'block';
            setTimeout(() => errorDiv.style.display = 'none', 5000);
            window.scrollTo({
        top: 500,
        behavior: 'smooth'
        });
        }
    })
    .catch(error => {
        document.getElementById('cart-loading').style.display = 'none';
        errorDiv.textContent = 'An error occurred while updating the cart.';
        errorDiv.style.display = 'block';
        var icon = document.createElement('i');
        icon.className = 'fa-regular fa-bell';
        errorDiv.prepend(icon);
        setTimeout(() => errorDiv.style.display = 'none', 5000);
         window.scrollTo({
        top: 500,
        behavior: 'smooth'
        });
        // Log the actual error message and stack trace
        let errorMessage = 'Unknown error';
        let errorStack = '';

        if (error instanceof Error) {
          errorMessage = error.message || 'Error object with no message';
          errorStack = error.stack || '';
        } else if (typeof error === 'string') {
          errorMessage = error;
        } else if (error && typeof error === 'object') {
          errorMessage = JSON.stringify(error) || 'Object error';
        } else {
          errorMessage = String(error) || 'Unknown error type';
        }

        if (typeof window.logJSError === 'function') {
          window.logJSError(
            'Cart Update Error: ' + errorMessage + (errorStack ? '\nStack: ' + errorStack : ''),
            'error',
            window.location.href
          );
        }
        console.error('Error:', error);
    });
});

// Delete product functionality
document.querySelectorAll('.remove-btn').forEach(btn => {
    btn.addEventListener('click', function(){
        const productId = this.getAttribute('data-product-id');
        const color = this.getAttribute('data-color');
        const variant = this.getAttribute('data-variant');
        const CurrentCsrfToken = csrfToken ? csrfToken.value : '';
        //check empty token
        checkEmptyCsrfToken(CurrentCsrfToken);
        // Show loading
        const cartload = document.getElementById('cart-loading');
        cartload.style.display = 'flex';
        setTimeout(() => cartload.style.display = 'none', 500); // hide after 1s if no response

        fetch('cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': CurrentCsrfToken

            },
            credentials: 'same-origin',
            body: new URLSearchParams({
                remove_product: '1',
                ajax: '1',
                product_id: productId,
                product_color: color,
                product_variant: variant,
            })
        })
        .then(response => response.json())
        .then(data => {
            // Hide loading
            // document.getElementById('cart-loading').style.display = 'none';
            if(data.success){
                //check for csrf token receive
                if(!CheckNewCsrfToken(data.csrf_token, CurrentCsrfToken)){
                    // CSRF token error, show error and stop
                    const errorDiv = document.getElementById('cart-error');
                    errorDiv.textContent = 'Something happen while removing product. Please reload page.';
                    var icon = document.createElement('i');
                    icon.className = 'fa-regular fa-bell';
                    errorDiv.prepend(icon);
                    errorDiv.style.display = 'block';
                    setTimeout(() => errorDiv.style.display = 'none', 5000);
                    window.scrollTo({
                         top: 500,
                          behavior: 'smooth'
                          });
                    return;
                }
                //update token on success
                UpdateCsrfToken(data.csrf_token);
                // Remove the main cart item
                this.closest('.card').remove();
                // Remove the corresponding summary item
                const summaryItem = document.querySelector(`.sub-cart-items[data-product-id="${productId}"]`);
                if (summaryItem) {
                    summaryItem.remove();
                }
                // Update total
                document.querySelector('.sub-total p').textContent = data.total + ' FCFA';
                document.querySelector('.total p').textContent = data.total + ' FCFA';
                // Show message
                const msgDiv = document.getElementById('cart-message');
                msgDiv.textContent = data.message;
                msgDiv.style.display = 'block';
                var icon = document.createElement('i');
                icon.className = 'fa-regular fa-bell';
                msgDiv.prepend(icon);
                  if (typeof data.items !== 'undefined') {
                     updateCartCount(data.items);
                 }
                setTimeout(() => msgDiv.style.display = 'none', 5000);
                window.scrollTo({
                     top: 500,
                     behavior: 'smooth'
                          });
                // If cart empty, reload page
             if(data.items === 0){
                    location.reload();
                }
            } else {
                //check for csrf token receive
                CheckNewCsrfToken(data.csrf_token, CurrentCsrfToken);
                //update token on error
                UpdateCsrfToken(data.csrf_token);
                const errorDiv = document.getElementById('cart-error');
                errorDiv.textContent = data.message;
                errorDiv.style.display = 'block';
                var icon = document.createElement('i');
                icon.className = 'fa-regular fa-bell';
                errorDiv.prepend(icon);
                setTimeout(() => errorDiv.style.display = 'none', 5000);
                 window.scrollTo({
                       top: 500,
                        behavior: 'smooth'
                        });
            }
        })
        .catch(error => {
            document.getElementById('cart-loading').style.display = 'none';
            const errorDiv = document.getElementById('cart-error');
            errorDiv.textContent = 'An error occurred while removing the product.';
            errorDiv.style.display = 'block';
            var icon = document.createElement('i');
            icon.className = 'fa-regular fa-bell';
            errorDiv.prepend(icon);
            setTimeout(() => errorDiv.style.display = 'none', 5000);
             window.scrollTo({
                      top: 500,
                       behavior: 'smooth'
                      });
            console.error('Error:', error);
        })
    });
});