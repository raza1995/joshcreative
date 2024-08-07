(function() {
    console.log('Tracking script loaded'); // Log when the script is loaded

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

    console.log('Initial data:', { userId, pageUrl, startTime }); // Log initial data

    function sendPageViewEvent(data) {
        console.log('Sending event data:', data); // Log the data being sent
        if (navigator.sendBeacon) {
            navigator.sendBeacon('https://joshcreative.co/api/webhook/event', data);
        } else {
            fetch('https://joshcreative.co/api/webhook/event', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: data,
            })
            .then(response => response.json())
            .then(data => console.log('Event recorded:', data))
            .catch(error => console.error('Error:', error));
        }
    }

    function handleVisibilityChange() {
        const currentTime = new Date();
        if (document.visibilityState === 'hidden') {
            totalFocusTime += (currentTime - focusStartTime) / 1000; // Calculate focus time in seconds
            trackingData.push({
                user_id: userId,
                page_url: pageUrl,
                start_time: startTime.toISOString(),
                end_time: currentTime.toISOString(),
                focus_time: totalFocusTime
            });
            localStorage.setItem('pageViewTrackingData', JSON.stringify(trackingData));
            console.log('Page hidden, focus time recorded:', totalFocusTime); // Log when page visibility changes
        } else if (document.visibilityState === 'visible') {
            focusStartTime = new Date();
            console.log('Page visible'); // Log when page becomes visible
        }
        visibilityChangeTime = currentTime;
    }

    function sendDataBeforeUnload() {
        const endTime = new Date();
        const focusEndTime = new Date();
        totalFocusTime += (focusEndTime - focusStartTime) / 1000; // Calculate focus time in seconds

        trackingData.push({
            user_id: userId,
            page_url: pageUrl,
            start_time: startTime.toISOString(),
            end_time: endTime.toISOString(),
            focus_time: totalFocusTime
        });

        localStorage.setItem('pageViewTrackingData', JSON.stringify(trackingData));
        sendPageViewEvent(trackingData);
        console.log('Before unload, data sent:', trackingData); // Log when data is sent before unload
    }

    window.addEventListener('beforeunload', sendDataBeforeUnload);
    document.addEventListener('visibilitychange', handleVisibilityChange);

    window.addEventListener('load', () => {
        startTime = new Date();
        focusStartTime = new Date();
        console.log('Page loaded'); // Log when the page is loaded

        // Retrieve any stored data from localStorage and send it
        const storedData = localStorage.getItem('pageViewTrackingData');
        if (storedData) {
            sendPageViewEvent(storedData);
            localStorage.removeItem('pageViewTrackingData');
            console.log('Stored data sent:', storedData); // Log when stored data is sent
        }
    });

    // Optionally, send data periodically if the user stays on the page for a long time
    setInterval(() => {
        const currentTime = new Date();
        totalFocusTime += (currentTime - visibilityChangeTime) / 1000; // Update focus time
        visibilityChangeTime = currentTime;

        trackingData.push({
            user_id: userId,
            page_url: pageUrl,
            start_time: startTime.toISOString(),
            end_time: currentTime.toISOString(),
            focus_time: totalFocusTime
        });

        localStorage.setItem('pageViewTrackingData', JSON.stringify(trackingData));
        sendPageViewEvent(trackingData);
        trackingData.length = 0; // Clear tracking data after sending
        console.log('Periodic data sent:', trackingData); // Log periodic data sending
    }, 9000); // Send data every minute
})();
