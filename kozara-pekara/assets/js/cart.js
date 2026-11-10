
'use strict';


function addToCart(productId, btn, quantity = 1) {
    if (!productId) return;

    const originalText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.classList.add('loading');
        btn.innerHTML = '⏳';
    }

    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('product_id', productId);
    formData.append('quantity', quantity);

    fetch('/cart.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
    })
    .then(res => {
        if (!res.ok) throw new Error('Network error');
        return res.json();
    })
    .then(data => {
        if (data.success) {
            updateCartBadge(data.count);
            showToast('Dodano u korpu! 🛒', 'success');

            if (btn) {
                btn.innerHTML = '✓';
                btn.style.background = 'var(--clr-success)';
            }
        } else {
            showToast(data.message || 'Greška pri dodavanju.', 'error');
            if (btn) btn.innerHTML = originalText;
        }
    })
    .catch(() => {
        showToast('Greška. Pokušajte ponovo.', 'error');
        if (btn) btn.innerHTML = originalText;
    })
    .finally(() => {
        if (btn) {
            btn.classList.remove('loading');
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.style.background = '';
            }, 1500);
        }
    });
}


function updateCartBadge(count) {
    const badgeIds = ['cartBadge', 'cartBadgeInline', 'cartBadgeMenu'];

    badgeIds.forEach((id) => {
        const badge = document.getElementById(id);
        if (!badge) return;

        badge.textContent = count;

        if (count > 0) {
            badge.classList.remove('cart-badge-hidden');
            badge.style.display = '';
            badge.classList.remove('bounce');
            void badge.offsetWidth;
            badge.classList.add('bounce');
        } else {
            badge.classList.add('cart-badge-hidden');
        }
    });
}


function changeQty(productId, delta) {
    const qtyEl = document.getElementById('qty-' + productId);
    if (!qtyEl) return;

    const currentQty = parseInt(qtyEl.textContent);
    const newQty     = Math.max(0, currentQty + delta);

    qtyEl.textContent = newQty;

    updateLocalSubtotal(productId, newQty);

    if (newQty === 0) {
        const cartItem = document.querySelector(`[data-product-id="${productId}"]`);
        if (cartItem) {
            cartItem.style.opacity = '0';
            cartItem.style.transform = 'translateX(-20px)';
            cartItem.style.transition = 'all 0.3s ease';
        }
    }

    const formData = new FormData();
    formData.append('action', newQty > 0 ? 'update' : 'remove');
    formData.append('product_id', productId);
    formData.append('quantity', newQty);

    fetch('/cart.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            updateCartBadge(data.count);
            updateCartTotal(data);

            if (newQty === 0) {
                const cartItem = document.querySelector(`[data-product-id="${productId}"]`);
                if (cartItem) {
                    setTimeout(() => cartItem.remove(), 300);
                    checkEmptyCart();
                }
            }
        }
    })
    .catch(() => {
        qtyEl.textContent = currentQty;
        updateLocalSubtotal(productId, currentQty);
        showToast('Greška. Pokušajte ponovo.', 'error');
    });
}


function removeItem(productId) {
    const cartItem = document.querySelector(`[data-product-id="${productId}"]`);
    if (cartItem) {
        cartItem.style.opacity = '0';
        cartItem.style.transform = 'translateX(-20px)';
        cartItem.style.transition = 'all 0.3s ease';
    }

    const formData = new FormData();
    formData.append('action', 'remove');
    formData.append('product_id', productId);

    fetch('/cart.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            updateCartBadge(data.count);
            updateCartTotal(data);
            setTimeout(() => {
                if (cartItem) cartItem.remove();
                checkEmptyCart();
            }, 300);
        }
    })
    .catch(() => {
        if (cartItem) {
            cartItem.style.opacity = '1';
            cartItem.style.transform = '';
        }
        showToast('Greška. Pokušajte ponovo.', 'error');
    });
}


function updateLocalSubtotal(productId, qty) {
    const prices = typeof cartItemPrices !== 'undefined' ? cartItemPrices : {};
    const price  = prices[productId] || 0;

    if (!price) return;

    const subtotalEl = document.getElementById('subtotal-' + productId);
    if (subtotalEl && qty > 0) {
        subtotalEl.textContent = formatPriceJS(price * qty);
    }
}


function updateCartTotal(data) {
    if (!data || typeof data !== 'object') return;

    const subtotalEl = document.getElementById('summarySubtotal');
    const totalEl    = document.getElementById('summaryTotal');
    const countEl    = document.getElementById('summaryCount');
    const deliveryEl = document.getElementById('summaryDelivery');
    const noteEl     = document.getElementById('cartDeliveryNote');

    if (subtotalEl && data.subtotal) {
        subtotalEl.textContent = data.subtotal;
    }

    if (totalEl && data.total) {
        totalEl.textContent = data.total;
    }

    if (countEl && typeof data.count !== 'undefined') {
        countEl.textContent = data.count;
    }

    if (deliveryEl && data.delivery_label) {
        deliveryEl.textContent = data.delivery_label;

        if (data.is_free_delivery) {
            deliveryEl.classList.add('text-success');
        } else {
            deliveryEl.classList.remove('text-success');
        }
    }

    if (noteEl && data.free_delivery_from && data.delivery_base_label) {
        if (data.is_free_delivery) {
            noteEl.textContent = `Vaša narudžba ispunjava uslov za besplatnu dostavu (iznad ${data.free_delivery_from}).`;
            noteEl.classList.remove('text-muted');
            noteEl.classList.add('text-success');
        } else {
            noteEl.textContent = `Za narudžbe ispod ${data.free_delivery_from} naplaćuje se dostava ${data.delivery_base_label}.`;
            noteEl.classList.remove('text-success');
            noteEl.classList.add('text-muted');
        }
    }
}


function checkEmptyCart() {
    const items = document.querySelectorAll('.cart-item');
    if (items.length === 0) {
        setTimeout(() => window.location.reload(), 400);
    }
}


(function initCartPage() {
    const cartItems = document.getElementById('cartItems');
    if (!cartItems) return;

    document.querySelectorAll('.qty-value').forEach(el => {
        el.setAttribute('contenteditable', 'true');
        el.setAttribute('inputmode', 'numeric');

        el.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.blur();
            }
            if (!['Backspace','Delete','ArrowLeft','ArrowRight','Tab'].includes(e.key) && !/^\d$/.test(e.key)) {
                e.preventDefault();
            }
        });

        el.addEventListener('blur', function() {
            const cartItem = this.closest('[data-product-id]');
            if (!cartItem) return;
            const productId = parseInt(cartItem.dataset.productId);
            const newQty    = Math.max(0, parseInt(this.textContent) || 0);
            this.textContent = newQty;
            if (newQty !== parseInt(this.dataset.prev || newQty)) {
                changeQty(productId, newQty - parseInt(this.dataset.prev || newQty));
            }
        });

        el.addEventListener('focus', function() {
            this.dataset.prev = this.textContent;
        });
    });
})();
