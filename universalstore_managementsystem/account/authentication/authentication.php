<?php
// INITALIZE APP
if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

// Register secure session helper early. It will register the project autoloader.
require_once __DIR__ . '/../../../config/config.php';
require_once SERVER_PATH . '/secure_session_csrf_cookie.php';
require_once INIT_PATH . '/oauth_config.php';


require_once CORE_PATH . '/google_oauth_handler.php' ;
outputJavaScriptErrorHandler(); // This will output the JavaScript errors

use core\GoogleAuthController;
$authenticatedUserId = GoogleAuthController::getAuthenticatedUserId();

//REDIRECTION IF LOGGED IN
// If $authenticatedUserId is NOT NULL, the user IS logged in.
if ($authenticatedUserId) {
    header('Location: ' . INDEX_URL);
    exit;
}

?>

<!DOCTYPE html>     
<html lang="en">  
    <head>
        <meta charset="UTF-8"/>
        <meta name="viewport" content="width=device-width, initial-scale= 1" />
        <title>Authentication - Universal Store</title>
        
  <!-- bootstrap CDN -->
  <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/includes/bootstrap-5.3.7-dist/css/bootstrap.min.css">

  <!-- Font Awesome CDN -->
  <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/includes/fontawesome-free-5.15.4-web/css/all.css">
  <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/includes/fontawesome/fontawesome-free-6.7.2-web/css/all.css">

  <!-- custom css file -->
  <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/authentications.css">
  <link href="https://ik.imagekit.io/univ35tore/universal_store/imgs/UNIVERSAL%20STORE.jpg" rel="icon">
  <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/styling.css">

</head>
<body class="body">
    <!--    Google Login error-->
    <div class="google-error">
    <?php
    if(isset($_SESSION['google_error'])){
        echo '<div id="google-auth-status" class="font-Roboto google-error" style="display:block; color:red; background: rgba(241, 177, 177, 1); text-align:center;">' . htmlspecialchars($_SESSION['google_error']) . '</div>';
        unset($_SESSION['google_error']);
    }
    ?>
    </div>
    
    <div class="container" >

    <!-- LOGIN FORM  -->

<div class="form-box login">
    <form action="#" id="login-form">
        <h1>Login</h1>
        <div id="auth-status" class="font-Roboto"></div>
        <input type="hidden" name="csrf_token" id="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken()); ?>" />
        <div class="input-box">
            <input type="email" name="email" placeholder="Enter your email address" autocomplete="email" />
            <i class="fa-solid fa-envelope"></i>
        </div>
        <div class="input-box">
            <input type="password" class="password" name="loginpassword" placeholder="Your Password" />
            <span class="togglePassword" class="bi-eye-slash" style="position:absolute; right:18px; top:10px; cursor: pointer;"><i class="fa fa-eye-slash" style="right:0px; top:15px;" ></i></span>
        </div>
            <div class="forgot-link">
                <a href="#">Forget Password ?</a>
        </div>
        <input type="submit" name="login"  value="Login" class="login-btn">
        <p>Or login with Social Platforms</p>
        <div class="social-icons">
          <button type="button" data-login-url="<?php echo $login_url?>"  id="loginGoogle-Oauth" name="google-oauth" class="google_oauth" href="#" aria-label="Sign in with Google"><i class="fa-brands fa-google" ></i></button>
             <a href="#" aria-label="Sign in with Facebook"><i class="fa-brands fa-facebook-f"></i></a>
              <a href="#" aria-label="Sign in with LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
        </div>
        
    </form>
</div>

    <!-- Signup FORM  -->
