
//   // Function to get a cookie value by name
// function getCookie(name) {
//     let value = "; " + document.cookie;
//     let parts = value.split("; " + name + "=");
//     if (parts.length === 2) return parts.pop().split(";").shift();
// }

// // Function to get query parameters as an object
// function getQueryParams() {
//     let params = {};
//     let queryString = window.location.search.substring(1);
//     let pairs = queryString.split("&");
//     for (let i = 0; i < pairs.length; i++) {
//         let pair = pairs[i].split("=");
//         params[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1]);
//     }
//     return params;
// }

// // Get the user_id from the cookie
// let user_id = getCookie('user_id');
// let utm_source = getCookie('utm_source');
// // Get the query parameters
// let queryParams = getQueryParams();
// console.log(queryParams);
// // Create a FormData object
// let formData = new FormData();
// formData.append('dj_user_id',user_id);
// formData.append('utm_source', utm_source);

// for (let key in queryParams) {
//     if (queryParams.hasOwnProperty(key)) {
//         formData.append(key, queryParams[key]);
//     }
// }
// console.log(formData, 'formdataaa');
// // Define the Zapier webhook URL
// const url = 'https://hooks.zapier.com/hooks/catch/18441148/22sew3u/';

// // Send the data using fetch
// fetch(url, {
//     method: 'POST',
//       headers: {
//         'Content-Type': 'application/json'
//     },
//     body: formData,
//     mode: 'no-cors' // Note: 'no-cors' mode is used here assuming you're sending to a different domain
// })
// .then(() => {
//     console.log('Request sent successfully');
// })
// .catch((error) => {
//     console.error('Error:', error);
// });
// fetch('https://api.ipify.org?format=json')
//     .then(response => response.json())
//     .then(data => {
//         const userIp = data.ip;
      

//         // Create the payload object after the IP address is retrieved
//         const payload = {
//             'dj_user_id': getCookie('user_id') || '',
//             'ip_address': userIp || '',
//             'user_id': window._user_id || '',
//             'project_id': 1
//         };

//         // Log the payload to verify the IP address is set correctly
//         console.log('Payload:', payload);

//         // You can now send the payload as needed
//         // For example, using fetch:
//         const webHookUrl = 'https://joshcreative.co/api/webhook';

//         fetch(webHookUrl, {
//             method: 'POST',
//             headers: {
//                 'Content-Type': 'application/json'
//             },
//             body: JSON.stringify(payload),
//             mode: 'no-cors' // or 'no-cors' depending on your requirements
//         })
//         .then(response => {
//             if (!response.ok) {
//                 throw new Error('Network response was not ok ' + response.statusText);
//             }
//             return response.json();
//         })
//         .then(data => {
//             console.log('Request sent successfully', data);
//         })
//         .catch((error) => {
//             console.error('Error:', error);
//         });
//     })
//     .catch(error => {
//         console.error("Failed to fetch IP address:", error);
//     });
// </script>










// WEBFLOW SCRIPT 

// <!-- Google Tag Manager (noscript) -->
// <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-PCZT67CQ"
// height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
// <!-- End Google Tag Manager (noscript) -->

// <!-- Google tag (gtag.js) -->
// <script async src="https://www.googletagmanager.com/gtag/js?id=G-V852D5MYRD"></script>
// <script>
//   window.dataLayer = window.dataLayer || [];
//   function gtag(){dataLayer.push(arguments);}
//   gtag('js', new Date());

//   gtag('config', 'G-V852D5MYRD');
  
//    const userID = getUserID();

//       gtag('config', 'G-V852D5MYRD', {
//      'user_id': userID
//      });
  
//   function setPerCookie(name, value, days) {
//     let expires = "";
//     if (days) {
//         let date = new Date();
//         date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
//         expires = "; expires=" + date.toUTCString();
//     }
//      document.cookie = name + "=" + (value || "") + expires + "; path=/; domain=academyofdjs.com; SameSite=None; Secure";
// }
  
//   function setCookie(name, value, days) {
//           var expires = "";
//           if (days) {
//               var date = new Date();
//               date.setTime(date.getTime() + (days*24*60*60*1000));
//               expires = "; expires=" + date.toUTCString();
//           }
//           document.cookie = name + "=" + (value || "")  + expires + "; path=/";
//       }
//      function getCookie(name) {
//           var nameEQ = name + "=";
//           var ca = document.cookie.split(';');
//           for(var i=0;i < ca.length;i++) {
//               var c = ca[i];
//               while (c.charAt(0)==' ') c = c.substring(1,c.length);
//               if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
//           }
//           return null;
//       }

