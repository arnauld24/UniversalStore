<?php

// INITIALIZATION & SECURITY
require_once __DIR__ . '/../../../config/config.php';
require_once INIT_PATH . '/init.php';

use internal_logic\otpVerification;
use external_server\Connect;
// Security check to prevent direct file access.
if (!defined('APP_INIT')) {
    error_log('Attempted direct access to secure utility file: ' . $_SERVER['REQUEST_URI']);
    http_response_code(403); 
    $redirect_url =  ERROR_URL . "?code=403&message= " . urlencode("Access Denied.");
    header("Location: $redirect_url");
    exit('403 Access Denied.'); 
}

require_once SERVER_PATH . '/secure_session_csrf_cookie.php';


if(!$authenticatedUserId){
  header('Location: ' . AUTHENTICATION_URL);
  exit;
}

// Function to send OTP via Email
// Check if user not verified 
try{
$connect = new Connect();
 $stmt = $connect->prepare("SELECT is_verified FROM email_otp WHERE user_id = :user_id AND user_email = :email LIMIT 1");
$stmt->execute([':user_id' => $user_id, ':email' => $email]);
$is_verified_value = $stmt->fetchColumn();
$stmt->closeCursor();

if($is_verified_value !== false){
  if($is_verified_value == 1){
    // User already verified send to profile page
  header("Location: " . PROFILE_URL);
  exit;
  }
else{
if(isset($_GET['action']) && $_GET['action'] === 'request_otp'){
    $emailVerification = new otpVerification();
    $sendOTP = $emailVerification::generateAndSendOtp($user_id, $email,$fname,$lname);
    if($sendOTP){ 
        header("Location: " . VERIFY_ACCOUNT_URL);
    }else{
        if(isset($_SESSION['emailError']) && !empty($_SESSION['emailError'])){
        $otpEmail_Error = $_SESSION['emailError'] ?? 'Error occured while sending OTP to email';
        }
    }
}
}
}
else{
  error_log("Database error while fetching data.");
    header("Location: " . PROFILE_URL);
    exit;
}
}catch(PDOException $e){
   error_log("OTP Error " . $e->getMessage());
   exit;
}
//outputJavaScriptErrorHandler(); // This will output the JavaScript errors



?>


<!DOCTYPE html>     
<html lang="en">  
    <head>
        <meta charset="UTF-8"/>
        <meta name="viewport" content="width=device-width, initial-scale= 1" />
        <title>Verify account- Universal Store</title>
        
  <!-- bootstrap CDN -->
  <link rel="stylesheet" href="../../assets/includes/bootstrap-5.3.7-dist/css/bootstrap.min.css">

  <!-- Font Awesome CDN -->
  <link rel="stylesheet" href="../../assets/includes/fontawesome-free-5.15.4-web/css/all.css">
  <link rel="stylesheet" href="../../assets/includes/fontawesome/fontawesome-free-6.7.2-web/css/all.css">
  
  <!-- custom css file -->
  <link rel="stylesheet" href="../../assets/css/otpVerif1.css">
  <link href="../../assets/imgs/apple.jpeg" rel="icon">
  <link rel="stylesheet" href="../../assets/css/style.css">

</head>
<body>

     <div class="contain">
     <div class="form" style="text-align: center;">
       <h1 class="font-baloo " >Verify Your Account</h1>
       <p class="font-baloo">we emailed you the Six digit otp code. Enter the code below to confirm your email address. It is valid for 5 minutes.</p>
       <form action="" autocomplete="off" id="form">
       	
       	<div class="fields-input">
          <input type="hidden" name="csrf_token" id="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken()); ?>">
           <input type="number" name="otp1"  class="otp_field" placeholder="0" min="0" max="9" maxlength="1" onpaste="false" >
            <input type="number" name="otp2"  class="otp_field" placeholder="0" min="0" max="9" maxlength="1" onpaste="false" >
             <input type="number" name="otp3"  class="otp_field" placeholder="0" min="0" max="9" maxlength="1" onpaste="false" >
              <input type="number" name="otp4"  class="otp_field" placeholder="0" min="0" max="9" maxlength="1" onpaste="false" >
              <input type="number" name="otp5"  class="otp_field" placeholder="0" min="0" max="9" maxlength="1" onpaste="false" >
              <input type="number" name="otp6"  class="otp_field" placeholder="0" min="0" max="9" maxlength="1" onpaste="false" >
       	</div>
         <!-- Display Email OTP error--->
<?php if(isset($otpEmail_Error)){
      echo '<div class="otp_error" style="display:block;">' . htmlspecialchars($otpEmail_Error ?? '') . '</div>';
          unset($otpEmail_Error);
      }
      ?>
        <div class="error-text font-baloo x">Error</div>
       	<div class="submit">
       		<input type="submit" value="Verify Now" class="button font-rubik">
       	</div>
        </form>
        <a href="?action=request_otp" class="font-rubik">Send a new Otp code</a>
     </div>
</div>


      <script>
        //Prepare script to send otp input via ajax/json

      // CSRFTOKEN Update
      function UpdateCsrfToken(newToken){
        const csrfToken = document.getElementById('csrf_token');
        if(csrfToken){
          csrfToken.value = newToken;
          return true;
        }
        else{
          console.error("Fail to update csrtToken in OTP Fields.")
          return false;
        }
      }
           // Function Reset UI State ---
