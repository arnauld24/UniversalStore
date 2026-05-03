<?php 

// INITIALIZATION & SECURITY
require_once __DIR__ . '/../../config/config.php';
require_once INIT_PATH . '/init.php';


// Security check to prevent direct file access.
if (!defined('APP_INIT')) {
    error_log('Attempted direct access to secure utility file: ' . $_SERVER['REQUEST_URI']);
    http_response_code(403); 
    $redirect_url =  ERROR_URL . "?code=403&message= " . urlencode("Access Denied.");
    header("Location: $redirect_url");
    exit('403 Access Denied.'); 
}

// Load session manager and Composer autoloader.
require_once SERVER_PATH. '/secure_session_csrf_cookie.php';

use core\Controller;
use internal_logic\Cart;
use external_server\Connect;
use internal_logic\OrderDisplayLogic;
use core\utility;

// UTILITY 
$utility_for_data_decryption = new utility();

// AUTHENTICATION & AUTHORIZATION

$authenticatedUserId = Controller::getAuthenticatedUserId();

if (!$authenticatedUserId) {
    // Capture the current URL to redirect back after login.
    $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $_SESSION['redirect_after_oauth'] = $current_url;

    header("Location: " . AUTHENTICATION_URL);
    exit;
 }

// NEW: Instantiate the OrderDisplay class, passing the authenticated user ID

$orderDisplay = new OrderDisplayLogic($authenticatedUserId);

//call function to retieve orders from database

$display_order_details = $orderDisplay->getOrderDetails();