<div class="form-box signup">
    <form action="#" id="signup-form">
        <h1>Sign Up</h1> 
        <div id="signup-auth-status" class="font-Roboto"></div>
        <div class="input-box">
        <input type="hidden" name="signup_csrf_token" id="signup_csrf_token" value="<?php echo htmlspecialchars(getCsrfToken()); ?>" />
        <input type="text" name="fname" placeholder="Your First Name" autocomplete="name" />
        <i class="fa-solid fa-user"></i>
        </div>
        <div class="input-box">
            <input type="text" name="lname" placeholder="Your Last Name" autocomplete="name" />
            <i class="fa-solid fa-user"></i>
        </div>
        <div class="input-box">
            <input type="email" id="signupemail" name="email" placeholder="Enter your email address" autocomplete="email" />
            <i class="fa-solid fa-envelope"></i>
        </div>
        <div class="input-box">
            <input type="password" class="password" name="signuppassword" placeholder="Your Password" />
            <span class="togglePassword" class="bi-eye-slash" style="position:absolute; right:18px; top:10px; cursor: pointer;"><i class="fa fa-eye-slash" style="right:0px; top:15px;" ></i></span>
        </div>
        <div id="contextData" class="contextData">
            <input type="hidden" name="timezone" id="user-timeZone" value="">
            <input type="hidden" name="devictype" id="user-deviceType" value="">
            <input type="hidden" name="locale" id="user-locale" value="">
        </div>
        <input type="submit" name="signup" value="Sign Up" class="signup-btn">
        <p>Or Sign Up with Social Platforms</p>
        <div class="social-icons">
          <button type="button" data-login-url="<?php echo $login_url?>"  id="signupGoogle-Oauth" name="google-oauth" class="google_oauth" href="#" aria-label="Sign in with Google"><i class="fa-brands fa-google" ></i></button>
             <a href="#" aria-label="Sign in with Facebook"><i class="fa-brands fa-facebook-f"></i></a>
              <a href="#" aria-label="Sign in with LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
       </div>
        
    </form>
</div>


    <!-- Toggle box    -->

 <div class="toggle-box">

    <!-- Toggle box left   -->

   <div class="toggle-panel toggle-left">
    <h2 class="font-Roboto">Universal Store</h2>
    <h1>Welcome Back!</h1>
    <p>Don't have an Account ?</p>
    <button class="btn signup-btns">Sign up</button>

   </div>

    <!-- Toggle box right   -->

   <div class="toggle-panel toggle-right">
    <h2 class="font-Roboto">Universal Store</h2>
    <h1>Hello, Welcome!</h1>
    <p>Already have an Account ?</p>
    <button class="btn login-btns">Log in</button>

    </div>
        </div>
            </div>
    <script src="<?php echo ASSETS_URL; ?>/js/authentication.js"></script>
    <script>
const INTERNAL_SERVER_URL = '<?php echo INTERNAL_SERVER_URL ?>';
const CORE_URL = '<?php echo CORE_URL ?>';
    // --- GOOGLE LOGIN/SIGNUP ---  //
// general fucntion used by Non-Google-Oauth-Signup and Non-Google-Oauth-login

 //function to get user device type 
function getDeviceType() {
    const ua = navigator.userAgent;
    if (/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i.test(ua)) {
        return 'Tablet';
    }
    if (/Mobile|iP(hone|od)|Android|BlackBerry|IEMobile|Kindle|Silk-L|Opera M(obi|ini)|Dolfin|KZG|IUP|UP\.Browser|Wasa|Ava/i.test(ua)) {
        return 'Mobile';
    }
    return 'Desktop';
}
//Function to get user's timezone, device_type and locale(language-fr/en)
function getUserContextData() {
    return{
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'Unknown',
        devicetype: getDeviceType(),
        locale: navigator.language || 'en-US'
    };
}
    
    //We shall use the getUserContextData defined to get all user context data and insert them in the fields
const googleUserAgent = getUserContextData();

// container of usercontextdata 
const contextDataContainer = document.getElementById('contextData');

const timezoneInput = document.getElementById('user-timeZone');
const deviceTypeInput = document.getElementById('user-deviceType');
const localeInput = document.getElementById('user-locale');
const AjaxCsrfToken = '<?php echo htmlspecialchars(getajaxcsrftoken()); ?>';
// google button that initiate the action for contextData transfer
const googleAuthButton = document.getElementById('signupGoogle-Oauth');
const googleLogAuthButton = document.getElementById('loginGoogle-Oauth');
//function to collect context data 

