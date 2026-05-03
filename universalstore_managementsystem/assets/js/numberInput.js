// increment decrement for cart page

document.querySelectorAll('.custom-buttons').forEach(function(wrapper) {
    const numberInput = wrapper.querySelector('input[type="number"]');
    const incrementBtn = wrapper.querySelector('button:nth-of-type(2)');
    const decrementBtn = wrapper.querySelector('button:nth-of-type(1)');

    incrementBtn.addEventListener('click', function increment() {
        const max = parseInt(numberInput.max) || Infinity;
        let value = parseInt(numberInput.value) || 0;
        if (value < max) numberInput.value = value + 1;
    });

    decrementBtn.addEventListener('click', function decrement() {
        const min = parseInt(numberInput.min) || -Infinity;
        let value = parseInt(numberInput.value) || 0;
        if (value > min) numberInput.value = value - 1;
    });

    numberInput.addEventListener('input', function() {
        let value = parseInt(numberInput.value) || 0;
        if (value < parseInt(numberInput.min)) numberInput.value = numberInput.min;
        if (value > parseInt(numberInput.max)) numberInput.value = numberInput.max;
    });
});


// increment decrement for single_product page

function increment() {
    const input = document.getElementById("inputNumber");
    let value = parseInt(input.value, 10);
    if (!isNaN(value) && value < 100) { // Assuming max value is 100
        input.value = value + 1;
    }
}

function decrement() {
    const input = document.getElementById("inputNumber");
    let value = parseInt(input.value, 10);
    if (!isNaN(value) && value > 1) { // Assuming min value is 1
        input.value = value - 1;
    }
}


/*

// Cart update functionality
document.getElementById('update-cart-btn').addEventListener('click', function(){
    const quantities = {};
    document.querySelectorAll('input[name^="quantities["]').forEach(input => {
        const productId = input.name.match(/quantities\[(\d+)\]/)[1];
        quantities[productId] = parseInt(input.value);
    });

    // Show loading
   const cartload = document.getElementById('cart-loading');
    cartload.style.display = 'flex';
    setTimeout(() => cartload.style.display = 'none', 1000); // hide after 2s if no response

    fetch('/cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
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
            // Update subtotals
            for(const id in data.subtotals){
                document.getElementById(`subtotal-${id}`).textContent = data.subtotals[id];
            }
            // Update total
            document.querySelector('.sub-total p').textContent = data.total;
            document.querySelector('.total p').textContent = data.total;
            // Show message
            const msgDiv = document.getElementById('cart-message');
            msgDiv.textContent = data.message;
            msgDiv.style.display = 'block';
            setTimeout(() => msgDiv.style.display = 'none', 5000);
            window.scrollTo({
        top: 500,
        behavior: 'smooth'
        });
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        document.getElementById('cart-loading').style.display = 'none';
        console.error('Error:', error);
        alert('An error occurred while updating the cart.');
    });
});


// Delete product functionality
document.querySelectorAll('.remove-btn').forEach(btn => {
    btn.addEventListener('click', function(){
        const productId = this.getAttribute('data-product-id');
        // Show loading
        const cartload = document.getElementById('cart-loading');
        cartload.style.display = 'flex';
        setTimeout(() => cartload.style.display = 'none', 1000); // hide after 1s if no response

        fetch('/cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                remove_product: '1',
                ajax: '1',
                product_id: productId
            })
        })
        .then(response => response.json())
        .then(data => {
            // Hide loading
            // document.getElementById('cart-loading').style.display = 'none';
            if(data.success){
                // Remove the row
                this.closest('tr').remove();
                // Update total
                document.querySelector('.sub-total p').textContent = data.total;
                document.querySelector('.total p').textContent = data.total;
                // Show message
                const msgDiv = document.getElementById('cart-message');
                msgDiv.textContent = data.message;
                msgDiv.style.display = 'block';
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
                alert(data.message);
            }
        })
        .catch(error => {
            document.getElementById('cart-loading').style.display = 'none';
            console.error('Error:', error);
            alert('An error occurred while deleting the product.');
        });
    });
});

*/