//Function slug for clean url display
function createSlug($string){
  $string = strtolower($string); // Convert to lowercase
  $string = preg_replace('/[^a-z0-9]+/i', '-', $string); // Replace non-alphanumeric characters with hyphens
  $string = preg_replace('/^-+|-+$/', '', $string); // Remove leading and trailing hyphens
  return rtrim($string, '-'); // Remove any trailing hyphens
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
     <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Advanced Dashboard">
    <meta name="theme-color" content="#000000">
    <title>My Orders - Advanced Dashboard</title>
    <link href="<?php echo ASSETS_URL; ?>/css/tailwindcss.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
     /* Mobile-First Responsive Design */
    @media (max-width: 640px) {
        body { padding: 0; }
        main { padding: 0; }
    }
    .order-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .details-panel { 
        max-height: 0; 
        overflow: hidden; 
        transition: max-height 0.4s ease-out, opacity 0.3s ease; 
        opacity: 0;
    }
    .details-panel.open { 
        max-height: 2000px; 
        opacity: 1; 
        border-top: 1px solid #e2e8f0;
        padding-top: 1.5rem;
        margin-top: 1.5rem;
    }
    .rotate-icon { transition: transform 0.3s ease; }
    .rotate-icon.active { transform: rotate(180deg); }
    .bg-color-light-red {
        background: #ffb1b1f8;
    }
    .step-line { position: relative; }
    .step-line::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 0;
        right: 0;
        height: 2px;
        background: #e2e8f0;
        z-index: 0;
    }
    .step-dot { z-index: 1; position: relative; }
    .timeline-step { position: relative; }
    .timeline-step:not(:last-child)::after {
        content: '';
        position: absolute;
        left: 20px;
        top: 55px;
        bottom: -20px;
        width: 2px;
        background: #e2e8f0;
    }
    .active-step::after { background: #3b82f6 !important; }
    .no-underline{
        text-decoration: none;
    }
    .btn-pay{
        border-radius: 5px;
    }
    .arrow-btn{
         border-radius: 20px;
    }
    </style>
</head>
<body class="p-4 md:p-8">

    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 mt-4">Order History</h1>
                <p class="text-slate-500">Track and manage your recent purchases</p>
            </div>
            <!-- Search & Filter -->
            <div class="flex items-center gap-2">
                <div class="relative">
                    <input type="text" id="searchOrders" placeholder="Search ID, product, status..." class="pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-40 md:w-64 shadow-sm">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                </div>
                 <!-- compute order status -->
                <button class="p-2 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 shadow-sm" onclick="resetSearch()">
                    <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                </button>
            </div>
        </div>
   <?php foreach($display_order_details as $value){ 
        $order_id = isset($value['order_id']) ? htmlspecialchars($value['order_id'], ENT_QUOTES, 'UTF-8'): '';
        $date = isset($value['order_date']) ? htmlspecialchars($value['order_date'], ENT_QUOTES, 'UTF-8'): '';
        $date_format = strtotime($date);
        $formatted_date = date('M d, Y', $date_format) . ' at ' . date('H:i:s', $date_format);
        $raw_delivery_start = isset($value['estimated_delivery_start_date']) ? htmlspecialchars($value['estimated_delivery_start_date'], ENT_QUOTES, 'UTF-8'): '';
        $raw_delivery_end = isset($value['estimated_delivery_end_date']) ? htmlspecialchars($value['estimated_delivery_end_date'], ENT_QUOTES, 'UTF-8'): '';
        $delivery_start = date('M d, Y', strtotime($raw_delivery_start));
        $delivery_end = date('M d, Y', strtotime($raw_delivery_end));

        $payment_id = isset($value['payment_id']) ? htmlspecialchars($value['payment_id'], ENT_QUOTES, 'UTF-8'): '';
        $total_items = 0;
        if (!empty($value['products']) && is_array($value['products'])) {
            $total_items = array_sum(array_column($value['products'], 'product_quantity'));
        }
        $total_items = htmlspecialchars((string)$total_items, ENT_QUOTES, 'UTF-8');
        $raw_cost = isset($value['order_cost']) ?$value['order_cost']: '0';
        $format_cost = number_format((float)$raw_cost,0,'.',',');
        $order_cost = htmlspecialchars($format_cost,ENT_QUOTES,'UTF-8');
        $order_status = isset($value['order_status']) ? htmlspecialchars($value['order_status'],ENT_QUOTES, 'UTF-8'): '';
        $order_email = isset($value['user_email']) ? htmlspecialchars($value['user_email'], ENT_QUOTES, 'UTF-8') : '';
        $order_fname = isset($value['user_fname']) ? htmlspecialchars($value['user_fname'], ENT_QUOTES, 'UTF-8') : '';
        $order_lname = isset($value['user_lname']) ? htmlspecialchars($value['user_lname'], ENT_QUOTES, 'UTF-8') : '';
       
       ?>
        <!-- Orders Container -->
        <div id="orders-list" class="space-y-6">
            
            <!-- Order Card 1: Processing Example -->
            <div class="order-card bg-white rounded-2xl shadow-sm border border-slate-200 p-4 md:p-6 hover:shadow-md" data-order-id="<?php echo htmlspecialchars($value['order_id'], ENT_QUOTES, 'UTF-8'); ?>" data-order-status="<?php echo htmlspecialchars($value['order_status'], ENT_QUOTES, 'UTF-8'); ?>" data-search-content="<?php echo htmlspecialchars(substr($value['order_id'], 0, 15) . ' ' . $order_status . ' ' . implode(' ', array_map(function($p) { return $p['product_name'] ?? ''; }, $value['products'] ?? [])), ENT_QUOTES, 'UTF-8'); ?>">
                 <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                         <?php if($order_status === 'on_hold'):?>
                        <div class="bg-blue-50 p-3 rounded-xl">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                        </div>
                        <?php elseif($order_status === 'processing'):?>
                        <div class="bg-green-100 p-3 rounded-xl">
                            <svg class="w-6 h-6 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                        </div>
                        <?php elseif($order_status === 'shipped'):?>
                            <div class="bg-amber-100 p-3 rounded-xl">
                                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 8H3m2 3H3m2 3H3"></path>
                                </svg>
                            </div>
                        <?php elseif($order_status === 'delivered'):?>
                        <div class="bg-green-500 p-3 rounded-xl">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        </div>
                         <?php else:?>
                        <div class="bg-color-light-red p-3 rounded-xl">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </div>
                        <?php endif;?>
                        <div>
                            <h3 class="font-semibold text-slate-800 tracking-tight"><?php echo isset($value['order_id']) ? htmlspecialchars(substr($value['order_id'],0,15), ENT_QUOTES, 'UTF-8'): ''; ?></h3>
                            <p class="text-sm text-slate-400"><?php echo 'Placed on ' . $formatted_date; ?> <?php echo ' • '.$total_items . ' Items'; ?></p>
                            <p class="text-sm text-slate-400"><?php echo 'Estimated Delivery: ' . $delivery_start . ' - ' . $delivery_end; ?></p>
                            
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <div class="text-right hidden sm:block">
                            <p class="text-sm text-slate-400">Total</p>
                            <p class="font-bold text-slate-800"><?php echo $order_cost . ' XAF ';?></p>
                        </div>
                        <!-- compute status of order -->
                         <?php if($order_status === 'on_hold'): ?>
                        <span class="px-3 py-1 text-xs font-semibold bg-blue-500 text-white rounded-full ring-1 ring-blue-100"><?php echo $order_status; ?></span>
                        <?php elseif($order_status === 'processing'): ?>
                        <span class="px-3 py-1 text-xs font-semibold bg-green-100 text-green-600 rounded-full ring-1 ring-blue-100"><?php echo $order_status; ?></span>
                        <?php elseif($order_status === 'shipped'): ?>
                        <span class="px-3 py-1 text-xs font-semibold bg-amber-100 text-amber-700 rounded-full ring-1 ring-amber-200"><?php echo $order_status; ?></span>
                        <?php elseif($order_status === 'delivered'): ?>
                        <span class="px-3 py-1 text-xs font-semibold bg-green-500 text-white rounded-full ring-1 ring-green-200"><?php echo $order_status; ?></span>
                         <?php else: ?>
                        <span class="px-3 py-1 text-xs font-semibold bg-color-light-red text-red-500 rounded-full ring-2 ring-red-500"><?php echo $order_status; ?></span>
                        <?php endif;?>
                        <button onclick="toggleOrder(this)" class="p-2 hover:bg-slate-100 arrow-btn rounded-full rotate-icon">
                            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                    </div>
                </div>
                
                <!-- Expandable Details -->
                <div class="details-panel">
                    <!-- Progress Stepper -->
                     <!--  compute the order process-->
                      
                    <div class="mb-10 px-4">
                        <div class="flex items-center justify-between step-line">
                             <?php if($order_status === 'on_hold'): ?>
                            <div class="flex flex-col items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold step-dot ring-4 ring-blue-50">1</div>
                                <span class="text-[10px] font-bold text-slate-800 uppercase tracking-tighter">Placed</span>
                            </div>
                            <div class="flex flex-col items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-400 flex items-center justify-center text-xs font-bold step-dot">2</div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Processing</span>
                            </div>
                            <div class="flex flex-col items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-400 flex items-center justify-center text-xs font-bold step-dot">3</div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Shipped</span>
                            </div>
                            <div class="flex flex-col items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-400 flex items-center justify-center text-xs font-bold step-dot">4</div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Delivered</span>
                            </div>
                            <?php elseif($order_status === 'processing'): ?>
                            <div class="flex flex-col items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold step-dot ring-4 ring-blue-50">✓</div>
                                <span class="text-[10px] font-bold text-slate-800 uppercase tracking-tighter">Placed</span>
                            </div>
    
                            <div class="flex flex-col items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-green-100 text-green-600 flex items-center justify-center text-xs font-bold step-dot ring-4 ring-blue-50">2</div>
                                <span class="text-[10px] font-bold text-slate-800 uppercase tracking-tighter">Processing</span>
                            </div>
                            <div class="flex flex-col items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-400 flex items-center justify-center text-xs font-bold step-dot">3</div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Shipped</span>
                            </div>
                            <div class="flex flex-col items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-400 flex items-center justify-center text-xs font-bold step-dot">4</div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Delivered</span>
                            </div>
                            <?php elseif($order_status === 'shipped'): ?>
                            <div class="flex flex-col items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold step-dot ring-4 ring-blue-50">✓</div>
                                <span class="text-[10px] font-bold text-slate-800 uppercase tracking-tighter">Placed</span>
                             </div>
    
                            <div class="flex flex-col items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-green-100 text-green-600 flex items-center justify-center text-xs font-bold step-dot ring-4 ring-blue-50">✓</div>
                                <span class="text-[10px] font-bold text-slate-800 uppercase tracking-tighter">Processing</span>
                            </div>
                            <div class="flex flex-col items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center text-xs font-bold step-dot ring-4 ring-blue-50">3</div>
                                <span class="text-[10px] font-bold text-slate-800 uppercase tracking-tighter">Shipped</span>
                            </div>
                            <div class="flex flex-col items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-400 flex items-center justify-center text-xs font-bold step-dot">4</div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Delivered</span>
                            </div>
                            <?php elseif($order_status === 'delivered'): ?>
                            <div class="flex flex-col items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold step-dot ring-4 ring-blue-50">✓</div>
                                <span class="text-[10px] font-bold text-slate-800 uppercase tracking-tighter">Placed</span>
                            </div>
    
                            <div class="flex flex-col items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-green-100 text-green-600 flex items-center justify-center text-xs font-bold step-dot ring-4 ring-blue-50">✓</div>
                                <span class="text-[10px] font-bold text-slate-800 uppercase tracking-tighter">Processing</span>
                            </div>
                            <div class="flex flex-col items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center text-xs font-bold step-dot ring-4 ring-blue-50">✓</div>
                                <span class="text-[10px] font-bold text-slate-800 uppercase tracking-tighter">Shipped</span>
                            </div>
                            <div class="flex flex-col items-center gap-2 step-line">
                                <div class="w-8 h-8 rounded-full bg-green-500 text-green-700 flex items-center justify-center text-xs font-bold step-dot ring-4 ring-blue-50">✓</div>
                                <span class="text-[10px] font-bold text-slate-800 uppercase tracking-tighter">Delivered</span>
                            </div>
                            <?php elseif($order_status === 'cancelled'): ?>
                            <div class="flex flex-col items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-color-light-red text-white flex items-center justify-center text-xs font-bold step-dot ring-4 ring-blue-50">❌</div>
                                <span class="text-[10px] font-bold text-slate-800 uppercase tracking-tighter">Placed</span>
                            </div>
    
                            <div class="flex flex-col items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-color-light-red text-white flex items-center justify-center text-xs font-bold step-dot ring-4 ring-blue-50">❌</div>
                                <span class="text-[10px] font-bold text-slate-800 uppercase tracking-tighter">Processing</span>
                            </div>
                            <div class="flex flex-col items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-color-light-red text-white flex items-center justify-center text-xs font-bold step-dot ring-4 ring-blue-50">❌</div>
                                <span class="text-[10px] font-bold text-slate-800 uppercase tracking-tighter">Shipped</span>
                            </div>
                            <div class="flex flex-col items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-color-light-red text-white flex items-center justify-center text-xs font-bold step-dot ring-4 ring-blue-50">❌</div>
                                <span class="text-[10px] font-bold text-slate-800 uppercase tracking-tighter">Delivered</span>
                            </div>
                            <?php endif;?>
                        </div>
                    </div>

                    <!-- Product Listing from database -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Product List -->
                        <div class="space-y-4">
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Package Contents</p>
                            
                            <?php foreach($value['products'] as $products){
                                $product_image = isset($products['product_image']) ? htmlspecialchars($products['product_image'], ENT_QUOTES, 'UTF-8'): '';
                                $product_img_src = ($product_image && preg_match('#^(https?:)?//#i', $product_image)) ? $product_image : (ASSETS_URL . '/imgs/' . $product_image);
                                $product_name = isset($products['product_name']) ? htmlspecialchars($products['product_name'], ENT_QUOTES, 'UTF-8'): '';
                                $product_quantity = isset($products['product_quantity']) ? htmlspecialchars((string)$products['product_quantity'], ENT_QUOTES, 'UTF-8'): '0';
                                $product_price = isset($products['product_price']) ? htmlspecialchars((string)$products['product_price'], ENT_QUOTES, 'UTF-8'): '0';
                                $product_color = isset($products['product_color']) ? htmlspecialchars($products['product_color'], ENT_QUOTES, 'UTF-8'): '';
                                $product_variant = isset($products['product_variant']) ? htmlspecialchars($products['product_variant'], ENT_QUOTES, 'UTF-8'): '';
                                ?>
                            <div class="flex items-center gap-4 group">
                                <div class="w-16 h-16 bg-slate-100 rounded-xl flex-shrink-0 flex items-center justify-center overflow-hidden border border-slate-100 group-hover:border-blue-200 transition-colors" onclick="window.location.href='<?php echo universalstore_URL; ?>/<?php echo isset($products['product_category']) ? $products['product_category'] : ''; ?>/<?php echo isset($products['product_id']) ? (int)$products['product_id'] : 0; ?>/<?php echo isset($products['product_name']) ? urlencode($products['product_name']) : ''; ?>/<?php echo isset($products['product_color']) ? urlencode($products['product_color']) : ''; ?>';" style="cursor:pointer;">
                                    <img src="<?php echo $product_img_src; ?>" alt="<?php echo $product_name; ?>" class="w-full h-full object-cover">
                                </div>
                                <div class="flex-1" onclick="window.location.href='<?php echo universalstore_URL; ?>/<?php echo createSlug(isset($products['product_category']) ? $products['product_category'] : ''); ?>/<?php echo createSlug(isset($products['product_id']) ? (int)$products['product_id'] : 0); ?>/<?php echo createSlug(isset($products['product_name']) ? urlencode($products['product_name']) : ''); ?>/<?php echo createSlug(isset($products['product_color']) ? urlencode($products['product_color']) : ''); ?>';" style="cursor:pointer;">
                                    <p class="font-medium text-slate-800 hover:text-blue-700"><?php echo $product_name . ' ' . $product_color . ' ' . $product_variant; ?></p>
                                    <p class="text-sm text-slate-500">Qty: <?php echo $product_quantity; ?> • <?php echo $product_price; ?> XAF</p>
                                </div>
                            </div>
                            <?php }?>
                        </div>

                        <!-- Summary & Shipping -->
                        <div class="bg-slate-50 rounded-2xl p-6 space-y-6">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase mb-1">Shipping To</p>
                                    <p class="text-sm text-slate-700 leading-relaxed font-medium"><?php echo $utility_for_data_decryption->decryptData(htmlspecialchars($value['user_address'], ENT_QUOTES, 'UTF-8')) . ', ' . $utility_for_data_decryption->decryptData(htmlspecialchars($value['user_city'], ENT_QUOTES, 'UTF-8')); ?><br><?php echo htmlspecialchars($value['user_postcode'], ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars($value['user_country'], ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                                <button class="text-blue-600 text-xs font-bold hover:underline">Track Link</button>
                            </div>
                            
                            <div class="pt-4 border-t border-slate-200">
                                <div class="flex justify-between text-sm mb-2 text-slate-500">
                                    <span>Subtotal</span>
                                    <span><?php echo $order_cost; ?> XAF</span>
                                </div>
                                <div class="flex justify-between text-sm mb-4 text-slate-500">
                                    <span>Delivery Fee</span>
                                    <span>0 XAF</span>
                                </div>
                                <div class="flex justify-between text-lg font-bold pt-4 border-t border-dashed border-slate-300">
                                    <span class="text-slate-800">Total</span>
                                    <span class="text-blue-600"><?php echo $order_cost; ?> XAF</span>
                                </div>
                            </div>

                            <!-- compute order status changes for buttons --> 
                            <?php if($order_status === 'on_hold'): ?>
                            <div class="flex gap-2">
                                <a class="" href="<?php echo universalstore_URL;?>/payment.php?order_id=<?php echo htmlspecialchars($value['order_id'], ENT_QUOTES, 'UTF-8');?>"><button class="flex-1 btn-pay bg-slate-800 hover:bg-slate-900 text-white py-3 px-3 rounded-xl text-sm font-semibold transition-all"  >Complete Payment</button></a>
                                <a href="<?php echo universalstore_URL; ?>/invoice?order_id=<?php echo htmlspecialchars($value['order_id'], ENT_QUOTES, 'UTF-8'); ?>" class="px-4 bg-white no-underline border border-slate-200 text-slate-700 py-3 rounded-xl text-sm font-semibold hover:bg-slate-50 text-center">View Invoice</a>
                            </div>  
                            <?php elseif($order_status === 'processing'): ?>
                            <div class="flex gap-2">
                                <a class="" style="color:white;" href="<?php echo universalstore_URL;?>/payment_status?payment_id=<?php echo htmlspecialchars($payment_id, ENT_QUOTES, 'UTF-8');?>"><button class="flex-1 btn-pay bg-green-500 hover:text-green-200 hover:bg-green-100  py-3 px-3 rounded-xl text-sm font-semibold transition-all"  >Check Payment Status</button></a>
                                <a href="<?php echo universalstore_URL; ?>/invoice?order_id=<?php echo htmlspecialchars($value['order_id'], ENT_QUOTES, 'UTF-8'); ?>" class="px-4 bg-white no-underline border border-slate-200 text-slate-700 py-3 rounded-xl text-sm font-semibold hover:bg-slate-50 text-center">View Invoice</a>
                            </div>   
                             <?php elseif($order_status === 'shipped'): ?>
                            <div class="flex gap-2">
                               <a class="" style="color:white;" href="<?php echo universalstore_URL;?>/payment_status?payment_id=<?php echo htmlspecialchars($payment_id, ENT_QUOTES, 'UTF-8');?>"><button class="flex-1 btn-pay bg-green-500 hover:text-green-200 hover:bg-green-100  py-3 px-3 rounded-xl text-sm font-semibold transition-all"  >Check Payment Status</button></a>
                                <a href="<?php echo universalstore_URL; ?>/invoice?order_id=<?php echo htmlspecialchars($value['order_id'], ENT_QUOTES, 'UTF-8'); ?>" class="px-4 bg-white no-underline border border-slate-200 text-slate-700 py-3 rounded-xl text-sm font-semibold hover:bg-slate-50 text-center">View Invoice</a>
                            </div>
                            <?php elseif($order_status === 'delivered'): ?>
                            <div class="flex gap-2">
                                 <a class="" style="color:white;" href="<?php echo universalstore_URL;?>/payment_status?payment_id=<?php echo htmlspecialchars($payment_id, ENT_QUOTES, 'UTF-8');?>"><button class="flex-1 btn-pay bg-green-500 hover:text-green-200 hover:bg-green-100  py-3 px-3 rounded-xl text-sm font-semibold transition-all"  >Check Payment Status</button></a>
                                <a href="<?php echo universalstore_URL; ?>/invoice?order_id=<?php echo htmlspecialchars($value['order_id'], ENT_QUOTES, 'UTF-8'); ?>" class="px-4 bg-white no-underline border border-slate-200 text-slate-700 py-3 rounded-xl text-sm font-semibold hover:bg-slate-50 text-center">View Invoice</a>
                            </div>
                            <?php elseif($order_status === 'cancelled'): ?>
                            <div class="flex gap-2">
                                <button class="flex-1 btn-pay bg-color-light-red text-red-500 py-3 px-3 rounded-xl text-sm font-semibold transition-all">Order Cancelled</button>
                                <a href="<?php echo universalstore_URL; ?>/invoice?order_id=<?php echo htmlspecialchars($value['order_id'], ENT_QUOTES, 'UTF-8'); ?>" class="px-4 bg-white no-underline border border-slate-200 text-slate-700 py-3 rounded-xl text-sm font-semibold hover:bg-slate-50 text-center">View Invoice</a>
                            </div>
                            <?php endif;?>
                        </div>
                    </div>
                </div>
            </div>
            <?php }?>
            <!-- Order Card 2: Delivered Example -->
            <div class="order-card bg-white rounded-2xl shadow-sm border border-slate-200 p-5 md:p-6 opacity-80 hover:opacity-100 grayscale hover:grayscale-0 transition-all">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="bg-green-50 p-3 rounded-xl">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-slate-800">Order #ORD-77102</h3>
                            <p class="text-sm text-slate-400">Delivered on Oct 12, 2023</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-6">
                        <div class="text-right hidden sm:block">
                            <p class="text-sm text-slate-400">Total</p>
                            <p class="font-bold text-slate-800">12,000 XAF</p>
                        </div>
                        <span class="px-3 py-1 text-xs font-semibold bg-green-100 text-green-700 rounded-full ring-1 ring-green-200">Delivered</span>
                        <button onclick="toggleOrder(this)" class="p-2 hover:bg-slate-100 rounded-full rotate-icon">
                            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                    </div>
                </div>
                <div class="details-panel">
                    <div class="text-center py-8">
                        <p class="text-slate-500 mb-4 font-medium">This order was delivered successfully.</p>
                        <button class="bg-blue-50 text-blue-600 px-6 py-2 rounded-lg text-sm font-bold hover:bg-blue-100">Leave a Review</button>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <br><br><br>
    <div class="max-w-4xl mx-auto">
        <header class="mb-10 text-center">
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">Order Settlement Engine</h1>
            <p class="text-slate-500 mt-2">A breakdown of the lifecycle from intent to final settlement.</p>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            
            <!-- Left Column: Process Flow -->
            <div class="md:col-span-1 space-y-10">
                <div class="timeline-step active-step">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shadow-lg shadow-blue-200">1</div>
                        <div>
                            <h3 class="font-bold">Placement</h3>
                            <p class="text-xs text-slate-400">Pending / On Hold</p>
                        </div>
                    </div>
                </div>

                <div class="timeline-step active-step">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shadow-lg shadow-blue-200">2</div>
                        <div>
                            <h3 class="font-bold">Fulfillment</h3>
                            <p class="text-xs text-slate-400">Processing / Paid</p>
                        </div>
                    </div>
                </div>

                <div class="timeline-step">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-slate-200 text-slate-500 flex items-center justify-center font-bold">3</div>
                        <div>
                            <h3 class="font-bold">Logistics</h3>
                            <p class="text-xs text-slate-400">Shipped / Out for Delivery</p>
                        </div>
                    </div>
                </div>

                <div class="timeline-step">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-slate-200 text-slate-500 flex items-center justify-center font-bold">4</div>
                        <div>
                            <h3 class="font-bold">Settlement</h3>
                            <p class="text-xs text-slate-400">Delivered / Completed</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Detail Cards -->
            <div class="md:col-span-2 space-y-4">
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                    <!-- Display orders and status -->
                    <div class="flex justify-between items-start mb-4">
                        <span class="px-3 py-1 bg-amber-100 text-amber-700 text-xs font-bold rounded-full uppercase tracking-wider">Current State: <?php echo (string)$order_status; ?></span>
                        <span class="text-slate-400 text-sm">Ref: <?php echo (string)substr($order_id,0,12); ?></span>
                        </div>
                    <h2 class="text-xl font-bold mb-2">Detailed Status Definitions</h2>
                    <ul class="space-y-4 mt-6">
                        <li class="flex gap-4">
                            <div class="w-2 h-2 rounded-full bg-blue-500 mt-2"></div>
                            <div>
                                <p class="font-bold text-sm">On Hold</p>
                                <p class="text-sm text-slate-500">Order Placed successfully. No payment made, Please make sure you complete payment.</p>
                            </div>
                        </li>
                        <li class="flex gap-4">
                            <div class="w-2 h-2 rounded-full bg-green-100 mt-2"></div>
                            <div>
                                <p class="font-bold text-sm">Processing</p>
                                <p class="text-sm text-slate-500">- We are Processing your order.</p>
                                <p class="text-sm text-slate-500">-  Your Payment have been Validated.</p>
                            </div>
                        </li>
                        <li class="flex gap-4">
                            <div class="w-2 h-2 rounded-full bg-amber-100 mt-2"></div>
                            <div>
                                <p class="font-bold text-sm">Logistics (Shipped)</p>
                                <p class="text-sm text-slate-500">Your order/products have been shipped already, you will recieve your items in few days.</p>
                            </div>
                        </li>
                        <li class="flex gap-4">
                            <div class="w-2 h-2 rounded-full bg-green-500 mt-2"></div>
                            <div>
                                <p class="font-bold text-sm">Delivered (Settled)</p>
                                <p class="text-sm text-slate-500">The courier has confirmed receipt. The revenue is now officially earned.</p>
                            </div>
                        </li>
                        <li class="flex gap-4">
                            <div class="w-2 h-2 rounded-full bg-red-500 mt-2"></div>
                            <div>
                                <p class="font-bold text-sm">Cancelled (Settled)</p>
                                <p class="text-sm text-slate-500">The transaction is dead. Stock is returned to the virtual shelf.</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="bg-blue-600 p-6 rounded-2xl text-white shadow-xl mt-5 shadow-blue-100">
                    <h3 class="font-bold mb-2">Pro -Tips for Delivery</h3>
                    <p class="text-sm opacity-90 leading-relaxed">
                      1. Returns are only possible within 15 days you recieved the products. Passed that day no return nor refund is possible. Contact us via your Universal Store account to start a return procedure.
                    </p>
                    <p class="text-sm opacity-90 leading-relaxed mt-4">
                      2. Your orders are rapidly purchase dependending on you payment time, late payment results to late delivery dates and fast/immediate payments results to fast delivery dates. We advice you to make your payments as soon as possible to get your products as soon as possible.
                    </p>
                    <p class="text-sm opacity-90 leading-relaxed mt-4">
                      3. For any issues with your order, please contact our support team within 7 days of delivery. We are here to help resolve any problems you may encounter.
                    </p>
                    <p class="text-sm opacity-90 leading-relaxed mt-4">
                      4. For any inquiries about your order, please reach out to our support team through your Universal Store account or email us at <a href="mailto:fonkouaarnauld@gmail.com" class="underline hover:text-blue-200">fonkouaarnauld@gmail.com</a>
                    </p>
                </div>
    </div>

</main>
</html>