function collectContextData() {
    // determine timezone
    const userTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    timezoneInput.value = userTimeZone;

    // determine devicetype
    let deviceType = 'Desktop';
    const userAgent = navigator.userAgent.toLowerCase();
    if (/(mobile|android|iphone|ipod)/i.test(userAgent)) {
        deviceType = 'Mobile';
    } else if (/(tablet|ipad)/i.test(userAgent)) {
        deviceType = 'Tablet';
    }
    deviceTypeInput.value = deviceType;

    // determine locale
    const userLocale = navigator.language || navigator.userLanguage;
    localeInput.value = userLocale;

    // send data as group with their values
    const groupedData = {
        timeZone: timezoneInput.value,
        deviceType: deviceTypeInput.value,
        locale: localeInput.value,
    };

    return groupedData;
}

//Function for the event button initialize by google
async function handleGoogleAuthClick(event){
   event.preventDefault();

   const contextData = collectContextData();
    // This is the url to which google will redirect the OAuth endpoint which is in the data-login-url in the html
   const baseLoginUrl = googleAuthButton.dataset.loginUrl;
   if(!baseLoginUrl){
    console.error(" data-login-url is absent in the google button");
    signupstatusDiv.style.display = 'block';
    signupstatusDiv.textContent = "An error occurred. Please reload page.";
    signupstatusDiv.style.background = 'rgb(243, 163, 163)';
    return;
   }
   // check csrf_token exists
   if (!AjaxCsrfToken) {
        console.error('Erreur: No CSRF token found.');
        signupstatusDiv.style.display = 'block';
        signupstatusDiv.textContent = "Session expired. Please reload page.";
        signupstatusDiv.style.background = 'rgb(243, 163, 163)';
        return;
    }
  //construct URLSearchParams in other to encode GET parameter in a secure manner
  const params = new URLSearchParams();

  //Add the user context data
  params.append('tz', contextData.timeZone);
  params.append('dt', contextData.deviceType);
  params.append('lc', contextData.locale);

  //join the base URL with new parameters
  //we use '?' if URL has no parameter and '&' if it already has
   const separator = baseLoginUrl.includes('?') ? '&': '?';
   const finalLoginUrl = `${baseLoginUrl}${separator}${params.toString()}`;

   //display final url in log
   console.log("Final URL", finalLoginUrl);

   // Clear previous errors
   signupstatusDiv.style.display = 'none';
   signupstatusDiv.textContent = '';
   signupstatusDiv.style.background = '';

   //configure the fetch command to send data
try{
  const response = await fetch(CORE_URL + '/context_store_api.php', {
        method: 'POST',
        headers: {
            'content-type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token' : AjaxCsrfToken
        },
        body: JSON.stringify({
            tz: contextData.timeZone,
            lc: contextData.locale,
            dt: contextData.deviceType,
            action: 'store_context'
        })
    });

    const data = await response.json();
    if(response.ok && data.success){
         console.log("User context data stored successfully");
        // redirect to Google OAuth URL
        window.location.href = finalLoginUrl;
    }else{
        // Display error message
        const errorMessage = data.message || 'Failed to store context data. Please try again.';
        signupstatusDiv.style.display = 'block';
        signupstatusDiv.textContent = errorMessage;
        signupstatusDiv.style.background = 'rgb(243, 163, 163)';
        console.error('Failed to store user context data. Response status:', response.status,' Error:', data);
    }
}
catch(error){
        console.error("AJAX Fetch Error:" , error);
        signupstatusDiv.style.display = 'block';
        signupstatusDiv.textContent = 'Network error. Please check your connection and try again.';
        signupstatusDiv.style.background = 'rgb(243, 163, 163)';
    }
}

