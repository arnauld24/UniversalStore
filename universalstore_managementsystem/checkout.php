<?php

use internal_logic\Cart;
use core\utility;
ob_start(); // Start output buffering to prevent header errors

 // require config.php which holds BASE_PATH and BASE_URL
require_once __DIR__ . '/../config/config.php';
// Require the main file which handles SESSION and COOKIE management , LOGOUT,LOGGED_IN, VISITORS ETC
require_once INIT_PATH . '/init.php';
// Define the security constant if it hasn't been defined yet.
if (!defined('APP_INIT')) {
    error_log('Attempted direct access to secure utility file: ' . $_SERVER['REQUEST_URI']);
    http_response_code(403); 
    $redirect_url =  ERROR_URL . "?code=403&message= " . urlencode("Access Denied.");
    header("Location: $redirect_url");
    die('403 Access Denied.'); 
}

// Autoload and secure session helper (registers autoloader and starts session)
require_once SERVER_PATH . '/secure_session_csrf_cookie.php';

// User can only access checkout if he is authenticated(logged-In)
// check if user logged in 

if(empty($authenticatedUserId) && !isset($authenticatedUserId)){
    header("Location: " . AUTHENTICATION_URL);
    exit;
}

// Initialize utility class for helper functions
$utility = new utility();
// Get cart item from db for display

$cart = new Cart($user_id);

// Prepare display items and total using the Cart class
$display_items = $cart->getDisplayItems();
$display_total = $cart->calculateTotalCart();

// Attempt to compute an estimated delivery range for the current cart
$estimatedDeliveryRange = null;
try {
    // Determine shipping zone based on user's saved address or default (the init.php have already set/calculated $shipping_zone_id)
    
    $estimatedDeliveryRange = $cart->getEstimatedDeliveryRangeForOrder($display_items, $shipping_zone_id);
} catch (Throwable $e) {
    error_log('Failed to compute estimated delivery range on checkout page: ' . $e->getMessage());
}

// check if user has items in cart
if (!isset($display_items) || empty($display_items) || !is_array($display_items) || count($display_items) === 0) {
    // Debug: Log the redirect for testing
    error_log("Checkout access blocked: Empty cart detected. Redirecting to cart.php");
    // User has no items in cart, redirect to cart page with a message
    header("location: cart.php?message=empty_cart");
    exit;
}

//outputJavaScriptErrorHandler(); // This will output the JavaScript errors
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">

  <title>Checkout - Universal Store</title>

  <!-- bootstrap CDN -->
  <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/includes/bootstrap-5.3.7-dist/css/bootstrap.min.css">

  <!-- Font Awesome CDN -->
  <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/includes/fontawesome-free-5.15.4-web/css/all.css">
  <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/includes/fontawesome/fontawesome-free-6.7.2-web/css/all.css">

  <!-- custom css file -->
  <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style1.css">
  <link href="https://ik.imagekit.io/univ35tore/universal_store/imgs/UNIVERSAL%20STORE.jpg" rel="icon">

  <!-- JavaScript Error Logger -->
  <script src="<?php echo ASSETS_URL;?>/js/error-logger.js"></script>

</head>
<body class="checkout-page">

 <!-- checkout -->

<!--  Home Section   --->
<div class="checkout-nav">
    <a href="<?php echo universalstore_URL; ?>/index" class="brand-check">
          <img class="logo-ckeck" src="https://ik.imagekit.io/univ35tore/universal_store/imgs/UNIVERSAL%20STORE.jpg" alt="Universal Store logo" />
          <span class="font-baloo" >UNIVERSAL STORE</span>
    </a>

</div>
<div  class="cart-loading " style="display: none;"><div class="spinner"></div><p style="position:relative; top: 50px; left: -70px; font-weight: bold;">Processing....</p></div>

<section class="my-5 py-2 mx-5" style="position: relative;">
<a href="cart.php"><i class="fas fa-arrow-left"></i></a>
<div class="mt-3 pt-1 px-4">
    <h2 class="font-weight-800 ">CheckOut</h2>
    <hr class="mx-0">
</div>

<div class="row mt-0">
    <div id="checkoutAlert" class="container mt-5">
 <div class="checkout-success container" style="display: none;"></div>
