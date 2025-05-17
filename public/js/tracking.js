(function () {
    console.log('📊 Shopify Funnel Tracker Loaded');

    const API_ENDPOINT = 'https://joshcreative.co/api/webhook/event';

    const botUserAgents = [
        /Googlebot/i, /Bingbot/i, /Slurp/i, /DuckDuckBot/i, /Baiduspider/i,
        /YandexBot/i, /Sogou/i, /Exabot/i, /facebot/i, /ia_archiver/i
    ];

    if (botUserAgents.some(agent => agent.test(navigator.userAgent))) return;

    // ---------- Utility Functions ----------
    function getCookie(name) {
        const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? match[2] : null;
    }

    function setCookie(name, value, days = 365) {
        const expires = new Date(Date.now() + days * 864e5).toUTCString();
        document.cookie = `${name}=${value}; path=/; expires=${expires}`;
    }

    function getOrCreateAnonId() {
        let id = getCookie('_anon_id');
        if (!id) {
            id = crypto.randomUUID();
            setCookie('_anon_id', id);
        }
        return id;
    }

    function getUTMParams() {
        const params = new URLSearchParams(window.location.search);
        return {
            utm_source: params.get('utm_source'),
            utm_medium: params.get('utm_medium'),
            utm_campaign: params.get('utm_campaign'),
            utm_term: params.get('utm_term'),
            utm_content: params.get('utm_content')
        };
    }

    function detectPageType() {
        const path = window.location.pathname;
        if (path.includes('/products/')) return 'product';
        if (path.includes('/cart')) return 'cart';
        if (path.includes('/checkout')) return 'checkout';
        if (path === '/') return 'home';
        return 'other';
    }

    function sendToBackend(event) {
        const jsonData = JSON.stringify(event);
        if (navigator.sendBeacon) {
            navigator.sendBeacon(API_ENDPOINT, jsonData);
        }
    }

    function trackEvent(eventType, element = null, funnelStage = null) {
        const now = new Date();
        const event = {
            anon_id: anonId,
            event_type: eventType,
            funnel_stage: funnelStage,
            element: element || null,
            page_url: pageUrl,
            page_type: pageType,
            referrer,
            timestamp: now.toISOString(),
            focus_time: totalFocusTime,
            utm: utmData,
            screen: {
                width: window.innerWidth,
                height: window.innerHeight
            },
            user_agent: navigator.userAgent
        };
        trackingData.push(event);
        localStorage.setItem('pageTrackingData', JSON.stringify(trackingData));
        rateLimitedSend(event);
    }

    function rateLimitedSend(data) {
        if (requestCount < maxRequestsPerMinute) {
            requestCount++;
            sendToBackend(data);
        }
    }

    function syncAnonIdToCart() {
        fetch('/cart/update.js', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ attributes: { _anon_id: anonId } })
        });
    }

    function sendStoredData() {
        const stored = localStorage.getItem('pageTrackingData');
        if (stored) {
            JSON.parse(stored).forEach(rateLimitedSend);
            localStorage.removeItem('pageTrackingData');
        }
    }
    function extractButtonLabel(target) {
        const aria = target.getAttribute('aria-label');
        if (aria) return aria.toLowerCase().trim();
    
        const span = target.querySelector('span');
        if (span?.innerText) return span.innerText.toLowerCase().trim();
    
        if (target.innerText) return target.innerText.toLowerCase().trim();
        if (target.value) return target.value.toLowerCase().trim();
    
        return '';
    }
    
    function registerEventListeners() {
        // ✅ Funnel Tracking by URL
        const url = window.location.href;
        const pathname = window.location.pathname;
    
        window.addEventListener('load', () => {
            startTime = new Date();
            focusStartTime = new Date();
            sendStoredData();
    
            // Always track page view
            trackEvent('page_view', null, 'page_view');
    
            // 🟢 Funnel logic by URL path
            if (pathname.includes('/products/')) {
                const productTitle = document.querySelector('h1')?.innerText || '';
                const productHandle = Shopify?.product?.handle || pathname.split('/').pop();
                const productId = Shopify?.product?.id || null;
                const price = Shopify?.product?.variants?.[0]?.price / 100 || null;
    
                trackEvent('view_product', {
                    product_title: productTitle,
                    product_handle: productHandle,
                    product_id: productId,
                    price
                }, 'view_product');
            }
    
            if (pathname.includes('/cart')) {
                trackEvent('add_to_cart', { page: 'cart' }, 'add_to_cart');
            }
    
            if (pathname.includes('/checkout')) {
                trackEvent('start_checkout', { page: 'checkout' }, 'start_checkout');
            }
    
            if (pathname.includes('/thank_you')) {
                trackEvent('purchase_complete', { page: 'thank_you' }, 'purchase_complete');
            }
        });
    
        // ✅ Visibility & unload tracking
        window.addEventListener('beforeunload', () => {
            const destination = document.activeElement?.href || '';
        
            // Only track if user is leaving to external domain or closing tab
            const isExternal = destination && !destination.includes(location.hostname);
        
            if (!destination || isExternal) {
                const now = new Date();
                totalFocusTime += (now - focusStartTime) / 1000;
                trackEvent('site_exit', null, 'exit_site');
            }
        });
        
    
   
    
        // ✅ Auto ping every 60s for session tracking
        setInterval(() => {
            const now = new Date();
            totalFocusTime += (now - visibilityChangeTime) / 1000;
            visibilityChangeTime = now;
            // trackEvent('active_ping', null, 'page_view');
            trackingData.length = 0;
        }, 60000);
    }
    

    // ---------- Init State ----------
    const anonId = getOrCreateAnonId();
    const pageUrl = window.location.href;
    const utmData = getUTMParams();
    const pageType = detectPageType();
    const referrer = document.referrer;
    let startTime = new Date();
    let focusStartTime = new Date();
    let totalFocusTime = 0;
    let visibilityChangeTime = new Date();
    let requestCount = 0;
    const maxRequestsPerMinute = 5;
    const trackingData = [];

    if (window.location.href.includes('/thank_you')) {
        trackEvent('purchase_complete', {}, 'purchase_complete');
    }

    syncAnonIdToCart();
    registerEventListeners();
})();