// For Google Signup button
if (googleAuthButton) {
// ensure data is immediately available in case the are read somewhere
    document.addEventListener('DOMContentLoaded', collectContextData); 
    // relate fucntion to google click button in html
    googleAuthButton.addEventListener('click', handleGoogleAuthClick);
} else {
    console.error("Le bouton #signupGoogle-Oauth n'a pas été trouvé.");
}

//For Google Login button
 if (googleLogAuthButton) {
// ensure data is immediately available in case the are read somewhere
    document.addEventListener('DOMContentLoaded', collectContextData); 
    // relate fucntion to google click button in html
    googleLogAuthButton.addEventListener('click', handleGoogleAuthClick);
} else {
    console.error("Le bouton #signupGoogle-Oauth n'a pas été trouvé.");
}


        // --- Form Submission Logic (AJAX/Fetch for local authentication) ---
        const statusDiv = document.getElementById('auth-status');
        const emailInput = document.querySelector('input[name="email"]');
        const passInput  = document.querySelector('input[name="loginpassword"]');
       
        // CSRF TOKEN Regenaration 
function UpdateCsrfToken(newToken) {
    const csrfInput = document.getElementById('csrf_token');
    if(csrfInput) {
        csrfInput.value = newToken;
        console.log("CSRF Token updated succesfully.  // Note this is not an error, it was suppossed to be logged but put in error_type because logging function not set here");
    }else {
        console.error("CSRF Token update failed or not found");
    }
}
           // Function Reset UI State ---