<div class="checkout-error container" style="display: none;"></div>
            </div>

   <?php if(isset($delivery_info) && !empty($delivery_info)): ?>  
    <!--- Delivery address --->
     <div class="container col-lg-6 col-md-12 col-sm-12 py-4 mt-5 checkout-content ">
       <div class="d-flex justify-content-between">
        <p class="font-baloo font-size-20 font-weight-600">Delivery Address</p>
        <button type="button" data-bs-toggle="modal" data-bs-target="#deliveryAddress" data-bs-whatever="deliveryAddress">Modify</button>
       </div>     
       <p class="mt-2 font-baloo" id="del-name"><?php echo htmlspecialchars($del_fname . ' ' . $del_lname); ?></p>
       <p><?php echo $utility->decryptData($del_fname);?></p>
       <p class="mt-2 font-baloo" id="del-country"><?php echo htmlspecialchars($del_country); ?></p>
       <p class="mt-2 font-baloo" id="del-address"><?php echo htmlspecialchars($del_address); ?></p>
       <p class="mt-2 font-baloo" id="del-city"><?php echo htmlspecialchars($del_city); ?></p>
       <p class="mt-2 font-baloo" id="del-postcode"><?php echo htmlspecialchars($del_postcode); ?></p>
       <p class="mt-2 font-baloo" id="del-phone"><?php echo htmlspecialchars($del_phone); ?></p>
       <p class="mt-2 font-baloo" id="del-email"><?php echo htmlspecialchars($del_email); ?></p>

     </div>
        
   <?php else: ?>
    <div class="container col-lg-6 col-md-12 col-sm-12 py-4 mt-5 checkout-content ">
   <h4 class="font-baloo">Billing details</h4>

     <!-- Checkout Form -->

        <form method="post" action="<?php echo INTERNAL_LOGIC_URL . '/place_order.php'; ?>" id="checkout-form"  >
            <input type="hidden" name="csrf_token" id="csrf_token" value="<?php echo getCsrfToken(); ?>" />

            <div class="form-group group-name">
            <div class="input">
            <label for="checkout-fname">First name&nbsp;<abbr class="required" title="required">*</abbr> </label>
            <input type="text" class="form-control" name="fname" id="checkout-fname" placeholder="First Name" />
            </div>
            <div  class="input">
            <label for="checkout-lname">Last name&nbsp;<abbr class="required" title="required">*</abbr></label>
            <input type="text" class="form-control" name="lname" id="checkout-lname" placeholder="Last Name" />
            </div>
            </div>

            <div class="form-group">
             <label for="checkout-country">Country / Region&nbsp;<abbr class="required" title="required">*</abbr></label>
             <select name="country" class="form-control" autocomplete="country" id="checkout-country" >
               <option value="">--Please choose an option--</option>

      <!-- UN Member States -->
      <option value="AF">Afghanistan</option>
      <option value="AL">Albania</option>
      <option value="DZ">Algeria</option>
      <option value="AD">Andorra</option>
      <option value="AO">Angola</option>
      <option value="AG">Antigua and Barbuda</option>
      <option value="AR">Argentina</option>
      <option value="AM">Armenia</option>
      <option value="AU">Australia</option>
      <option value="AT">Austria</option>
      <option value="AZ">Azerbaijan</option>
      <option value="BS">Bahamas</option>
      <option value="BH">Bahrain</option>
      <option value="BD">Bangladesh</option>
      <option value="BB">Barbados</option>
      <option value="BY">Belarus</option>
      <option value="BE">Belgium</option>
      <option value="BZ">Belize</option>
      <option value="BJ">Benin</option>
      <option value="BT">Bhutan</option>
      <option value="BO">Bolivia</option>
      <option value="BA">Bosnia and Herzegovina</option>
      <option value="BW">Botswana</option>
      <option value="BR">Brazil</option>
      <option value="BN">Brunei</option>
      <option value="BG">Bulgaria</option>
      <option value="BF">Burkina Faso</option>
      <option value="BI">Burundi</option>
      <option value="CV">Cabo Verde</option>
      <option value="KH">Cambodia</option>
      <option value="CM">Cameroon</option>
      <option value="CA">Canada</option>
      <option value="CF">Central African Republic</option>
      <option value="TD">Chad</option>
      <option value="CL">Chile</option>
      <option value="CN">China</option>
      <option value="CO">Colombia</option>
      <option value="KM">Comoros</option>
      <option value="CD">Congo (DRC)</option>
      <option value="CG">Congo (Republic)</option>
      <option value="CR">Costa Rica</option>
      <option value="HR">Croatia</option>
      <option value="CU">Cuba</option>
      <option value="CY">Cyprus</option>
      <option value="CZ">Czech Republic</option>
      <option value="DK">Denmark</option>
      <option value="DJ">Djibouti</option>
      <option value="DM">Dominica</option>
      <option value="DO">Dominican Republic</option>
      <option value="EC">Ecuador</option>
      <option value="EG">Egypt</option>
      <option value="SV">El Salvador</option>
      <option value="GQ">Equatorial Guinea</option>
      <option value="ER">Eritrea</option>
      <option value="EE">Estonia</option>
      <option value="SZ">Eswatini</option>
      <option value="ET">Ethiopia</option>
      <option value="FJ">Fiji</option>
      <option value="FI">Finland</option>
      <option value="FR">France</option>
      <option value="GA">Gabon</option>
      <option value="GM">Gambia</option>
      <option value="GE">Georgia</option>
      <option value="DE">Germany</option>
      <option value="GH">Ghana</option>
      <option value="GR">Greece</option>
      <option value="GD">Grenada</option>
      <option value="GT">Guatemala</option>
      <option value="GN">Guinea</option>
      <option value="GW">Guinea-Bissau</option>
      <option value="GY">Guyana</option>
      <option value="HT">Haiti</option>
      <option value="HN">Honduras</option>
      <option value="HU">Hungary</option>
      <option value="IS">Iceland</option>
      <option value="IN">India</option>
      <option value="ID">Indonesia</option>
      <option value="IR">Iran</option>
      <option value="IQ">Iraq</option>
      <option value="IE">Ireland</option>
      <option value="IL">Israel</option>
      <option value="IT">Italy</option>
      <option value="JM">Jamaica</option>
      <option value="JP">Japan</option>
      <option value="JO">Jordan</option>
      <option value="KZ">Kazakhstan</option>
      <option value="KE">Kenya</option>
      <option value="KI">Kiribati</option>
      <option value="KR">South Korea</option>
      <option value="KW">Kuwait</option>
      <option value="KG">Kyrgyzstan</option>
      <option value="LA">Laos</option>
      <option value="LV">Latvia</option>
      <option value="LB">Lebanon</option>
      <option value="LS">Lesotho</option>
      <option value="LR">Liberia</option>
      <option value="LY">Libya</option>
      <option value="LI">Liechtenstein</option>
      <option value="LT">Lithuania</option>
      <option value="LU">Luxembourg</option>
      <option value="MG">Madagascar</option>
      <option value="MW">Malawi</option>
      <option value="MY">Malaysia</option>
      <option value="MV">Maldives</option>
      <option value="ML">Mali</option>
      <option value="MT">Malta</option>
      <option value="MH">Marshall Islands</option>
      <option value="MR">Mauritania</option>
      <option value="MU">Mauritius</option>
      <option value="MX">Mexico</option>
      <option value="FM">Micronesia</option>
      <option value="MD">Moldova</option>
      <option value="MC">Monaco</option>
      <option value="MN">Mongolia</option>
      <option value="ME">Montenegro</option>
      <option value="MA">Morocco</option>
      <option value="MZ">Mozambique</option>
      <option value="MM">Myanmar</option>
      <option value="NA">Namibia</option>
      <option value="NR">Nauru</option>
      <option value="NP">Nepal</option>
      <option value="NL">Netherlands</option>
      <option value="NZ">New Zealand</option>
      <option value="NI">Nicaragua</option>
      <option value="NE">Niger</option>
      <option value="NG">Nigeria</option>
      <option value="MK">North Macedonia</option>
      <option value="NO">Norway</option>
      <option value="OM">Oman</option>
      <option value="PK">Pakistan</option>
      <option value="PW">Palau</option>
      <option value="PA">Panama</option>
      <option value="PG">Papua New Guinea</option>
      <option value="PY">Paraguay</option>
      <option value="PE">Peru</option>
      <option value="PH">Philippines</option>
      <option value="PL">Poland</option>
      <option value="PT">Portugal</option>
      <option value="QA">Qatar</option>
      <option value="RO">Romania</option>
      <option value="RU">Russia</option>
      <option value="RW">Rwanda</option>
      <option value="KN">Saint Kitts and Nevis</option>
      <option value="LC">Saint Lucia</option>
      <option value="VC">Saint Vincent and the Grenadines</option>
      <option value="WS">Samoa</option>
      <option value="SM">San Marino</option>
      <option value="ST">Sao Tome and Principe</option>
      <option value="SA">Saudi Arabia</option>
      <option value="SN">Senegal</option>
      <option value="RS">Serbia</option>
      <option value="SC">Seychelles</option>
      <option value="SL">Sierra Leone</option>
      <option value="SG">Singapore</option>
      <option value="SK">Slovakia</option>
      <option value="SI">Slovenia</option>
      <option value="SB">Solomon Islands</option>
      <option value="SO">Somalia</option>
      <option value="ZA">South Africa</option>
      <option value="SS">South Sudan</option>
      <option value="ES">Spain</option>
      <option value="LK">Sri Lanka</option>
      <option value="SD">Sudan</option>
      <option value="SR">Suriname</option>
      <option value="SE">Sweden</option>
      <option value="CH">Switzerland</option>
      <option value="SY">Syria</option>
      <option value="TW">Taiwan</option> <!-- Partially recognized -->
      <option value="TJ">Tajikistan</option>
      <option value="TZ">Tanzania</option>
      <option value="TH">Thailand</option>
      <option value="TL">Timor-Leste</option>
      <option value="TG">Togo</option>
      <option value="TO">Tonga</option>
      <option value="TT">Trinidad and Tobago</option>
      <option value="TN">Tunisia</option>
      <option value="TR">Turkey</option>
      <option value="TM">Turkmenistan</option>
      <option value="TV">Tuvalu</option>
      <option value="UG">Uganda</option>
      <option value="UA">Ukraine</option>
      <option value="AE">United Arab Emirates</option>
      <option value="GB">United Kingdom</option>
      <option value="US">United States</option>
      <option value="UY">Uruguay</option>
      <option value="UZ">Uzbekistan</option>
      <option value="VU">Vanuatu</option>
      <option value="VA">Vatican City</option> <!-- Observer state -->
      <option value="VE">Venezuela</option>
      <option value="VN">Vietnam</option>
      <option value="EH">Western Sahara</option> <!-- Disputed territory -->
      <option value="YE">Yemen</option>
      <option value="ZM">Zambia</option>
      <option value="ZW">Zimbabwe</option>
             </select>
             </div>

            <div class="form-group">
            <label for="checkout-address">Street Address&nbsp;<abbr class="required" title="required">*</abbr></label>
            <input type="text" class="form-control" name="address" id="checkout-address" placeholder="House number and Street name" />
            </div>

            <div class="form-group">
            <label for="checkout-town">Town / City&nbsp;<abbr class="required" title="required">*</abbr></label>
            <input type="text" class="form-control" name="city" id="checkout-town" placeholder="House number and Street name" />
             </div>

            <div class="form-group">
            <label for="checkout-postcode">Postcode / ZIP&nbsp;<abbr class="required" title="required">*</abbr></label>
            <input type="number" class="form-control" name="postcode" id="checkout-postcode" />
             </div>

            <div class="form-group">
            <label for="checkout-phone">Phone&nbsp;<abbr class="required" title="required">*</abbr></label>
           <div class="phone">
            <span class="tel font-baloo">+237</span>
            <input type="tel" class="form-control" name="phone" id="checkout-phone" placeholder=" 6xx-xxx-xxx or 2xx-xxx-xxx" autocomplete="tel-country-code" pettern="^(?:6(?:5[5-9]|6[0-9]|7[0-9]|8[0-9]|9[0-9])|2(?:2[0-9]|3[0-9]))[0-9]{6}$" oninvalid="this.setCustomValidity('Please enter a valid Cameroonian phone number format (e.g., 6xx-xxx-xxx or 2xx-xxx-xxx).')"
           oninput="this.setCustomValidity('')"/>
            </div>
            </div>
            <div class="form-group">
            <label for="checkout-email">Email address&nbsp;<abbr class="required" title="required">*</abbr></label>
            <input type="email" class="form-control" name="email" id="checkout-email" autocomplete="email" placeholder="Ex: email@gmail.com"/>
            </div>

            <div class="form-group">
            <label for="checkout-addinfo" class="mt-3 color-black font-size-25">Additional information</label><br>
            <h5 class="font-baloo font-size-18 mt-2">Order notes (optional)</h5>
            <textarea name="addinfo" id="checkout-addinfo" class="form-control" placeholder="Notes about your order, e.g. Special notes for delivery" ></textarea>
            </div>

     </div>
    
