(function () {
    console.log('📊 Shopify Funnel Tracker Loaded');

    const API_ENDPOINT = 'https://joshcreative.co/api/webhook/event';

    const botUserAgents = [
        /Googlebot/i, /Bingbot/i, /Slurp/i, /DuckDuckBot/i, /Baiduspider/i,
        /YandexBot/i, /Sogou/i, /Exabot/i, /facebot/i, /ia_archiver/i
    ];
    const isBot = botUserAgents.some(botAgent => botAgent.test(navigator.userAgent));
    if (isBot) return;

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
            utm_content: params.get('utm_content'),
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
    

    // ✅ Immediately sync anon_id to cart attributes
    fetch('/cart/update.js', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            attributes: {
                _anon_id: anonId
            }
        })
    });
    setInterval(() => requestCount = 0, 60000);

    function rateLimitedSend(data) {
        if (requestCount < maxRequestsPerMinute) {
            requestCount++;
            sendToBackend(data);
        } else {
            console.log('⛔ Rate limit exceeded, skipping');
        }
    }

    function sendToBackend(event) {
        const jsonData = JSON.stringify(event);
        if (navigator.sendBeacon) {
            navigator.sendBeacon(API_ENDPOINT, jsonData);
        } else {
            fetch(API_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: jsonData
            }).catch(console.error);
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

    function handleVisibilityChange() {
        const now = new Date();
        if (document.visibilityState === 'hidden') {
            totalFocusTime += (now - focusStartTime) / 1000;
            trackEvent('page_hidden', null, 'page_view');
        } else if (document.visibilityState === 'visible') {
            focusStartTime = new Date();
        }
        visibilityChangeTime = now;
    }

    function sendBeforeUnload() {
        const now = new Date();
        totalFocusTime += (now - focusStartTime) / 1000;
        trackEvent('page_exit', null, 'page_view');
    }

    function sendStoredData() {
        const stored = localStorage.getItem('pageTrackingData');
        if (stored) {
            const events = JSON.parse(stored);
            events.forEach(rateLimitedSend);
            localStorage.removeItem('pageTrackingData');
        }
    }

    window.addEventListener('load', () => {
        startTime = new Date();
        focusStartTime = new Date();
        sendStoredData();
        trackEvent('page_view', null, 'page_view');

        // Funnel: View Product
        if (window.location.pathname.includes('/products/')) {
            const productTitle = document.querySelector('h1')?.innerText || '';
            const productHandle = Shopify?.product?.handle || window.location.pathname.split('/').pop();
            const productId = Shopify?.product?.id || null;
            const price = Shopify?.product?.variants?.[0]?.price / 100 || null;

            const productData = {
                product_title: productTitle,
                product_handle: productHandle,
                product_id: productId,
                price: price
            };
            trackEvent('view_product', productData, 'view_product');
        }
    });

    window.addEventListener('beforeunload', sendBeforeUnload);
    document.addEventListener('visibilitychange', handleVisibilityChange);

    document.addEventListener('click', function (event) {
        const el = event.target.closest('form[action*="/cart/add"], button[data-add-to-cart], a[href*="/checkout"], button[name="checkout"]');

        if (el) {
            // Funnel: Add to Cart
            if (el.matches('form[action*="/cart/add"], button[data-add-to-cart]')) {
                const productTitle = document.querySelector('h1')?.innerText || '';
                const quantity = el.querySelector('input[name="quantity"]')?.value || 1;
                const cartData = {
                    product_title: productTitle,
                    quantity: Number(quantity)
                };
                trackEvent('add_to_cart', cartData, 'add_to_cart');
            }

            // Funnel: Start Checkout
            if (el.matches('a[href*="/checkout"], button[name="checkout"]')) {
                trackEvent('start_checkout', null, 'start_checkout');
            }
        }

        // Generic click tracking
        const target = event.target.closest('button, a, [data-track]');
        if (target) {
            trackEvent('click', target.outerHTML, 'click');
        }
    });

    document.addEventListener('submit', function (event) {
        const el = event.target;
        if (el.tagName === 'FORM') {
            trackEvent('form_submit', el.action, 'form_submit');
        }
    });

    setInterval(() => {
        const now = new Date();
        totalFocusTime += (now - visibilityChangeTime) / 1000;
        visibilityChangeTime = now;
        trackEvent('active_ping', null, 'page_view');
        trackingData.length = 0;
    }, 60000);

})();
