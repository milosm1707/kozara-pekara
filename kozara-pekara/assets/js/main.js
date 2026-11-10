
'use strict';

(function initNavbar() {
    const toggle = document.getElementById('navToggle');
    const links  = document.getElementById('navLinks');
    const navbar = document.getElementById('navbar');

    if (!toggle || !links) return;

    toggle.addEventListener('click', () => {
        const isOpen = links.classList.toggle('open');
        toggle.classList.toggle('open', isOpen);
        toggle.setAttribute('aria-expanded', isOpen);
    });

    document.addEventListener('click', e => {
        if (!navbar.contains(e.target)) {
            links.classList.remove('open');
            toggle.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
        }
    });

    links.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            links.classList.remove('open');
            toggle.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
        });
    });

    window.addEventListener('scroll', () => {
        navbar.classList.toggle('scrolled', window.scrollY > 10);
    }, { passive: true });
})();


function showToast(message, type = '', duration = 3000) {
    let toast = document.getElementById('toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'toast';
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        document.body.appendChild(toast);
    }

    toast.textContent = message;
    toast.className   = 'toast';
    if (type) toast.classList.add('toast-' + type);

    requestAnimationFrame(() => {
        toast.classList.add('show');
    });

    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => {
        toast.classList.remove('show');
    }, duration);
}


function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;

    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';

    const btn = input.parentElement.querySelector('.password-toggle');
    if (btn) {
        btn.setAttribute('aria-label', isText ? 'Prikaži lozinku' : 'Sakrij lozinku');
        btn.style.opacity = isText ? '0.5' : '1';
    }
}


(function initFlash() {
    document.querySelectorAll('.flash').forEach(flash => {
        setTimeout(() => {
            flash.style.transition = 'opacity 0.4s ease';
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 400);
        }, 5000);
    });
})();


(function initFormValidation() {
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.7';
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '';
                }, 5000);
            }
        });
    });
})();


(function initResponsiveAdminTables() {
    function labelTable(table) {
        const headerCells = table.querySelectorAll('thead th');
        if (!headerCells.length) return;

        const labels = Array.from(headerCells).map(th =>
            (th.textContent || '').trim() || 'Polje'
        );

        table.querySelectorAll('tbody tr').forEach(row => {
            row.querySelectorAll('td').forEach((cell, index) => {
                const label = labels[index] !== undefined ? labels[index] : '';
                cell.setAttribute('data-label', label);
            });
        });
    }

    document.querySelectorAll('.admin-table').forEach(labelTable);

    document.querySelectorAll('.admin-table').forEach(table => {
        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        const observer = new MutationObserver(() => labelTable(table));
        observer.observe(tbody, { childList: true, subtree: false });
    });
})();


(function initOrderTableCompactMarker() {
    function markOrderTable(table) {
        const headers = Array.from(table.querySelectorAll('thead th'))
            .map(th => (th.textContent || '').trim().toLowerCase());

        if (!headers.length) return;

        const hasOrderNo = headers.some(h => h.includes('broj'));
        const hasCustomer = headers.some(h => h.includes('korisnik'));
        const hasAction = headers.some(h => h.includes('akcija'));

        if (hasOrderNo && hasCustomer && hasAction) {
            table.classList.add('orders-mobile-compact');
        }
    }

    document.querySelectorAll('.table-responsive .admin-table').forEach(markOrderTable);
})();