<?php endif;?>

        <!-- Your Order details -->

     <div class="sub-order col-lg-5 col-md-12 col-sm-12 mt-5 py-3 ">
        <div class="mx-4 mt-2">
            <h3>Your Order</h3>
            <table>
             <tr>
                <th>Product</th>
                <th>Subtotal</th>
            </tr>
            <tr>

    <?php foreach($display_items as $key => $value ){?>
                <td><?php echo isset($value['product_name']) ? htmlspecialchars($value['product_name'],ENT_QUOTES, 'UTF-8') :'';?> <?php echo isset($value['product_color']) ? htmlspecialchars($value['product_color'],ENT_QUOTES, 'UTF-8') : ''; ?> <?php echo isset($value['product_variant']) ? htmlspecialchars($value['product_variant'],ENT_QUOTES, 'UTF-8') : ''; ?><span> x <strong><?php echo htmlspecialchars((int)$value['product_quantity'], ENT_QUOTES, 'UTF-8');?></strong> </span></td>
                <td><?php echo htmlspecialchars((int)$value['product_quantity'] * (float)$value['product_price'], ENT_QUOTES, 'UTF-8');?> FCFA</td>

            </tr>
            <?php }?>
            <tr>
                <td class="sub-total">Subtotal</td>
                <td class="sub-total"><?php echo isset($display_total) ? htmlspecialchars($display_total) . ' FCFA' : '0 FCFA'; ?></td>
             </tr>
             <tr>
                <td class="total">Total</td>
                <td class="total"><?php echo isset($display_total) ? htmlspecialchars($display_total) . ' FCFA' : '0 FCFA'; ?></td>
            </tr>

            <?php if (!empty($estimatedDeliveryRange) && is_array($estimatedDeliveryRange)): ?>
            <tr>
                <td class="estimated-delivery">Estimated delivery</td>
                <td class="estimated-delivery"><?php echo htmlspecialchars($estimatedDeliveryRange['display']); ?></td>
            </tr>
            <?php endif; ?>

            </table>
            <p >Your personal data will be used to process your order, support your experience
                 throughout this website, and for other purposes described in our general conditions of use.</p>
    <input type="submit" class="checkout-btn" value="Place order" name="place_order"/>

         </form>
          <!-- End of checkout form  -->

           </div>
              </div>
              
                  </div>
                <!------- DELIVERY ADDRESS MODAL ------>
