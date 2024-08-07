(function() {
    const userId = getCookie('user_id') || '';
    const pageUrl = window.location.href;
    let startTime = new Date();
    let focusStartTime = new Date();
    let totalFocusTime = 0;
    let visibilityChangeTime = new Date();
    const trackingData = [];

    function getCookie(name) {
        let value = "; " + document.cookie;
        let parts = value.split("; " + name + "=");
        if (parts.length === 2) return parts.pop().split(";").shift();
    }

    function sendPageViewEvent(data) {
        if (navigator.sendBeacon) {
            navigator.sendBeacon('https://joshcreative.co/api/webhook/event', JSON.stringify(data));
        } else {
            fetch('https://joshcreative.co/api/webhook/event', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
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
        } else if (document.visibilityState === 'visible') {
            focusStartTime = new Date();
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
    }

    window.addEventListener('beforeunload', sendDataBeforeUnload);
    document.addEventListener('visibilitychange', handleVisibilityChange);

    window.addEventListener('load', () => {
        startTime = new Date();
        focusStartTime = new Date();

        // Retrieve any stored data from localStorage and send it
        const storedData = localStorage.getItem('pageViewTrackingData');
        if (storedData) {
            sendPageViewEvent(JSON.parse(storedData));
            localStorage.removeItem('pageViewTrackingData');
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
    }, 60000); // Send data every minute
})();