document.querySelectorAll('a[href^="#"]').forEach(link => {
    link.addEventListener('click', e => {
        const href = link.getAttribute('href');
        if (href === '#') return;
        const target = document.querySelector(href);
        if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});


function formatPriceJS(amount) {
    return new Intl.NumberFormat('bs-BA', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount) + ' KM';
}


(function initWorkingHours() {
    function showClosedBakeryPopup(hoursLabel) {
        const text = `Pekara je trenutno zatvorena. Radno vreme za naručivanje je ${hoursLabel}.`;
        showToast(text, 'error', 5000);

        let modal = document.getElementById('workingHoursModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'workingHoursModal';
            modal.className = 'working-hours-modal';
            modal.innerHTML = `
                <div class="working-hours-modal-backdrop" data-close-modal="1"></div>
                <div class="working-hours-modal-card" role="dialog" aria-modal="true" aria-labelledby="workingHoursTitle">
                    <h3 id="workingHoursTitle">Pekara je zatvorena</h3>
                    <p id="workingHoursText"></p>
                    <button type="button" class="btn btn-primary" id="workingHoursCloseBtn">U redu</button>
                </div>
            `;
            document.body.appendChild(modal);

            modal.addEventListener('click', function(e) {
                if (e.target && (e.target.dataset.closeModal === '1' || e.target.id === 'workingHoursCloseBtn')) {
                    modal.classList.remove('show');
                }
            });
        }

        const textTarget = document.getElementById('workingHoursText');
        if (textTarget) textTarget.textContent = text;
        modal.classList.add('show');
    }

    const checkoutLink = document.getElementById('checkoutBtn');
    if (checkoutLink) {
        const isOpen     = checkoutLink.dataset.orderingOpen === '1';
        const hoursLabel = checkoutLink.dataset.orderingHours || '08:00 — 22:00';

        if (!isOpen) {
            checkoutLink.classList.add('btn-disabled');
            checkoutLink.setAttribute('aria-disabled', 'true');
            checkoutLink.addEventListener('click', e => {
                e.preventDefault();
                showClosedBakeryPopup(hoursLabel);
            });
        }
    }

    const checkoutForm = document.querySelector('.checkout-form[data-ordering-open]');
    if (checkoutForm) {
        const isOpen     = checkoutForm.dataset.orderingOpen === '1';
        const hoursLabel = checkoutForm.dataset.orderingHours || '08:00 — 22:00';

        if (!isOpen) {
            checkoutForm.addEventListener('submit', e => {
                e.preventDefault();
                showClosedBakeryPopup(hoursLabel);
            });
        }
    }
})();


(function initProductModal() {
    // Klik na proizvod (na početnoj / u meniju) je ranije vodio na
    // href="#" u slici, što je samo skrolalo stranicu na vrh umjesto
    // da prikaže detalje proizvoda. Sada otvara modal sa detaljima;
    // dugme "Dodaj u korpu" (ili "Prijavi se" / "Samo pregled" za
    // pekara) se klonira direktno iz kartice, pa se stanje uvijek
    // poklapa sa onim što korisnik već vidi u gridu.

    const grid  = document.getElementById('productsGrid');
    const modal = document.getElementById('productModal');
    if (!grid || !modal) return;

    const imageEl  = document.getElementById('productModalImage');
    const badgeEl  = document.getElementById('productModalBadge');
    const titleEl  = document.getElementById('productModalTitle');
    const descEl   = document.getElementById('productModalDesc');
    const footerEl = document.getElementById('productModalFooter');

    let isOpen = false;

    function isActionTarget(el) {
        return !!el.closest('.btn-add-cart, .btn-login-prompt');
    }

    function openModalFromCard(card) {
        const img    = card.querySelector('.product-image');
        const badge  = card.querySelector('.product-badge');
        const name   = card.querySelector('.product-name');
        const desc   = card.querySelector('.product-desc');
        const footer = card.querySelector('.product-footer');

        if (imageEl && img) {
            imageEl.src = img.src;
            imageEl.alt = img.alt || '';
        }

        if (badgeEl) {
            const text = badge ? badge.textContent.trim() : '';
            badgeEl.textContent = text;
            badgeEl.hidden = !text;
        }

        if (titleEl) titleEl.textContent = name ? name.textContent.trim() : '';

        if (descEl) {
            const text = desc ? desc.textContent.trim() : '';
            descEl.textContent = text;
            descEl.hidden = !text;
        }

        if (footerEl) {
            footerEl.innerHTML = '';
            if (footer) footerEl.appendChild(footer.cloneNode(true));
        }

        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        isOpen = true;

        // Gurni novo stanje istorije: dugme "nazad" na mobilnom tako
        // zatvara samo modal, a ne izlazi iz cijele aplikacije.
        history.pushState({ productModal: true }, '', location.pathname + location.search + location.hash);
    }

    function closeModal(viaPopstate) {
        if (!isOpen) return;
        isOpen = false;

        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');

        // Ako je zatvoreno klikom na X / pozadinu / Escape (a ne
        // dugmetom "nazad"), uklanjamo state koji smo dodali da
        // sledeći klik na "nazad" ne bude "prazan".
        if (!viaPopstate && history.state && history.state.productModal) {
            history.back();
        }
    }

    grid.addEventListener('click', function (e) {
        if (isActionTarget(e.target)) return;
        const card = e.target.closest('.product-card');
        if (!card) return;
        openModalFromCard(card);
    });

    grid.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        if (isActionTarget(e.target)) return;
        const card = e.target.closest('.product-card');
        if (!card) return;
        e.preventDefault();
        openModalFromCard(card);
    });

    modal.addEventListener('click', function (e) {
        if (e.target.dataset.closeProductModal === '1' || e.target.closest('#productModalClose')) {
            closeModal(false);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen) closeModal(false);
    });

    window.addEventListener('popstate', function () {
        if (isOpen) closeModal(true);
    });
})();