<!--- Display Delivery address in cells on Modify for user who provided it--->
<?php if(!$delivery_info): ?>
 
<?php endif; ?>
       <div class="modal fade" id="deliveryAddress" tabindex="-1" aria-labelledby="deliveryAddressLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
         <!-- Error Message -->
<div id="checkoutAlert" class="container mt-5">
 <div class="checkout-success container" id="delAddress-success" style="display: none;"></div>
<div class="checkout-error container" id="delAddress-error" style="display: none;"></div>
 </div>
        <div class="modal-content rounded-4">
          <div class="modal-header">
            <h1 class="modal-title font-bold fs-5" id="deliveryAddressLabel"> Delivery Address </h1>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form id="deliveryAddressForm">
                <div><input type="hidden" name="csrf_Token" id="csrf_Token" value="<?php echo htmlspecialchars(getCsrfToken());?>"  /> </div>
              <div class="d-flex justify-content-between">
                <div class="mb-2">
                  <label for="checkout-fname" class="col-form-label font-semibold">First name&nbsp;<abbr class="required color-red" title="required">*</abbr></label>
                <input type="text" name="fname" value="<?php echo htmlspecialchars($del_fname ?? '')  ;?>" class="form-control border-gray-700 py-2 px-5" placeholder="First name" id="checkout-fname" />
              </div>
              <div class="mb-2">
                  <label for="checkout-lname" class="col-form-label font-semibold">Last name&nbsp;<abbr class="required color-red" title="required">*</abbr></label>
                <input type="text" name="lname" value="<?php echo htmlspecialchars($del_lname ?? '')  ;?>" class="form-control border-gray-700 py-2 px-5" placeholder="Last name" id="checkout-lname"/>
              </div>
              </div>
              <div class="form-group mb-2 font-semibold">
                  <label for="checkout-country" class="col-form-label font-semibold">Country&nbsp;<abbr class="required color-red" title="required">*</abbr></label>
                <select name="country" class="form-control border-gray-700 py-2" autocomplete="country" id="checkout-country">
                  <option value="<?php echo htmlspecialchars($del_country ?? '--Please choose your country--') ;?>"><?php echo htmlspecialchars($del_country ?? '--Please choose your country--') ;?></option>

                  <!-- UN Member States -->
                  <option value="AF">Afghanistan</option>
                  <option value="AL">Albania</option>
                  <option value="DZ">Algeria</option>
                  <option value="AD">Andorra</option>
                  <option value="AO">Angola</option>
                  <option value="AG">Antigua and Barbuda</option>
                  <option value="AR">Argentina</option>
                  <option value="AM">Armenia</option>
                  <option value="AU">Australia</option>
                  <option value="AT">Austria</option>
                  <option value="AZ">Azerbaijan</option>
                  <option value="BS">Bahamas</option>
                  <option value="BH">Bahrain</option>
                  <option value="BD">Bangladesh</option>
                  <option value="BB">Barbados</option>
                  <option value="BY">Belarus</option>
                  <option value="BE">Belgium</option>
                  <option value="BZ">Belize</option>
                  <option value="BJ">Benin</option>
                  <option value="BT">Bhutan</option>
                  <option value="BO">Bolivia</option>
                  <option value="BA">Bosnia and Herzegovina</option>
                  <option value="BW">Botswana</option>
                  <option value="BR">Brazil</option>
                  <option value="BN">Brunei</option>
                  <option value="BG">Bulgaria</option>
                  <option value="BF">Burkina Faso</option>
                  <option value="BI">Burundi</option>
                  <option value="CV">Cabo Verde</option>
                  <option value="KH">Cambodia</option>
                  <option value="Cameroon">Cameroon</option>
                  <option value="CA">Canada</option>
                  <option value="CF">Central African Republic</option>
                  <option value="TD">Chad</option>
                  <option value="CL">Chile</option>
                  <option value="CN">China</option>
                  <option value="CO">Colombia</option>
                  <option value="KM">Comoros</option>
                  <option value="CD">Congo (DRC)</option>
                  <option value="CG">Congo (Republic)</option>
                  <option value="CR">Costa Rica</option>
                  <option value="HR">Croatia</option>
                  <option value="CU">Cuba</option>
                  <option value="CY">Cyprus</option>
                  <option value="CZ">Czech Republic</option>
                  <option value="DK">Denmark</option>
                  <option value="DJ">Djibouti</option>
                  <option value="DM">Dominica</option>
                  <option value="DO">Dominican Republic</option>
                  <option value="EC">Ecuador</option>
                  <option value="EG">Egypt</option>
                  <option value="SV">El Salvador</option>
                  <option value="GQ">Equatorial Guinea</option>
                  <option value="ER">Eritrea</option>
                  <option value="EE">Estonia</option>
                  <option value="SZ">Eswatini</option>
                  <option value="ET">Ethiopia</option>
                  <option value="FJ">Fiji</option>
                  <option value="FI">Finland</option>
                  <option value="FR">France</option>
                  <option value="GA">Gabon</option>
                  <option value="GM">Gambia</option>
                  <option value="GE">Georgia</option>
                  <option value="DE">Germany</option>
                  <option value="GH">Ghana</option>
                  <option value="GR">Greece</option>
                  <option value="GD">Grenada</option>
                  <option value="GT">Guatemala</option>
                  <option value="GN">Guinea</option>
                  <option value="GW">Guinea-Bissau</option>
                  <option value="GY">Guyana</option>
                  <option value="HT">Haiti</option>
                  <option value="HN">Honduras</option>
                  <option value="HU">Hungary</option>
                  <option value="IS">Iceland</option>
                  <option value="IN">India</option>
                  <option value="ID">Indonesia</option>
                  <option value="IR">Iran</option>
                  <option value="IQ">Iraq</option>
                  <option value="IE">Ireland</option>
                  <option value="IL">Israel</option>
                  <option value="IT">Italy</option>
                  <option value="JM">Jamaica</option>
                  <option value="JP">Japan</option>
                  <option value="JO">Jordan</option>
                  <option value="KZ">Kazakhstan</option>
                  <option value="KE">Kenya</option>
                  <option value="KI">Kiribati</option>
                  <option value="KR">South Korea</option>
                  <option value="KW">Kuwait</option>
                  <option value="KG">Kyrgyzstan</option>
                  <option value="LA">Laos</option>
                  <option value="LV">Latvia</option>
                  <option value="LB">Lebanon</option>
                  <option value="LS">Lesotho</option>
                  <option value="LR">Liberia</option>
                  <option value="LY">Libya</option>
                  <option value="LI">Liechtenstein</option>
                  <option value="LT">Lithuania</option>
                  <option value="LU">Luxembourg</option>
                  <option value="MG">Madagascar</option>
                  <option value="MW">Malawi</option>
                  <option value="MY">Malaysia</option>
                  <option value="MV">Maldives</option>
                  <option value="ML">Mali</option>
                  <option value="MT">Malta</option>
                  <option value="MH">Marshall Islands</option>
                  <option value="MR">Mauritania</option>
                  <option value="MU">Mauritius</option>
                  <option value="MX">Mexico</option>
                  <option value="FM">Micronesia</option>
                  <option value="MD">Moldova</option>
                  <option value="MC">Monaco</option>
                  <option value="MN">Mongolia</option>
                  <option value="ME">Montenegro</option>
                  <option value="MA">Morocco</option>
                  <option value="MZ">Mozambique</option>
                  <option value="MM">Myanmar</option>
                  <option value="NA">Namibia</option>
                  <option value="NR">Nauru</option>
                  <option value="NP">Nepal</option>
                  <option value="NL">Netherlands</option>
                  <option value="NZ">New Zealand</option>
                  <option value="NI">Nicaragua</option>
                  <option value="NE">Niger</option>
                  <option value="NG">Nigeria</option>
                  <option value="MK">North Macedonia</option>
                  <option value="NO">Norway</option>
                  <option value="OM">Oman</option>
                  <option value="PK">Pakistan</option>
                  <option value="PW">Palau</option>
                  <option value="PA">Panama</option>
                  <option value="PG">Papua New Guinea</option>
                  <option value="PY">Paraguay</option>
                  <option value="PE">Peru</option>
                  <option value="PH">Philippines</option>
                  <option value="PL">Poland</option>
                  <option value="PT">Portugal</option>
                  <option value="QA">Qatar</option>
                  <option value="RO">Romania</option>
                  <option value="RU">Russia</option>
                  <option value="RW">Rwanda</option>
                  <option value="KN">Saint Kitts and Nevis</option>
                  <option value="LC">Saint Lucia</option>
                  <option value="VC">Saint Vincent and the Grenadines</option>
                  <option value="WS">Samoa</option>
                  <option value="SM">San Marino</option>
                  <option value="ST">Sao Tome and Principe</option>
                  <option value="SA">Saudi Arabia</option>
                  <option value="SN">Senegal</option>
                  <option value="RS">Serbia</option>
                  <option value="SC">Seychelles</option>
                  <option value="SL">Sierra Leone</option>
                  <option value="SG">Singapore</option>
                  <option value="SK">Slovakia</option>
                  <option value="SI">Slovenia</option>
                  <option value="SB">Solomon Islands</option>
                  <option value="SO">Somalia</option>
                  <option value="ZA">South Africa</option>
                  <option value="SS">South Sudan</option>
                  <option value="ES">Spain</option>
                  <option value="LK">Sri Lanka</option>
                  <option value="SD">Sudan</option>
                  <option value="SR">Suriname</option>
                  <option value="SE">Sweden</option>
                  <option value="CH">Switzerland</option>
                  <option value="SY">Syria</option>
                  <option value="TW">Taiwan</option>
                  <!-- Partially recognized -->
                  <option value="TJ">Tajikistan</option>
                  <option value="TZ">Tanzania</option>
                  <option value="TH">Thailand</option>
                  <option value="TL">Timor-Leste</option>
                  <option value="TG">Togo</option>
                  <option value="TO">Tonga</option>
                  <option value="TT">Trinidad and Tobago</option>
                  <option value="TN">Tunisia</option>
                  <option value="TR">Turkey</option>
                  <option value="TM">Turkmenistan</option>
                  <option value="TV">Tuvalu</option>
                  <option value="UG">Uganda</option>
                  <option value="UA">Ukraine</option>
                  <option value="AE">United Arab Emirates</option>
                  <option value="GB">United Kingdom</option>
                  <option value="US">United States</option>
                  <option value="UY">Uruguay</option>
                  <option value="UZ">Uzbekistan</option>
                  <option value="VU">Vanuatu</option>
                  <option value="VA">Vatican City</option>
                  <!-- Observer state -->
                  <option value="VE">Venezuela</option>
                  <option value="VN">Vietnam</option>
                  <option value="EH">Western Sahara</option>
                  <!-- Disputed territory -->
                  <option value="YE">Yemen</option>
                  <option value="ZM">Zambia</option>
                  <option value="ZW">Zimbabwe</option>
                </select>
              </div>
              <div class="mb-2">
                  <label for="checkout-postcode" class="col-form-label font-semibold">Postcode&nbsp;<abbr class="required color-red" title="required">*</abbr></label>
                <input type="text" name="postcode" value="<?php echo htmlspecialchars($del_postcode ?? '')  ;?>"class="form-control border-gray-700 py-2" placeholder="Postcode" id="checkout-postcode" />
              </div>
              <div class="mb-2">
                  <label for="checkout-address" class="col-form-label font-semibold">Street Address&nbsp;<abbr class="required color-red" title="required">*</abbr></label>
                <input type="text" name="address" value="<?php echo htmlspecialchars($del_address ?? '') ; ?>" class="form-control border-gray-700 py-2" placeholder="House number and Street name (Street Address)" id="checkout-address" />
              </div>
              <div class="mb-3">
                  <label for="checkout-city" class="col-form-label font-semibold">City / Town&nbsp;<abbr class="required color-red" title="required">*</abbr></label>
                <input type="text" name="city" value="<?php echo htmlspecialchars($del_city ?? '')  ; ?>" class="form-control border-gray-700 py-2" placeholder="Enter Town / City" id="checkout-city" />
              </div>
              <div class="form-group mb-2">
            <label for="checkout-phone">Phone&nbsp;<abbr class="required color-red" title="required">*</abbr></label>
           <div class="phone">
            <span class="tel font-baloo">+237</span>
            <input type="tel" class="form-control" name="phone" id="checkout-phone" placeholder=" 6xx-xxx-xxx or 2xx-xxx-xxx" value="<?php echo htmlspecialchars($del_phone ?? '')  ; ?>" autocomplete="tel-country-code" pettern="^(?:6(?:5[5-9]|6[0-9]|7[0-9]|8[0-9]|9[0-9])|2(?:2[0-9]|3[0-9]))[0-9]{6}$" oninvalid="this.setCustomValidity('Please enter a valid Cameroonian phone number format (e.g., 6xx-xxx-xxx or 2xx-xxx-xxx).')"
           oninput="this.setCustomValidity('')"/>
            </div>
            </div>
            <div class="form-group mb-4">
            <label for="checkout-email">Email address&nbsp;<abbr class="required color-red" title="required">*</abbr></label>
            <input type="email" class="form-control" name="email" id="checkout-email" value="<?php echo htmlspecialchars(trim($del_email, '+237') ?? '')  ; ?>" autocomplete="email" placeholder="Ex: email@gmail.com"/>
            </div>

               <p class="text-muted small">We just need it for shipping your products and shipping rates calculation </p>
            </form>
          </div>
          <div class="modal-footer">
            <!-- <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"> Close </button> -->
            <button type="submit" class="btn save-btn btn-primary" data-form-id="deliveryAddressForm">Save</button>
          </div>
        </div>
      </div>
    </div>
