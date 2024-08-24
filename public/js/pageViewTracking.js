(function() {
    console.log('Tracking script loaded');

    const botUserAgents = [
        /Googlebot/i,
        /Bingbot/i,
        /Slurp/i,
        /DuckDuckBot/i,
        /Baiduspider/i,
        /YandexBot/i,
        /Sogou/i,
        /Exabot/i,
        /facebot/i,
        /ia_archiver/i
    ];

    const isBot = botUserAgents.some(botAgent => botAgent.test(navigator.userAgent));
    if (isBot) {
        return;
    }

    function getCookie(name) {
        let value = "; " + document.cookie;
        let parts = value.split("; " + name + "=");
        if (parts.length === 2) return parts.pop().split(";").shift();
    }

    const userId = getCookie('user_id') || '';
    const pageUrl = window.location.href;
    let startTime = new Date();
    let focusStartTime = new Date();
    let totalFocusTime = 0;
    let visibilityChangeTime = new Date();
    const trackingData = new Set(); // Using a Set to avoid duplicate events
    let lastInteraction = null; // To keep track of the last event to avoid duplicates

    let requestCount = 0;
    const maxRequestsPerMinute = 5;

    function rateLimitedSend(data) {
        if (requestCount < maxRequestsPerMinute) {
            requestCount++;
            sendBatchEvents([data]);  // Send events in batch, even if only one
        } else {
            console.log('Rate limit exceeded, skipping request.');
        }
    }

    setInterval(() => {
        requestCount = 0;
    }, 60000);

    function sendBatchEvents(events) {
        if (events.length === 0) return;

        const jsonData = JSON.stringify(events);
        console.log('Sending batch event data:', jsonData);

        if (navigator.sendBeacon) {
            navigator.sendBeacon('https://joshcreative.co/api/webhook/event', jsonData);
        } else {
            fetch('https://joshcreative.co/api/webhook/event', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: jsonData,
            })
            .then(response => response.json())
            .then(data => console.log('Batch event recorded:', data))
            .catch(error => console.error('Error:', error));
        }
    }

    function handleVisibilityChange() {
        const currentTime = new Date();
        if (document.visibilityState === 'hidden') {
            totalFocusTime += (currentTime - focusStartTime) / 1000; 
            const event = createEvent('page_view');
            addEventToTracking(event);
            console.log('Page hidden, focus time recorded:', totalFocusTime); 
        } else if (document.visibilityState === 'visible') {
            focusStartTime = new Date();
            console.log('Page visible');
        }
        visibilityChangeTime = currentTime;
    }

    function createEvent(eventType, element = pageUrl) {
        const currentTime = new Date();
        return {
            user_id: userId,
            page_url: pageUrl,
            start_time: startTime.toISOString(),
            end_time: currentTime.toISOString(),
            focus_time: totalFocusTime,
            event_type: eventType,
            element: element,
            timestamp: currentTime.toISOString()
        };
    }

    function sendDataBeforeUnload() {
        const event = createEvent('page_view');
        addEventToTracking(event);
        rateLimitedSend([...trackingData]);
        console.log('Before unload, data sent:', event); 
    }

    function addEventToTracking(event) {
        const eventKey = JSON.stringify(event);
        if (!trackingData.has(eventKey)) {
            trackingData.add(eventKey);
            localStorage.setItem('pageViewTrackingData', JSON.stringify([...trackingData]));
            console.log('User interaction tracked:', event);
        } else {
            console.log('Duplicate event detected, not adding:', event);
        }
    }

    function trackUserInteraction(eventType, element) {
        const event = createEvent(eventType, element);
        addEventToTracking(event);
    }

    function sendStoredData() {
        const storedData = localStorage.getItem('pageViewTrackingData');
        if (storedData) {
            const events = JSON.parse(storedData);
            sendBatchEvents(events);
            localStorage.removeItem('pageViewTrackingData');
            console.log('Stored data sent:', events); 
        }
    }

    window.addEventListener('beforeunload', sendDataBeforeUnload);
    document.addEventListener('visibilitychange', handleVisibilityChange);

    document.addEventListener('click', function(event) {
        const target = event.target;
        if (target.tagName === 'BUTTON' || target.tagName === 'A' || target.getAttribute('data-track')) {
            trackUserInteraction('click', target.outerHTML);
        }
    });

    document.addEventListener('submit', function(event) {
        const target = event.target;
        if (target.tagName === 'FORM' || target.getAttribute('data-track')) {
            trackUserInteraction('form_submit', target.action);
        }
    });

    document.addEventListener('focus', function(event) {
        const target = event.target;
        if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT') {
            trackUserInteraction('focus', {
                element: target.outerHTML,
                id: target.id || null,
                name: target.name || null,
                type: target.type || null,
            });
        }
    }, true);

    document.addEventListener('change', function(event) {
        const target = event.target;
        if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT') {
            trackUserInteraction('change', {
                element: target.outerHTML,
                id: target.id || null,
                name: target.name || null,
                value: target.value,
            });
        }
    });

    document.addEventListener('scroll', debounce(function() {
        const scrollDepth = Math.round((window.scrollY + window.innerHeight) / document.documentElement.scrollHeight * 100);
        trackUserInteraction('scroll', scrollDepth);
    }, 200));

    window.addEventListener('load', () => {
        startTime = new Date();
        focusStartTime = new Date();
        console.log('Page loaded');
        sendStoredData();
    });

    setInterval(() => {
        const event = createEvent('page_view');
        addEventToTracking(event);
        if (trackingData.size > 0) {
            rateLimitedSend([...trackingData]);
            trackingData.clear(); 
        }
        console.log('Periodic data sent:', event); 
    }, 60000);

    // Debounce function to limit the rate at which a function can fire
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            const context = this;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    }

})();
