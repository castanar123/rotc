// ROTC Management System - Service Worker for Caching
// Version 2.0 - Cache Cleared

const CACHE_NAME = 'rotc-cms-v2-cleared';
const CACHE_VERSION = '2.0.0';

// Assets to cache immediately
const STATIC_ASSETS = [
    '/',
    '/index.php',
    '/css/landing.css',
    '/css/gallery-carousel.css',
    '/css/tactical-theme.css',
    '/css/landing-styles.css',
    '/js/landing.js',
    '/js/gallery-carousel.js',
    '/images/logo.png',
    '/images/hero-bg.jpg'
];

// Assets to cache on first request
const DYNAMIC_ASSETS = [
    '/login.php',
    '/register.php',
    '/QR/dashboard.php'
];

// Install event - clear all caches first, then cache static assets
self.addEventListener('install', event => {
    console.log('Service Worker: Installing and clearing all existing caches...');
    
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        console.log('Service Worker: Deleting existing cache:', cacheName);
                        return caches.delete(cacheName);
                    })
                );
            })
            .then(() => {
                console.log('Service Worker: All existing caches cleared, now caching fresh assets');
                return caches.open(CACHE_NAME);
            })
            .then(cache => {
                console.log('Service Worker: Caching static assets');
                return cache.addAll(STATIC_ASSETS.map(url => {
                    // Handle potential 404s gracefully
                    return fetch(url).then(response => {
                        if (response.ok) {
                            return cache.put(url, response);
                        }
                        console.warn(`Failed to cache: ${url}`);
                    }).catch(err => {
                        console.warn(`Failed to fetch: ${url}`, err);
                    });
                }));
            })
            .then(() => {
                console.log('Service Worker: Installation complete');
                return self.skipWaiting();
            })
            .catch(err => {
                console.error('Service Worker: Installation failed', err);
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    console.log('Service Worker: Activating and clearing ALL caches...');
    
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                console.log('Service Worker: Found caches:', cacheNames);
                return Promise.all(
                    cacheNames.map(cacheName => {
                        console.log('Service Worker: Deleting cache:', cacheName);
                        return caches.delete(cacheName);
                    })
                );
            })
            .then(() => {
                console.log('Service Worker: All caches cleared, activation complete');
                return self.clients.claim();
            })
    );
});

// Fetch event - serve from cache or network
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }
    
    // Skip external requests
    if (url.origin !== location.origin) {
        return;
    }
    
    // Skip camera and media-related requests to prevent interference with QR scanner
    if (url.pathname.includes('/camera/') ||
        url.pathname.includes('/media/') ||
        url.pathname.includes('/stream/') ||
        url.pathname.includes('/getUserMedia') ||
        url.protocol === 'blob:' ||
        request.destination === 'video' ||
        request.destination === 'audio' ||
        request.headers.get('accept')?.includes('video/') ||
        request.headers.get('accept')?.includes('audio/')) {
        console.log('Service Worker: Skipping camera/media request:', request.url);
        return;
    }
    
    // Skip API requests and form submissions
    if (url.pathname.includes('/api/') || 
        url.pathname.includes('/ajax/') ||
        url.pathname.includes('/upload/') ||
        request.headers.get('content-type')?.includes('multipart/form-data')) {
        return;
    }
    
    // Skip QR scanner related requests to ensure real-time functionality
    if (url.pathname.includes('rifle_scanner') ||
        url.pathname.includes('qr_scan') ||
        url.pathname.includes('scanner') ||
        url.searchParams.has('scan') ||
        url.searchParams.has('qr')) {
        console.log('Service Worker: Skipping QR scanner request for real-time access:', request.url);
        return;
    }
    
    event.respondWith(
        cacheFirst(request)
    );
});

// Cache-first strategy with network fallback
async function cacheFirst(request) {
    try {
        // Check cache first
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            console.log('Service Worker: Serving from cache:', request.url);
            
            // Update cache in background for dynamic content
            if (isDynamicContent(request.url)) {
                updateCacheInBackground(request);
            }
            
            return cachedResponse;
        }
        
        // Fetch from network
        console.log('Service Worker: Fetching from network:', request.url);
        const networkResponse = await fetch(request);
        
        // Cache successful responses
        if (networkResponse.ok && shouldCache(request)) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
            console.log('Service Worker: Cached new resource:', request.url);
        }
        
        return networkResponse;
        
    } catch (error) {
        console.error('Service Worker: Fetch failed:', error);
        
        // Return offline fallback for HTML pages
        if (request.headers.get('accept')?.includes('text/html')) {
            return new Response(
                '<h1>Offline</h1><p>Please check your internet connection and try again.</p>',
                {
                    headers: { 'Content-Type': 'text/html' },
                    status: 503,
                    statusText: 'Service Unavailable'
                }
            );
        }
        
        throw error;
    }
}

// Update cache in background
async function updateCacheInBackground(request) {
    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(CACHE_NAME);
            await cache.put(request, networkResponse);
            console.log('Service Worker: Updated cache in background:', request.url);
        }
    } catch (error) {
        console.warn('Service Worker: Background update failed:', error);
    }
}

// Check if content is dynamic
function isDynamicContent(url) {
    return url.includes('.php') || 
           url.includes('/dashboard') ||
           url.includes('/profile') ||
           url.includes('/attendance');
}

// Check if request should be cached
function shouldCache(request) {
    const url = request.url;
    
    // Cache static assets
    if (url.includes('.css') || 
        url.includes('.js') || 
        url.includes('.png') || 
        url.includes('.jpg') || 
        url.includes('.jpeg') || 
        url.includes('.gif') || 
        url.includes('.svg') || 
        url.includes('.webp') ||
        url.includes('.ico')) {
        return true;
    }
    
    // Cache HTML pages
    if (request.headers.get('accept')?.includes('text/html')) {
        return true;
    }
    
    return false;
}

// Handle messages from main thread
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'CACHE_UPDATE') {
        // Force cache update - clear ALL caches
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    console.log('Service Worker: Clearing cache:', cacheName);
                    return caches.delete(cacheName);
                })
            );
        }).then(() => {
            console.log('Service Worker: All caches cleared for update');
        });
    }
    
    if (event.data && event.data.type === 'CLEAR_ALL_CACHE') {
        // Clear all caches immediately
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => caches.delete(cacheName))
            );
        }).then(() => {
            console.log('Service Worker: All caches cleared on demand');
            // Reload all clients
            self.clients.matchAll().then(clients => {
                clients.forEach(client => client.postMessage({type: 'CACHE_CLEARED'}));
            });
        });
    }
});