</section>
<script src="<?php echo ASSETS_URL; ?>/includes/bootstrap-5.3.7-dist/js/bootstrap.bundle.min.js"></script>

<script>
    const internal_logic = '<?php echo INTERNAL_LOGIC_URL ?>';

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('checkout-form');
    const successDiv = document.querySelector('.checkout-success');
    const errorDiv = document.querySelector('.checkout-error');
    const placeOrderBtn = document.querySelector('.checkout-btn');

    function clearMessages() {
        successDiv.style.display = 'none';
        errorDiv.style.display = 'none';
        successDiv.textContent = '';
        errorDiv.textContent = '';
        placeOrderBtn.value = 'Place Order';
    }
    function updateCsrfToken(newToken){
        const csrfToken = document.getElementById('csrf_token');
        if (csrfToken) {
            csrfToken.value = newToken;
        console.log("CSRF Token updated succesfully.  // Note this is not an error, it was suppossed to be logged but put in error_type because logging function not set here");
    }else {
        console.error("CSRF Token update failed or not found");
    }
    }
        // show loading on website reload 
    const cartload = document.getElementsByClassName('cart-loading')[0];
    window.scrollTo({top:0, behavior:'smooth'});
    cartload.style.display = 'flex';

    setTimeout(() => cartload.style.display = 'none', 1000); // hide after 1s 
    // we define it here to use it in validateInput function
     const csrfToken = document.getElementById('csrf_token');
     const currentCsrfToken = csrfToken ? csrfToken.value : '';
    function validateInput() {
        clearMessages();
        const fname = form.fname.value.trim();
        const lname = form.lname.value.trim();
        const country = form.country.value.trim();
        const address = form.address.value.trim();
        const city = form.city.value.trim();
        const postcode = form.postcode.value.trim();
        const phone = form.phone.value.trim();
        const email = form.email.value.trim();
        const addinfo = form.addinfo.value.trim();

        const patternAlphabetic = /^[a-zA-Z\s]+$/;
        const patternNumeric = /^[0-9]+$/;
        const patternAlphanumeric = /^[a-zA-Z0-9.,\/\s]+$/;
        const patternPhone = /^(?:6(?:5[5-9]|6[0-9]|7[0-9]|8[0-9]|9[0-9])|2(?:2[0-9]|3[0-9]))[0-9]{6}$/;
        const patternEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

         window.scrollTo({top:0, behavior:'smooth'});     
         cartload.style.display = 'flex';
         setTimeout(() => cartload.style.display = 'none', 1000); // hide after 1s 
        
         let originalBtn = placeOrderBtn.value;
         placeOrderBtn.disabled = true;
         placeOrderBtn.value = 'Placing Order....';
         // check csrf-token is present
         if(!currentCsrfToken){
            errorDiv.textContent = 'Session expired. Please refresh the page and try again.';
            errorDiv.style.display = 'block';
              window.scrollTo({
                top: 0,
                behavior: 'smooth'
        });
            placeOrderBtn.disabled = false;
            placeOrderBtn.value = 'Place Order';
            return false;
         }
        if (!fname || !patternAlphabetic.test(fname)) {
            errorDiv.textContent = '"First name" is required and must contain only alphabetic characters.';
            errorDiv.style.display = 'block';
              window.scrollTo({
                top: 0,
                behavior: 'smooth'
        });
            placeOrderBtn.disabled = false;
            placeOrderBtn.value = 'Place Order';
            return false;
           
        }
        if (!lname || !patternAlphabetic.test(lname)) {
            errorDiv.textContent = '"Last name"  is required and must contain only alphabetic characters.';
            errorDiv.style.display = 'block';
              window.scrollTo({
                top: 0,
                behavior: 'smooth'
        });
            placeOrderBtn.disabled = false;
            placeOrderBtn.value = 'Place Order';
            return false;
        }
        if (!country || !patternAlphabetic.test(country)) {
            errorDiv.textContent = '"Country"  is required and must contain only alphabetic characters.';
            errorDiv.style.display = 'block';
              window.scrollTo({
                top: 0,
                behavior: 'smooth'
        });
            placeOrderBtn.disabled = false;
            placeOrderBtn.value = 'Place Order';
            return false;
        }
        if (!address || !patternAlphanumeric.test(address)) {
            errorDiv.textContent = '"Address"  is required and must contain only alphanumeric characters.';
            errorDiv.style.display = 'block';
              window.scrollTo({
                top: 0,
                behavior: 'smooth'
        });
             placeOrderBtn.disabled = false;
            placeOrderBtn.value = 'Place Order';
            return false;
        }
        if (!city || !patternAlphabetic.test(city)) {
            errorDiv.textContent = '"City" is required and must contain only alphabetic characters.';
            errorDiv.style.display = 'block';
              window.scrollTo({
                top: 0,
                behavior: 'smooth'
        });
            placeOrderBtn.disabled = false;
            placeOrderBtn.value = 'Place Order';
            return false;
        }
        if (!postcode || !patternNumeric.test(postcode)) {
            errorDiv.textContent = '"Postcode" is required and must contain only numeric characters.';
            errorDiv.style.display = 'block';
              window.scrollTo({
                top: 0,
                behavior: 'smooth'
        });
            placeOrderBtn.disabled = false;
            placeOrderBtn.value = 'Place Order';
            return false;
        }
        /*
        if (!phone || !patternPhone.test(phone)) {
            errorDiv.textContent = '"Phone" is required and must be a valid Cameroonian phone number.';
            errorDiv.style.display = 'block';
              window.scrollTo({
                top: 0,
                behavior: 'smooth'
        });
            return false;
        }
            */
        if (email && !patternEmail.test(email)) {
            errorDiv.textContent = '"Email"  must be a valid email address.';
            errorDiv.style.display = 'block';
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
        });
            placeOrderBtn.disabled = false;
            placeOrderBtn.value = 'Place Order';
            return false;
        }
        if (addinfo && !patternAlphanumeric.test(addinfo)) {
            errorDiv.textContent = '"Additional information"  must contain only alphanumeric characters.';
            errorDiv.style.display = 'block';
              window.scrollTo({
                top: 0,
                behavior: 'smooth'
        });
            placeOrderBtn.disabled = false;
            placeOrderBtn.value = 'Place Order';
            return false;
        }
        return true;
    }

    // Centralized submit handler used for form submit or button click
    function handlePlaceOrderEvent(e) {
        e.preventDefault();
        clearMessages();

        console.log('Form submission intercepted');

        // If the billing form exists, validate it. Otherwise skip validation
        const formExists = !!form;
        if (formExists) {
            if (!validateInput()) {
                console.log('Client-side validation failed');
                 placeOrderBtn.disabled = false;
                 placeOrderBtn.value = 'Place Order';
                return;
            }
        }
         placeOrderBtn.disabled = true;
         placeOrderBtn.value = 'Placing Order....';
        // Build FormData: use the form if present, otherwise create one manually
        let formData;
        if (formExists) {
             window.scrollTo({top:0, behavior:'smooth'});     
         cartload.style.display = 'flex';
            formData = new FormData(form);
            formData.append('ajax', '1');
            formData.append('place_order', '1'); // Always ensure place_order flag is present
        } else {
             window.scrollTo({top:0, behavior:'smooth'});     
         cartload.style.display = 'flex';
            formData = new FormData();
            formData.append('ajax', '1');
            // Ensure place_order flag is present so backend recognizes the request
            formData.append('place_order', '1');
            // Add CSRF token from meta tag or hidden input if available
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfVal = csrfMeta ? csrfMeta.getAttribute('content') : (document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '');
            if (csrfVal) formData.append('csrf_token', csrfVal);
        }

        // Check if delivery address is displayed (user has saved delivery info)
        const deliveryAddressSection = document.querySelector('.checkout-content');
        const delNameEl = document.getElementById('del-name');
        window.scrollTo({top:0, behavior:'smooth'});     
         cartload.style.display = 'flex';
        
        // If delivery address section exists and has content, extract from DOM
        if (deliveryAddressSection && delNameEl && delNameEl.textContent.trim()) {
            console.log('Delivery address found in display - extracting from DOM');

            // Extract delivery address from display elements
            const delCountryEl = document.getElementById('del-country');
            const delAddressEl = document.getElementById('del-address');
            const delCityEl = document.getElementById('del-city');
            const delPostcodeEl = document.getElementById('del-postcode');
            const delPhoneEl = document.getElementById('del-phone');
            const delEmailEl = document.getElementById('del-email');

            // Parse name (split into fname and lname)
            const fullName = delNameEl.textContent.trim();
            const nameParts = fullName.split(' ');
            const del_fname = nameParts[0] || '';
            const del_lname = nameParts.slice(1).join(' ') || '';

            // Extract other fields
            const del_country = delCountryEl ? delCountryEl.textContent.trim() : '';
            const del_address = delAddressEl ? delAddressEl.textContent.trim() : '';
            const del_city = delCityEl ? delCityEl.textContent.trim() : '';
            const del_postcode = delPostcodeEl ? delPostcodeEl.textContent.trim() : '';
            const del_phone = delPhoneEl ? delPhoneEl.textContent.trim() : '';
            let phone = del_phone;
            if(phone.startsWith('+237')){
                phone = phone.substring(4).trim();
            }
            const del_email = delEmailEl ? delEmailEl.textContent.trim() : '';
            
            // Append delivery address data to formData
            formData.append('del_fname', del_fname);
            formData.append('del_lname', del_lname);
            formData.append('del_country', del_country);
            formData.append('del_address', del_address);
            formData.append('del_city', del_city);
            formData.append('del_postcode', del_postcode);
            formData.append('del_phone', phone);
            formData.append('del_email', del_email);

            console.log('Delivery address extracted:', {
                del_fname, del_lname, del_country, del_address, del_city, del_postcode, del_phone, del_email
            });
        } else {
            console.log('No delivery address displayed - using billing form data');
        }

        // Determine CSRF header value (prefer currentCsrfToken if set, otherwise use meta)
        const metaCsrf = document.querySelector('meta[name="csrf-token"]');
        const csrfHeader = (typeof currentCsrfToken !== 'undefined' && currentCsrfToken) ? currentCsrfToken : (metaCsrf ? metaCsrf.getAttribute('content') : '');

        fetch(internal_logic + '/place_order.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfHeader
            }
        })
        .then(response => {
            if (!response.ok) {
                setTimeout(() => {  cartload.style.display = 'none'}, 200);
                throw new Error('Network response was not ok: ' + response.status + ' ' + response.statusText);
             placeOrderBtn.disabled = false;
             placeOrderBtn.value = 'Place Order';
            }
            return response.text();
        })
        .then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                setTimeout(() => {  cartload.style.display = 'none'}, 200);
                console.error('Failed to parse JSON response:', text);
                throw new Error('Server returned invalid JSON: ' + text.substring(0, 100));
            }
        })
        .then(data => {
            setTimeout(() => {  cartload.style.display = 'none'}, 200);
            console.log('Server response:', data);
            clearMessages();
            if(data.csrfToken){
            if(data.csrfToken === currentCsrfToken){
                errorDiv.textContent = 'Session expired. Please refresh the page and try again.';
                errorDiv.style.display = 'block';
                 placeOrderBtn.disabled = false;
                 placeOrderBtn.value = 'Place Order';
                return;
            }
            updateCsrfToken(data.csrfToken);
        }
            // Ensure data object has expected properties
            if (typeof data !== 'object' || data === null) {
                throw new Error('Invalid response structure');
            }
            
            if (data.success === true) {
                let successMsg = data.message || 'Order placed successfully!';
                // Append estimated delivery dates if available
                if (data.estimated_delivery_display) {
                    successMsg += ' Estimated delivery: ' + data.estimated_delivery_display;
                }
                successDiv.textContent = successMsg;
                successDiv.style.display = 'block';
                errorDiv.style.display = 'none';
                console.log('Order placed successfully');
                window.scrollTo({ top: 0, behavior: 'smooth' });
                 setTimeout(() => {
                     window.location.href = '<?php echo universalstore_URL; ?>/payment.php?order_id=' + encodeURIComponent(data.order_id);
                 }, 1500);
            } else if (data.success === false) {
                errorDiv.textContent = data.message || 'An error occurred during order placement.';
                errorDiv.style.display = 'block';
                successDiv.style.display = 'none';
                 placeOrderBtn.disabled = false;
                 placeOrderBtn.value = 'Place Order';
                console.error('Order placement failed:', data.message);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                throw new Error('Unexpected response: no success field');
            }
        })
        .catch(error => {
             setTimeout(() => {  cartload.style.display = 'none'}, 200);
            console.error('Fetch/processing error:', error);
            clearMessages();
            errorDiv.textContent = 'An error occurred: ' + error.message;
            errorDiv.style.display = 'block';
            successDiv.style.display = 'none';
            placeOrderBtn.disabled = false;
            placeOrderBtn.value = 'Place Order';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // Attach handler: use form submit if form exists, otherwise attach to Place Order button
    if (form) {
        form.addEventListener('submit', handlePlaceOrderEvent);
    } else {
        const placeBtn = document.querySelector('input[name="place_order"]');
        if (placeBtn) {
            placeBtn.addEventListener('click', handlePlaceOrderEvent);
        } else {
            // Last-resort: delegate clicks for any element named place_order
            document.addEventListener('click', function(e) {
                if (e.target && e.target.name === 'place_order') {
                    handlePlaceOrderEvent(e);
                }
            });
        }
    }
});
</script>

