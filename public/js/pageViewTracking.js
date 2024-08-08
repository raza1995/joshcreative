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
    let userIP = '';
    let locationData = {};

    console.log('Initial data:', { userId, pageUrl, startTime }); // Log initial data

    async function getUserIPAndLocation() {
        try {
            const response = await fetch('https://ipapi.co/json/'); // Using ipapi.co to get IP and location data
            const data = await response.json();
            userIP = data.ip;
            locationData = {
                country: data.country_name,
                region: data.region,
                city: data.city,
                latitude: data.latitude,
                longitude: data.longitude
            };
            console.log('User IP and location data:', { userIP, locationData }); // Log IP and location data
            return userIP;
        } catch (error) {
            console.error('Error fetching IP and location data:', error);
        }
    }

    async function isExcludedIP(ip) {
        try {
            const response = await fetch(`/excludedips/check?ip=${ip}`);
            const data = await response.json();
            return data.isExcluded;
        } catch (error) {
            console.error('Error checking excluded IP:', error);
        }
    }

    function sendPageViewEvent(data) {
        const jsonData = JSON.stringify(data);
        console.log('Sending event data:', jsonData); // Log the data being sent

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

    async function handleVisibilityChange() {
        const currentTime = new Date();
        if (document.visibilityState === 'hidden') {
            totalFocusTime += (currentTime - focusStartTime) / 1000; // Calculate focus time in seconds
            const isExcluded = await isExcludedIP(userIP);
            if (!isExcluded) {
                const event = {
                    user_id: userId,
                    page_url: pageUrl,
                    start_time: startTime.toISOString(),
                    end_time: currentTime.toISOString(),
                    focus_time: totalFocusTime,
                    ip_address: userIP,
                    location: locationData
                };
                trackingData.push(event);
                localStorage.setItem('pageViewTrackingData', JSON.stringify(trackingData));
                console.log('Page hidden, focus time recorded:', totalFocusTime); // Log when page visibility changes
            }
        } else if (document.visibilityState === 'visible') {
            focusStartTime = new Date();
            console.log('Page visible'); // Log when page becomes visible
        }
        visibilityChangeTime = currentTime;
    }

    async function sendDataBeforeUnload() {
        const endTime = new Date();
        const focusEndTime = new Date();
        totalFocusTime += (focusEndTime - focusStartTime) / 1000; // Calculate focus time in seconds

        const isExcluded = await isExcludedIP(userIP);
        if (!isExcluded) {
            const event = {
                user_id: userId,
                page_url: pageUrl,
                start_time: startTime.toISOString(),
                end_time: endTime.toISOString(),
                focus_time: totalFocusTime,
                ip_address: userIP,
                location: locationData
            };
            trackingData.push(event);

            localStorage.setItem('pageViewTrackingData', JSON.stringify(trackingData));
            sendPageViewEvent(event);
            console.log('Before unload, data sent:', event); // Log when data is sent before unload
        }
    }

    window.addEventListener('beforeunload', sendDataBeforeUnload);
    document.addEventListener('visibilitychange', handleVisibilityChange);

    window.addEventListener('load', async () => {
        startTime = new Date();
        focusStartTime = new Date();
        console.log('Page loaded'); // Log when the page is loaded

        // Retrieve any stored data from localStorage and send it
        const storedData = localStorage.getItem('pageViewTrackingData');
        if (storedData) {
            const events = JSON.parse(storedData);
            for (const event of events) {
                const isExcluded = await isExcludedIP(event.ip_address);
                if (!isExcluded) {
                    sendPageViewEvent(event);
                }
            }
            localStorage.removeItem('pageViewTrackingData');
            console.log('Stored data sent:', events); // Log when stored data is sent
        }

        await getUserIPAndLocation(); // Fetch user IP and location data
    });

    // Optionally, send data periodically if the user stays on the page for a long time
    setInterval(async () => {
        const currentTime = new Date();
        totalFocusTime += (currentTime - visibilityChangeTime) / 1000; // Update focus time
        visibilityChangeTime = currentTime;

        const isExcluded = await isExcludedIP(userIP);
        if (!isExcluded) {
            const event = {
                user_id: userId,
                page_url: pageUrl,
                start_time: startTime.toISOString(),
                end_time: currentTime.toISOString(),
                focus_time: totalFocusTime,
                ip_address: userIP,
                location: locationData
            };
            trackingData.push(event);

            localStorage.setItem('pageViewTrackingData', JSON.stringify(trackingData));
            sendPageViewEvent(event);
            trackingData.length = 0; // Clear tracking data after sending
            console.log('Periodic data sent:', event); // Log periodic data sending
        }
    }, 60000); // Send data every minute
})();

