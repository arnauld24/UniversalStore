<?php

require_once ('config.php');
require_once ('core/google_oauth_handler.php');
?>

<!DOCTYPE html>     
<html lang="en">  
    <head>
        <meta charset="UTF-8"/>
        <meta name="viewport" content="width=device-width, initial-scale= 1" />
        <link rel="stylesheet" href="../../assets/css/stylin01.css">
        <title>Login/Signup - Universal Store</title>
        
  <!-- bootstrap CDN -->
  <link rel="stylesheet" href="../../assets/includes/bootstrap-5.3.7-dist/css/bootstrap.min.css">

  <!-- Font Awesome CDN -->
  <link rel="stylesheet" href="../../assets/includes/fontawesome-free-5.15.4-web/css/all.css">
  <link rel="stylesheet" href="../../assets/includes/fontawesome/fontawesome-free-6.7.2-web/css/all.css">

  <!-- custom css file -->
  <link rel="stylesheet" href="../../assets/css/authentication.css">
  <link href="../../assets/imgs/apple.jpeg" rel="icon">
  <link rel="stylesheet" href="../../assets/css/stylin01.css">

</head>
<body class="body">
    <!--    Google Login-->
    <?php  
    
        if(isset($_COOKIE['user_id']) && isset($_COOKIE['user_session'])){
           $controller = new controller;
           if($controller -> checkUserStatus($_COOKIE['user_id'], $_COOKIE['user_session'])){
           echo $controller -> printData(strval($_COOKIE['user_id']));
           echo '<a href="logout.php">Logout</a>';
           }else{
            echo"Error!";
           }
        }
    else{
    ?>
    <div class="container" >

    <!-- LOGIN FORM  -->

<div class="form-box login">
    <form action="#" id="login-form">
        <h1>Login</h1>
        <div class="input-box">
            <input type="email" name="email" placeholder="Enter your email address" autocomplete="email" required/>
            <i class="fa-solid fa-envelope"></i>
        </div>
        <div class="input-box">
            <input type="password" class="password" name="loginpassword" placeholder="Your Password" required/>
            <span class="togglePassword" class="bi-eye-slash" style="position:absolute; right:18px; top:10px; cursor: pointer;"><i class="fa fa-eye-slash" style="right:0px; top:15px;" ></i></span>
        </div>
            <div class="forgot-link">
                <a href="#">Forget Password ?</a>
        </div>
        <input type="submit" name="login" value="Login" class="login-btn">
        <p>Or login with Social Platforms</p>
        <div class="social-icons">
            <a onclick="window.location='<?php echo $login_url?>'" href="#" aria-label="Sign in with Google"><i class="fa-brands fa-google" ></i></a>
             <a href="#" aria-label="Sign in with Facebook"><i class="fa-brands fa-facebook-f"></i></a>
              <a href="#" aria-label="Sign in with LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
        </div>
        <div id="auth-status"></div>
    </form>
</div>

    <!-- Signup FORM  -->

<div class="form-box signup">
    <form action="#" id="signup-form">
        <h1>Sign Up</h1>
        <div class="input-box">
            <input type="text" name="fname" placeholder="Your First Name" autocomplete="name" required/>
            <i class="fa-solid fa-user"></i>
        </div>
        <div class="input-box">
            <input type="text" name="lname" placeholder="Your Last Name" autocomplete="name" required/>
            <i class="fa-solid fa-user"></i>
        </div>
        <div class="input-box">
            <input type="email" name="email" placeholder="Enter your email address" autocomplete="email" required/>
            <i class="fa-solid fa-envelope"></i>
        </div>
        <div class="input-box">
            <input type="password" class="password" name="signuppassword" placeholder="Your Password" required/>
            <span class="togglePassword" class="bi-eye-slash" style="position:absolute; right:18px; top:10px; cursor: pointer;"><i class="fa fa-eye-slash" style="right:0px; top:15px;" ></i></span>
        </div>
        
        <input type="submit" name="signup" value="Sign Up" class="signup-btn">
        <p>Or Sign Up with Social Platforms</p>
        <div class="social-icons">
          <a href="#" aria-label="Sign in with Google"><i class="fa-brands fa-google" ></i></a>
             <a href="#" aria-label="Sign in with Facebook"><i class="fa-brands fa-facebook-f"></i></a>
              <a href="#" aria-label="Sign in with LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
       </div>
         <div id="auth-status"></div>
    </form>
</div>


    <!-- Toggle box    -->

 <div class="toggle-box">

    <!-- Toggle box left   -->

   <div class="toggle-panel toggle-left">
    <h1>Welcome Back!</h1>
    <p>Don't have an Account ?</p>
    <button class="btn signup-btns">Sign up</button>

   </div>

    <!-- Toggle box right   -->

   <div class="toggle-panel toggle-right">

    <h1>Hello, Welcome!</h1>
    <p>Already have an Account ?</p>
    <button class="btn login-btns">Log in</button>

    </div>
        </div>
            </div>
    <script src="../../assets/js/authentication.js"></script>
    <?php
    }
    ?>
    <script>
        const phpEndpoint = 'core/google_oauth_handler.php';
        // --- Form Submission Logic (AJAX/Fetch for local authentication) ---
    const statusDiv = document.getElementById('auth-status');

        // LOGIN HANDLING
     document.getElementById('login-form').addEventListener('submit', (e) => {
        e.preventDefault();
     // Dans une vraie application, vous feriez ici une requête AJAX pour valider l'email/mdp
     const form = e.target;
     // get data from the form inputs
     const email = form.querySelector('input[name="email"]').value;
     const password = form.querySelector('input[name="password"]').value;
    // update status message
     statusDiv.textContent = 'Authentication in progress...'; 
     statusDiv.style.color = 'blue';
    // perform AJAX POSTRequest using Fetch API
    fetch(phpEndpoint, {
        method: 'POST', 
        headers:{
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ // convert data to JSON stringify
            email: email,
            loginpassword: password

        })
    })
    .then(response => {
        // check if HTTP  status code indicates success (200)
        if(!response.ok){
            //throw an error to be caught below
            throw new Error('network or Server Error: ' + response.status);
        }
        //PArse the JSON response body
        return response.json();
    })
    .then(data => {
        // Handle the JSON response from the PHP endpoint

        if(data.success){
            //Authentication Successful
            statusDiv.textContent = data.message || 'Authentication Successful !';
            statusDiv.style.color = 'green';  
            
            //redirect to dashboard or home page
            window.location.href = data.redirect || '../../index.php';
        } else{
            // Authentication failed (e.g., wrong credentials)
            statusDiv.textContent = data.message || 'Authentication Failed. Please check your Informations.';
            statusDiv.style.color = 'red';
        
        }
    })
    .catch(error => {
        console.error('Erreur AJAX:', error);
        statusDiv.textContent = `Critical Error: ${error.message}`;
        statusDiv.style.color = 'red';
    })
    });

    // Sign up handling
    document.getElementById('signup-form').addEventListener('click', (e) => {
        e.preventDefault();
        // get data from the form inputs
        const password = document.getElementById('signup-password').value;
        const confirmpass = document.getElementById('confirm-password').value;
        const email = form.querySelector('input[name="email"]').value;
        const firstName = form.querySelector('input[name="fname"]').value;
        const lastName = form.querySelector('input[name="lname"]').value;
        
        if(empty(firstName) && empty(lastName) && empty(email) && empty(password)){
            statusDiv.textContent = 'Error: All fields are required';
            statusDiv.style.color = 'red';
            return;
        }
        if (strlen(password)<8){
            statusDiv.textContent = 'Error: Password must be atleast (8) characters';
            statusDiv.style.color = 'red';
            return;
        }
        statusDiv.textContent= 'Registration in progress...';
        statusDiv.style.color = 'blue';
        
        //Perform the AJAX POST request using Fetch API
        fetch(phpEndpoint,{
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                fname:firstName,
                lname: lastName,
                email: email,
                signuppassword:password
            })
        })
        .then(response => {
            // check for non-200 HTTP status codes
            if(!response.ok){
                throw new Error('Server or Network Error:' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if(data.success){
                // Registration successful!
            statusDiv.textContent = data.message || 'Registration Successful! Redirection...';
            statusDiv.style.color = 'green';
            
             // Redirect user
            window.location.href = data.redirect || '../../index.php'; 
            
            }else{
                 statusDiv.textContent = data.message || 'Registration Error. Please Try again';
                statusDiv.style.color = 'red';
            }
        })
        .catch(error =>{
            console.error('AJAX Error:', error);
        statusDiv.textContent = `Critical Error: ${error.message}`;
        statusDiv.style.color = 'red';
        });
    });

    </script>
    </body>
</html>