<!--- Update Delivery info AJAX/JSON ----->
<script>
// Prefer modal-specific messages, fall back to page-level checkout alerts if modal alerts are absent
const successMsg = document.getElementById('delAddress-success') || document.querySelector('.checkout-success');
const errorMsg = document.getElementById('delAddress-error') || document.querySelector('.checkout-error');
function updateCsrfToken(newToken){
        const csrf_Token = document.getElementById('csrf_Token');
        if (csrf_Token) {
            csrf_Token.value = newToken;
        console.log("CSRF Token updated succesfully.  // Note this is not an error, it was suppossed to be logged but put in error_type because logging function not set here");
    }else {
        console.error("CSRF Token update failed or not found");
    }
    }

    document.addEventListener('DOMContentLoaded', function() {

        const saveButton = document.querySelector('.save-btn');

        if(!saveButton){
            console.error('Save button not found');
            return;
        }
        saveButton.addEventListener('click', (e) => {

        e.preventDefault();
        const form = document.getElementById('deliveryAddressForm');

        if (!form) {
            console.error('Delivery address form not found');
            if (errorMsg) { errorMsg.textContent = 'Form not found. Please try again.'; errorMsg.style.display = 'block'; }
            return;
        }

        const email = form.querySelector('input[name="email"]');
        const phone = form.querySelector('input[name="phone"]');
        const fname = form.querySelector('input[name="fname"]');
        const lname = form.querySelector('input[name="lname"]');
        const country = form.querySelector('select[name="country"]');
        const address = form.querySelector('input[name="address"]');
        const city = form.querySelector('input[name="city"]');
        const postcode = form.querySelector('input[name="postcode"]');
        const csrf_Token = form.querySelector('input[name="csrf_Token"]');

        // Check if all required fields exist
        if (!email || !phone || !fname || !lname || !country || !address || !city || !postcode || !csrf_Token) {
            console.error('One or more required form fields not found');
            if (errorMsg) { errorMsg.textContent = 'Form fields missing. Please refresh the page and try again.'; errorMsg.style.display = 'block'; }
            return;
        }

        const emailVal = email.value.trim();
        const phoneVal = phone.value.trim();
        const fnameVal = fname.value.trim();
        const lnameVal = lname.value.trim();
        const countryVal = country.value.trim();
        const addressVal = address.value.trim();
        const cityVal = city.value.trim();
        const postcodeVal = postcode.value.trim();
        const currentCsrfToken = csrf_Token.value;
        const randomId = crypto.randomUUID();

        if(!currentCsrfToken){
            if (errorMsg) { errorMsg.textContent = 'Session expired. Please refresh the page and try again.'; errorMsg.style.display = 'block'; }
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }
        
        const formData = new FormData();
        formData.append('email', emailVal);
        formData.append('phone', phoneVal);
        formData.append('fname', fnameVal);
        formData.append('lname', lnameVal);
        formData.append('country', countryVal);
        formData.append('address', addressVal);
        formData.append('city', cityVal);
        formData.append('postcode', postcodeVal);
        formData.append('csrf_token', currentCsrfToken);
        formData.append('ajax', '1');
        formData.append('delivery_id', randomId);

        fetch(internal_logic + '/profile_logic.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token' : currentCsrfToken
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status + ' ' + response.statusText);
            }
            return response.text();
        })
        .then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse JSON response:', text);
                throw new Error('Server returned invalid JSON: ' + text.substring(0, 100));
            }
        })
        .then(data => {
            console.log('Server response:', data);
            // Update CSRF token if server provided a new one
            if (data.csrf_token && typeof data.csrf_token === 'string') {
                updateCsrfToken(data.csrf_token);
                console.log('CSRF token updated from server response');
            }

            if (data.success === true) {
                if (successMsg) { successMsg.textContent = data.message || 'Delivery address updated successfully!'; successMsg.style.display = 'block'; }
                if (errorMsg) { errorMsg.style.display = 'none'; }
                console.log('Delivery address updated successfully');
                const modalElement = document.getElementById('deliveryAddress');
                const modal = bootstrap.Modal.getInstance(modalElement);
                modal.hide();
                window.scrollTo({ top: 0, behavior: 'smooth' });
                   // Reload page after a short delay to apply changes
                    setTimeout(() => location.reload(), 200);
            } else if (data.success === false) {
                if (errorMsg) { errorMsg.textContent = data.message || 'An error occurred while updating delivery address.'; errorMsg.style.display = 'block'; }
                if (successMsg) { successMsg.style.display = 'none'; }
                console.error('Delivery address update failed:', data.message);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                throw new Error('Unexpected response: no success field');
            }
    })
        .catch(error => {
            console.error('Fetch/processing error:', error);
            errorMsg.textContent = 'An error occurred: ' + error.message;
            errorMsg.style.display = 'block';
            successMsg.style.display = 'none';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
});
</script>
</html>