function resetUI(loginButton) {
   
    loginButton.value = 'Login';
    loginButton.disabled = false; // Re-enable the button
    emailInput.style.border = '';
    passInput.style.border = '';
    statusDiv.style.color = ''; 
}
        // LOGIN HANDLING
        document.getElementById('login-form').addEventListener('submit', (e) => {
            e.preventDefault();
            const form = e.target;
            // get data from the form inputs
            const email = emailInput.value.trim();
            const password = passInput.value.trim();
            const loginButton = form.querySelector('input[type="submit"]');
            const csrfInput = document.getElementById('csrf_token');
            const currentCsrfToken = csrfInput ? csrfInput.value : '';
            const userAgent = getUserContextData();
            // get login btn originalText
            originalText = loginButton.value;
            // verify if token exists
         if (!currentCsrfToken) {
        console.error('Erreur: No CSRF token found.');
        statusDiv.style.display = 'block';
        statusDiv.textContent = "Session expired. Please reload page.";
        statusDiv.style.background = 'rgb(243, 163, 163)';
        return;
            }
            
                // Clear previous errors
                resetUI(loginButton); 
            if (!email || !password) {
               // setTimeout(() => loginButton.value = 'Login', 200);
                loginButton.value ="Proccessing...";
               setTimeout( () => (statusDiv.style.display = 'block',
                statusDiv.textContent = 'Email and password are required.',
                statusDiv.style.background = 'rgb(243, 163, 163)',
                emailInput.style.border = '2px solid red',
                passInput.style.border = '2px solid red' ), 200) ;
                loginButton.value = originalText;
                return;
            }
            
            // update status message
          
            //setTimeout(() => loginButton.value = 'Login', 200);
            loginButton.disabled = true; // Disable the button to prevent multiple submissions
            loginButton.value ="Proccessing...";
            statusDiv.style.display = 'block';



            // perform AJAX POST request using Fetch API
            fetch(CORE_URL + '/non_google_auth_login_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': currentCsrfToken

                },
                body: JSON.stringify({
                    email: email,
                    loginpassword: password,
                    timezone: userAgent.timezone,
                    devicetype: userAgent.devicetype,
                    locale: userAgent.locale,
                })
            })
            .then(response => {
                // Check if response is ok and has JSON content type
                if (!response.ok) {
                    // If response is not ok, try to get error message from response
                    return response.text().then(text => {
                        try {
                            const errorData = JSON.parse(text);
                            throw new Error(errorData.message || `HTTP ${response.status}: ${response.statusText}`);
                        } catch (e) {
                            throw new Error(`HTTP ${response.status}: ${text || response.statusText}`);
                        }
                    });
                }
                
                // Check content type
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Server returned non-JSON response');
                }
                
                return response.json().then(data => {
                    if (!response.ok) { 
                        // update CSRF_Token if a new one is send (even on failure)
                        if(data.csrf_token){
                             if(data.csrf_token === currentCsrfToken){
                                console.error("CSRF Token did not change, potential issue.");
                                statusDiv.style.display = 'block';
                                statusDiv.textContent = "There was an issue verifying your info. Please reload page.";
                                statusDiv.style.background = 'rgb(243, 163, 163)';
                                return;

                            }
                           UpdateCsrfToken(data.csrf_token);
                        }
                        if(data.redirect){
                            window.location.href = data.redirect;
                            return;
                        }
                       
                        // Server error with JSON, show message
                        loginButton.disabled = false; // re-enable button
                        statusDiv.textContent = data.message || 'Login failed.';
                        statusDiv.style.background = 'rgb(243, 163, 163)';
                        resetUI(loginButton);
                        return;
                    }
                    // Success
                    if (data.success) {
                         // update CSRF_Token if a new one is send 
                        if(data.csrf_token){
                             if(data.csrf_token === currentCsrfToken){
                                console.error("CSRF Token did not change, potential issue.");
                                statusDiv.style.display = 'block';
                                statusDiv.textContent = "There was an issue verifying your info. Please reload page.";
                                statusDiv.style.background = 'rgb(243, 163, 163)';
                                return;

                            }
                           UpdateCsrfToken(data.csrf_token);
                        }
                        loginButton.disabled = false; // re-enable button
                        statusDiv.style.background = 'rgb(212, 255, 212)';
                        statusDiv.textContent = data.message || 'Login successful!';
                        statusDiv.style.color = 'green';
                        window.location.href = data.redirect || '<?php echo INDEX_URL; ?>';
                    } else {
                         // update CSRF_Token if a new one is send (even on failure)
                        if(data.csrf_token){
                           UpdateCsrfToken(data.csrf_token);
                        }
                        loginButton.disabled = false; // re-enable button
                        statusDiv.textContent = data.message || 'Login failed. Please check your credentials.';
                        statusDiv.style.background = 'rgb(243, 163, 163)';
                        resetUI(loginButton);
                    }
                }).catch(jsonError => {
                    // JSON parsing failed, log and show generic
                    fetch(INTERNAL_SERVER_URL + '/log_js_error.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                    body: new URLSearchParams({
                        js_error: btoa(jsonError.message),
                        js_url: window.location.href,
                        js_line: 'fetch_login_json_parse',
                        js_column: '0',
                        js_error_type: 'unhandled_error'
                    })
                    }).catch(logError => console.error('Failed to log error:', logError));
                    loginButton.disabled = false; // re-enable button
                    statusDiv.textContent = 'An error occurred. Please try again.';
                    statusDiv.style.background = 'rgb(243, 163, 163)';
                    resetUI(loginButton);
                });
            })
            .catch(error => {
                // Handle network errors or JSON parsing errors
                console.error('Login request failed:', error);
                fetch(INTERNAL_SERVER_URL + '/log_js_error.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        js_error: btoa(error.message || 'Unknown error'),
                        js_url: window.location.href,
                        js_line: 'fetch_login_error',
                        js_column: '0',
                        js_error_type: 'unhandled_error'
                    })
                }).catch(logError => console.error('Failed to log error:', logError));
                loginButton.disabled = false; // re-enable button
                statusDiv.textContent = 'Network error. Please check your connection and try again.';
                statusDiv.style.background = 'rgb(243, 163, 163)';
                resetUI(loginButton);
            });
        });

  // --- Form Submission SIGNUP (AJAX/Fetch for local authentication) ---
        const signupstatusDiv = document.getElementById('signup-auth-status');
        const signupEmailInput = document.getElementById('signupemail');
        const signupPassInput  = document.querySelector('input[name="signuppassword"]');
        const signupFnameInput  = document.querySelector('input[name="fname"]');
        const signupLnameInput  = document.querySelector('input[name="lname"]');
        
        //CSRF TOKEN Regenaration 
