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
    const trackingData = [];

    let requestCount = 0;
    const maxRequestsPerMinute = 5;

 
    // Rate limiting function
    function rateLimitedSend(data) {
        if (requestCount < maxRequestsPerMinute) {
            requestCount++;
            sendPageViewEvent(data);
        } else {
            console.log('Rate limit exceeded, skipping request.');
        }
    }

    // Reset request counter every minute
    setInterval(() => {
        requestCount = 0;
    }, 60000);

    // Function to send page view events
    function sendPageViewEvent(data) {
     

        const jsonData = JSON.stringify(data);
        console.log('Sending event data:', jsonData);

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
            .then(data => console.log('Event recorded:', data))
            .catch(error => console.error('Error:', error));
        }
    }

    function handleVisibilityChange() {
        const currentTime = new Date();
        if (document.visibilityState === 'hidden') {
            totalFocusTime += (currentTime - focusStartTime) / 1000; 
            const event = {
                user_id: userId,
                page_url: pageUrl,
                start_time: startTime.toISOString(),
                end_time: currentTime.toISOString(),
                focus_time: totalFocusTime,
                event_type: 'page_view',
                element: pageUrl
            };
            trackingData.push(event);
            localStorage.setItem('pageViewTrackingData', JSON.stringify(trackingData));
            console.log('Page hidden, focus time recorded:', totalFocusTime); 
        } else if (document.visibilityState === 'visible') {
            focusStartTime = new Date();
            console.log('Page visible');
        }
        visibilityChangeTime = currentTime;
    }

    function sendDataBeforeUnload() {
        const endTime = new Date();
        const focusEndTime = new Date();
        totalFocusTime += (focusEndTime - focusStartTime) / 1000; 

        const event = {
            user_id: userId,
            page_url: pageUrl,
            start_time: startTime.toISOString(),
            end_time: endTime.toISOString(),
            focus_time: totalFocusTime,
            event_type: 'page_view',
            element: pageUrl
        };
        trackingData.push(event);

        localStorage.setItem('pageViewTrackingData', JSON.stringify(trackingData));
        rateLimitedSend(event);
        console.log('Before unload, data sent:', event); 
    }

    function trackUserInteraction(eventType, element) {
        const event = {
            user_id: userId,
            page_url: pageUrl,
            event_type: eventType,
            element: element,
            timestamp: new Date().toISOString()
        };
        trackingData.push(event);
        localStorage.setItem('pageViewTrackingData', JSON.stringify(trackingData));
        console.log('User interaction tracked:', event); 
    }

    function sendStoredData() {
        const storedData = localStorage.getItem('pageViewTrackingData');
        if (storedData) {
            const events = JSON.parse(storedData);
            events.forEach(event => rateLimitedSend(event));
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

    document.addEventListener('scroll', function() {
        const scrollDepth = Math.round((window.scrollY + window.innerHeight) / document.documentElement.scrollHeight * 100);
        trackUserInteraction('scroll', scrollDepth);
    });

    
    window.addEventListener('load', () => {
        startTime = new Date();
        focusStartTime = new Date();
        console.log('Page loaded');
        sendStoredData();
    });

    setInterval(() => {
        const currentTime = new Date();
        totalFocusTime += (currentTime - visibilityChangeTime) / 1000; 
        visibilityChangeTime = currentTime;

        const event = {
            user_id: userId,
            page_url: pageUrl,
            start_time: startTime.toISOString(),
            end_time: currentTime.toISOString(),
            focus_time: totalFocusTime,
            event_type: 'page_view',
            element: pageUrl
        };
        trackingData.push(event);

        localStorage.setItem('pageViewTrackingData', JSON.stringify(trackingData));
        rateLimitedSend(event);
        trackingData.length = 0; 
        console.log('Periodic data sent:', event); 
    }, 60000);
})();