function resetUI() {
    submitBtn.value = 'Verify Now';
    submitBtn.disabled = false; // Re-enable the button
   setTimeout(() => {
      otpInputs.forEach(input => {
          input.style.border = '';
      });
  }, 3000);
}
        // Get all otp_fields for error display
        const otpInputs = document.querySelectorAll('.otp_field');
      
        // Get form inputs 
        const otp_field1 = document.querySelector('input[name="otp1"]');
        const otp_field2 = document.querySelector('input[name="otp2"]');
        const otp_field3 = document.querySelector('input[name="otp3"]');
        const otp_field4 = document.querySelector('input[name="otp4"]');
        const otp_field5 = document.querySelector('input[name="otp5"]');
        const otp_field6 = document.querySelector('input[name="otp6"]');
        const error_text = document.querySelector('.error-text');
        const submitBtn = document.querySelector('input[type="submit"]');
         const csrfToken = document.getElementById('csrf_token');
        
        document.getElementById('form').addEventListener('submit', (e) => {
          e.preventDefault();
          const form  = e.target;
          const submitBtn = form.querySelector('input[type="submit"]');
          const otp1 = otp_field1.value;
          const otp2 = otp_field2.value;
          const otp3 = otp_field3.value;
          const otp4 = otp_field4.value;
          const otp5 = otp_field5.value;
          const otp6 = otp_field6.value;
         
          const currentCsrfToken = csrfToken ? csrfToken.value: '';
          const otp_key = crypto.randomUUID();
          submitBtn.value = 'Verifying...';
          submitBtn.disabled = true;
          if(!currentCsrfToken){
            error_text.style.display = 'block';
            error_text.textContent = 'Session Expired. Please refresh page';
            setTimeout(() => {
                      otpInputs.forEach(input => {
                          input.style.border = '2px solid red';
                      });
                  }, 0);
                  resetUI();
            return;
          }

          if(!otp1 || !otp2 || !otp3 || !otp4 || !otp5 || !otp6){
            error_text.style.display = 'block';
            error_text.textContent = 'All fields are required.';
            setTimeout(() => {
                      otpInputs.forEach(input => {
                          input.style.border = '2px solid red';
                      });
                  }, 0);
                  resetUI();
            return;
          }
         
          
          const INTERNAL_LOGIC_URL = '<?php echo INTERNAL_LOGIC_URL ?>';
          
          fetch( INTERNAL_LOGIC_URL + '/emailVerification.php', {
            method: 'POST',
            headers: {
              'Content-Type' : 'application/json',
              'X-Requested-With' : 'XMLHttpRequest',
              'X-CSRF-Token' : currentCsrfToken
             },
             body: JSON.stringify({
              otp1: otp1,
              otp2: otp2,
              otp3: otp3,
              otp4: otp4,
              otp5: otp5,
              otp6: otp6,
              otp_key_flag: otp_key,
              ajax: '1'
            })
          }).then(response => {
              if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
              .then(data => {
  
                  // update CSRF Token in the form if a new token is send (even on failure)
                  if(data.csrf_token){
                    if(data.csrf_token === currentCsrfToken){
                      error_text.style.display = 'block';
                      error_text.textContent = "Session Expired. Please refresh page";
                      setTimeout(() => {
                      otpInputs.forEach(input => {
                          input.style.border = '2px solid red';
                      });
                  }, 0);
                  resetUI();
                      console.error("CSRF Token did not change, potential.");
                       return;
                    }
                    UpdateCsrfToken(data.csrf_token);
                  }
                    
                if(data.success){
                  if(data.csrf_token){
                    if(data.csrf_token === currentCsrfToken){
                      error_text.style.display = 'block';
                      error_text.textContent = "Session Expired. Please refresh page";
                      setTimeout(() => {
                      otpInputs.forEach(input => {
                          input.style.border = '2px solid red';
                      });
                  }, 0);
                  resetUI();
                      console.error("CSRF Token did not change, potential.");
                       return;
                    }
                    // update token even on error
                    UpdateCsrfToken(data.csrf_token);
                  }
                  //redirect to profile page if success
                  setTimeout(() => window.location.href = data.redirect || '../../dashboard/profile.php', 1000);
                  submitBtn.disabled = false;
                  submitBtn.value = 'Verify Now';
                }else{
                  // update CSRF Token in the form if a new token is send (even on failure)
                        if(data.csrf_token){
                          UpdateCsrfToken(data.csrf_token);
                        }
                        error_text.style.display = 'block';
                        error_text.textContent = data.message || 'Invalid code. Retry or request for a new code';
                        setTimeout(() => {
                      otpInputs.forEach(input => {
                          input.style.border = '2px solid red';
                      });
                  }, 0);
                  resetUI();
                  }
              }).catch(error => {
                  console.error('Error : ', error);
                   setTimeout(() => {
                      otpInputs.forEach(input => {
                          input.style.border = '2px solid red';
                      });
                  }, 0);
                  resetUI();
                  error_text.style.display = 'block';
                  error_text.textContent = 'An unexpected error occured. Please try again.';
              })
        });



      </script>
      <script src="../../assets/js/otp_verify.js"></script>
    </body>
</html>