//       // Generate or retrieve a unique user ID
//       function getUserID() {
//           let userID = getCookie('user_id');
//           if (!userID) {
//               userID = 'user_' + Math.random().toString(36).substr(2, 9);
//               setCookie('user_id', userID, 365); // Set cookie to expire in 1 year
//             setPerCookie('dj_user_id', userID, 365);
//           }
//           return userID;
//       }
//             dataLayer.push({
//         'user_id' : userID,
//         })
//          dataLayer.push({'event': 'adding_user', 'user_id': userID});
  
//     function getUrlParameter(name) {
//           name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
//           var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
//           var results = regex.exec(location.search);
//           return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
//       }
//  // Capture UTM parameters and store them in cookies
//       function captureUtmParameters() {
//           var utmSource = getUrlParameter('utm_source') || '';
//           var utmMedium = getUrlParameter('utm_medium') || '';
//           var utmCampaign = getUrlParameter('utm_campaign') || '';

//           if (utmSource) setCookie('utm_source', utmSource, 30);
//           if (utmMedium) setCookie('utm_medium', utmMedium, 30);
//           if (utmCampaign) setCookie('utm_campaign', utmCampaign, 30);
//         console.log('test');
//       }
//    // Call the function to capture UTM parameters
//       captureUtmParameters();
     

//       function sendUserDataToGA4() {
//           const utmSource = getCookie('utm_source') || '';
//           const utmMedium = getCookie('utm_medium') || '';
//           const utmCampaign = getCookie('utm_campaign') || '';

//           // Send user data to GA4
//          gtag('event', 'page_view', {
//                'user_id': userID,
//               'utm_source': utmSource,
//               'utm_medium': utmMedium,
//               'utm_campaign': utmCampaign
//           });

//           // Set user properties in GA4
//           gtag('set', {
//               'user_properties': {
//                 'user_id': userID,
//                   'utm_source': utmSource,
//                   'utm_medium': utmMedium,
//                   'utm_campaign': utmCampaign
//               }
//           });
//       }
//       document.addEventListener('DOMContentLoaded', function() {
//           sendUserDataToGA4();
//       });
//   let userIp;
// 		   document.getElementById("source").value = getCookie('utm_source') || '';
// document.getElementById("user_id_dj").value = getCookie('user_id') || '';
// 	console.log(getCookie('utm_source'));
//   console.log('working');
//  fetch('https://api.ipify.org?format=json')
//       .then(response => response.json())
//       .then(data => {
//         document.getElementById('ip').textContent = data.ip;
//    		userIp = data.ip;
//         console.log("Your IP Address:", data.ip);
//       })
//       .catch(error => {
//         console.error("Error fetching IP address:", error);
//       });
  
  

//   document.addEventListener('DOMContentLoaded', function() {
//     console.log('registered');
//     // Your code that runs after DOMContentLoaded
//     const submitButton = document.querySelector('.senddata');
    
//     submitButton.addEventListener('click', function(event) {
   
  
//       // Redirect URL with parameters
//       const baseUrl = 'https://academyofdjs.teachable.com/p/redirect?userId=';
//       const redirectUrl = baseUrl + encodeURIComponent(getCookie('user_id'));
//   	  const webHookUrl = 'https://joshcreative.co/api/webhook';

// const payload = {
//     'dj_user_id': getCookie('user_id') || '',
//     'utm_source': getCookie('utm_source') || '',
//   	'ip_address': userIp || '',
//   	'email': document.getElementById('email').value || '',
//   	'project_id': 1,
// };

// fetch(webHookUrl, {
//     method: 'POST',
//     headers: {
//         'Content-Type': 'application/json'
//     },
//     body: JSON.stringify(payload)
// })
// .then(response => {
//     console.log(response);
//     return response.json();
// })
// .then(data => {
//     console.log('Request sent successfully', data);
// })
// .catch((error) => {
//     console.error('Error:', error);
// });

//       // Redirect to the new URL
//       window.location.href = redirectUrl;
//     });
//   });




