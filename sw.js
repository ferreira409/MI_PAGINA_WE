// =========================================================
//  Service Worker: hace que la página funcione como app
//  - Guarda en caché CSS, JS, íconos y fuentes (carga rápida)
//  - Las páginas PHP siempre se piden a internet (datos al día)
//  - Sin conexión muestra offline.html
// =========================================================
const VERSION = "agendafacil-v1";
const ARCHIVOS_BASE = [
    "offline.html",
    "assets/estilo.css",
    "assets/diseno.css",
    "js/app.js",
    "assets/iconos/icono-192.png",
    "assets/iconos/icono-512.png",
];

self.addEventListener("install", evento => {
    evento.waitUntil(caches.open(VERSION).then(cache => cache.addAll(ARCHIVOS_BASE)));
    self.skipWaiting();
});

self.addEventListener("activate", evento => {
    evento.waitUntil(
        caches.keys().then(claves => Promise.all(claves.filter(c => c !== VERSION).map(c => caches.delete(c))))
    );
    self.clients.claim();
});

self.addEventListener("fetch", evento => {
    const pedido = evento.request;
    if (pedido.method !== "GET") return;                    // formularios: siempre a internet
    const url = new URL(pedido.url);
    if (url.pathname.includes("/api/")) return;              // la API nunca se guarda en caché

    // Páginas: primero internet; si no hay conexión, página offline
    if (pedido.mode === "navigate") {
        evento.respondWith(fetch(pedido).catch(() => caches.match("offline.html")));
        return;
    }

    // Archivos estáticos (css, js, fuentes, íconos, imágenes): caché primero
    const estatico = /\.(css|js|png|jpg|jpeg|webp|svg|woff2?)$/i.test(url.pathname)
                  || url.hostname.includes("cdnjs.cloudflare.com")
                  || url.hostname.includes("fonts.g");
    if (estatico) {
        evento.respondWith(
            caches.match(pedido).then(enCache => enCache || fetch(pedido).then(resp => {
                if (resp.ok || resp.type === "opaque") {
                    const copia = resp.clone();
                    caches.open(VERSION).then(cache => cache.put(pedido, copia));
                }
                return resp;
            }))
        );
    }
});