function signupUpdateCsrfToken(signupNewToken) {
    const signupCsrfInput = document.getElementById('signup_csrf_token');
    if(signupCsrfInput) {
        signupCsrfInput.value = signupNewToken;
        console.log("CSRF Token updated succesfully");
    }else{
        console.error("CSRF Token update failed or not found");
    }
}

           // Function Reset UI State ---
function resetSignupUI(signupButton) {
    signupButton.value = 'Sign Up';
    signupButton.disabled = false; // Re-enable the button
    signupEmailInput.style.border = '';
    signupFnameInput.style.border = '';
    signupLnameInput.style.border = '';
    signupPassInput.style.border = '';
    signupstatusDiv.style.color = '';
}
        // SIGNUP HANDLING
        document.getElementById('signup-form').addEventListener('submit', (e) => {
            e.preventDefault();
            const form = e.target;
            // get data from the form inputs
            const signupButton = form.querySelector('input[type="submit"]');
            const firstName = signupFnameInput.value.trim();
            const lastName = signupLnameInput.value.trim();
            const email = signupEmailInput.value.trim();
            const password = signupPassInput.value.trim();
            const userAgent = getUserContextData();
            const signupCsrfInput = document.getElementById('signup_csrf_token');
            const signupCurrentCsrfToken = signupCsrfInput ? signupCsrfInput.value : '';
                if(!signupCurrentCsrfToken){
                    console.error("No CSRF Token Found");
                    signupstatusDiv.style.display = 'block';
                    signupstatusDiv.textContent = 'Session expired, please Reload page';
                    signupstatusDiv.style.background = 'rgb(243, 163, 163)';
                    return;
                }
             // Clear previous errors
                resetSignupUI(signupButton); 
            if (!firstName || !lastName || !email || !password) {
                setTimeout(() => signupButton.value = 'Sign Up', 200);
                signupButton.value ="Proccessing...";
                setTimeout( () => (signupstatusDiv.style.display = 'block',
                signupstatusDiv.textContent = 'All fields are required.',
                signupstatusDiv.style.background = 'rgb(243, 163, 163)',
                signupFnameInput.style.border = '2px solid red',
                signupLnameInput.style.border = '2px solid red',
                signupEmailInput.style.border = '2px solid red',
                signupPassInput.style.border = '2px solid red' ), 200) ;
                return;
                
            }
            if (password.length < 8) {
                setTimeout(() => signupButton.value = 'Sign Up', 200);
                signupButton.value ="Proccessing...";
                setTimeout( () => (signupstatusDiv.style.display = 'block',
                signupstatusDiv.style.background = 'rgb(243, 163, 163)',
                signupstatusDiv.textContent = 'Password must be at least 8 characters.',
                signupPassInput.style.border = '2px solid red' ), 200) ;
                return;
            }

            // update status message
            setTimeout( () => signupButton.value = 'Sign Up', 200);
            signupButton.disabled = true; // Disable the button to prevent multiple submissions
            signupButton.value ="Proccessing...";
            signupstatusDiv.style.display = 'block';

            // Perform the AJAX POST request using Fetch API
            fetch(CORE_URL + '/non_google_auth_signup_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Csrf-Token' : signupCurrentCsrfToken
                },
                body: JSON.stringify({
                    fname: firstName,
                    lname: lastName,
                    email: email,
                    signuppassword: password,
                    timezone: userAgent.timezone,
                    devicetype: userAgent.devicetype,
                    locale: userAgent.locale,
                })
            })
            .then(response => {
                return response.json()
                .then(data => {
                    if (!response.ok) {
                        // update CSRF Token in the form if a new token is send (even on failure)
                        if(data.csrf_token){
                             if(data.csrf_token === signupCurrentCsrfToken){
                                console.error("CSRF Token did not change, potential issue.");
                                signupstatusDiv.style.display = 'block';
                                signupstatusDiv.textContent = "There was an issue verifying your info. Please reload page.";
                                signupstatusDiv.style.background = 'rgb(243, 163, 163)';
                                return;
                            }
                            signupUpdateCsrfToken(data.csrf_token);
                            }
                        if(data.redirect){
                            window.location.href = data.redirect;
                            return;
                        }
                        // Server error with JSON, show message
                        signupButton.disabled = false; // Re-enable the button
                        signupstatusDiv.textContent = data.message || 'Registration failed.';
                        signupstatusDiv.style.background = 'rgb(243, 163, 163)';
                        signupstatusDiv.style.color = 'red';
                        resetSignupUI(signupButton);
                        return;
                    }
                    // Success
                    if (data.success) {
                 // Update CSRF Token in the form if a new token is send
                        if(data.csrf_token){
                             if(data.csrf_token === signupCurrentCsrfToken){
                                console.error("CSRF Token did not change, potential issue.");
                                signupstatusDiv.style.display = 'block';
                                signupstatusDiv.textContent = "There was an issue verifying your info. Please reload page.";
                                signupstatusDiv.style.background = 'rgb(243, 163, 163)';
                                return;
                            }
                          signupUpdateCsrfToken(data.csrf_token);
                        }
                         signupButton.disabled = false; // Re-enable the button
                        signupstatusDiv.style.background = 'rgb(212, 255, 212)';
                        signupstatusDiv.textContent = data.message || 'Registration successful!';
                        signupstatusDiv.style.color = 'green';
                        setTimeout(() => window.location.href = data.redirect || '<?php echo INDEX_URL; ?>', 1000);
                    } else {
                        // update CSRF Token in the form if a new token is send (even on failure)
                        if(data.csrf_token){
                          signupUpdateCsrfToken(data.csrf_token);
                        }
                         signupButton.disabled = false; // Re-enable the button
                        signupstatusDiv.textContent = data.message || 'Registration failed. Please try again.';
                        signupstatusDiv.style.background = 'rgb(243, 163, 163)';
                        signupstatusDiv.style.color = 'red';
                        resetSignupUI(signupButton);
                    }
                }).catch(jsonError => {
                    // JSON parsing failed, log and show generic
                    fetch(INTERNAL_SERVER_URL + '/log_js_error.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            js_error: btoa(jsonError.message),
                            js_url: window.location.href,
                            js_line: 'fetch_signup_json_parse',
                            js_column: '0',
                            js_error_type: 'unhandled_error'
                        })
                    }).catch(logError => console.error('Failed to log error:', logError));
                     signupButton.disabled = false; // Re-enable the button
                    signupstatusDiv.textContent = 'An error occurred. Please try again.';
                    signupstatusDiv.style.color = 'red';
                    signupstatusDiv.style.background = 'rgb(243, 163, 163)';
                    resetSignupUI(signupButton);
                });
            })
            .catch(networkError => {
                // Network error, log and show generic
                fetch(INTERNAL_SERVER_URL + '/log_js_error.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                        body: new URLSearchParams({
                            js_error: btoa(networkError.message),
                            js_url: window.location.href,
                            js_line: 'fetch_signup_network',
                            js_column: '0',
                            js_error_type: 'unhandled_error'
                        })
                }).catch(logError => console.error('Failed to log error:', logError));
                 signupButton.disabled = false; // Re-enable the button
                signupstatusDiv.textContent = 'An error occurred. Please try again.';
                signupstatusDiv.style.color = 'red';
                signupstatusDiv.style.background = 'rgb(243, 163, 163)';
                resetSignupUI(signupButton);
            });
        });
    </script>
    </body>
</html>
