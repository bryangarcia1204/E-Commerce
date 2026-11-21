// Wishlist — botones data-wishlist en tarjetas/detalle (HTML cacheado).
//
// Por qué por JS: las tarjetas y el detalle se cachean como HTML
// (CacheService). El estado "¿es favorito de ESTE usuario?" depende del
// usuario y NO se puede hornear en el HTML cacheado (cada usuario tendría
// el suyo). Así que el botón es neutro y este script:
//   1) lee window.wishlistIds (inyectado por layouts.app para auth),
//   2) rellena los corazones (.is-active),
//   3) alterna por fetch (POST a data-wishlist-url) con el token CSRF del
//      <meta name="csrf-token">,
//   4) actualiza el badge [data-wishlist-count].
// Invitados: redirige a login (window.loginUrl).

function initWishlist() {
    const ids = new Set(window.wishlistIds || []);
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    const setButtonState = (btn, active) => {
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        btn.setAttribute('aria-label', active ? 'Quitar de favoritos' : 'Añadir a favoritos');
    };

    const setCount = (n) => {
        document.querySelectorAll('[data-wishlist-count]').forEach((el) => {
            el.textContent = n;
        });
    };

    const refreshAll = () => {
        document.querySelectorAll('[data-wishlist]').forEach((btn) => {
            const id = parseInt(btn.dataset.wishlistId, 10);
            setButtonState(btn, ids.has(id));
        });
        setCount(ids.size);
    };

    const onClick = async (btn, e) => {
        e.preventDefault();
        e.stopPropagation();
        if (!window.isUserAuth) {
            if (window.loginUrl) window.location.href = window.loginUrl;
            return;
        }
        const url = btn.dataset.wishlistUrl;
        if (!url || !token) return;
        btn.disabled = true;
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            // Sesión caducada / no auth → a login.
            if (res.status === 401 || res.status === 419) {
                if (window.loginUrl) window.location.href = window.loginUrl;
                return;
            }
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            if (data.inWishlist) ids.add(data.productId); else ids.delete(data.productId);
            // Refresca todos los botones del mismo producto (aparece varias
            // veces: tarjeta en grid + posible corazón en detalle).
            document
                .querySelectorAll(`[data-wishlist-id="${data.productId}"]`)
                .forEach((b) => setButtonState(b, data.inWishlist));
            setCount(data.count);
        } catch (err) {
            // fallo de red: dejamos el botón en su estado previo
        } finally {
            btn.disabled = false;
        }
    };

    document.querySelectorAll('[data-wishlist]').forEach((btn) => {
        btn.addEventListener('click', (e) => onClick(btn, e));
    });

    refreshAll();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initWishlist);
} else {
    initWishlist();
}
