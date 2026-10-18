/* Service Worker — Refrigeração CRM PWA
   A versão do cache é injetada pelo PHP (rota /rc-sw.js) */
/* global RC_CACHE_VERSION */

const CACHE_VERSION = (typeof RC_CACHE_VERSION !== 'undefined') ? RC_CACHE_VERSION : 'dev';
const CACHE_NAME    = 'rc-crm-' + CACHE_VERSION;
const OFFLINE_URL   = '/painel-do-tecnico/';

/* Apenas assets estáticos — NUNCA páginas dinâmicas (exigem login e podem
   conter dados do utilizador; não devem ser cacheadas) */
const PRECACHE = [
    '/wp-content/plugins/refrigeracao-crm/assets/rc-frontend.css',
    '/wp-content/plugins/refrigeracao-crm/assets/rc-area-cliente.css',
    '/wp-content/plugins/refrigeracao-crm/assets/rc-admin.css',
    '/wp-content/plugins/refrigeracao-crm/assets/icon-192.svg',
    '/wp-content/plugins/refrigeracao-crm/assets/icon-512.svg',
];

/* Instalação */
self.addEventListener('install', function (event) {
    event.waitUntil(
        Promise.all([
            caches.open(CACHE_NAME).then(function (cache) {
                return cache.addAll(PRECACHE).catch(function () {
                    /* Ignorar erros individuais */
                });
            }),
            self.skipWaiting(),
        ])
    );
});

/* Ativação — limpar caches antigas */
self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(
                keys.filter(function (key) {
                    return key !== CACHE_NAME;
                }).map(function (key) {
                    return caches.delete(key);
                })
            );
        }).then(function () {
            return self.clients.claim();
        })
    );
});

/* Estratégia: network-first com fallback para cache */
self.addEventListener('fetch', function (event) {
    const request = event.request;

    /* Ignorar pedidos que não sejam GET */
    if (request.method !== 'GET') return;

    /* Ignorar admin, AJAX e login */
    const url = new URL(request.url);
    if (
        url.pathname.includes('/wp-admin/') ||
        url.pathname.includes('/wp-login.php') ||
        url.pathname.includes('admin-ajax.php') ||
        url.search.includes('action=')
    ) {
        return;
    }

    event.respondWith(
        fetch(request)
            .then(function (response) {
                /* Guardar cópia na cache (apenas same-origin, GET, 200) */
                if (response && response.status === 200 && response.type === 'basic') {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then(function (cache) {
                        cache.put(request, clone);
                    });
                }
                return response;
            })
            .catch(function () {
                /* Sem internet — tentar cache */
                return caches.match(request).then(function (cached) {
                    if (cached) return cached;

                    /* Se for navegação, devolver a página principal do painel */
                    if (request.mode === 'navigate') {
                        return caches.match(OFFLINE_URL);
                    }
                });
            })
    );
});