<?php
/*
Plugin Name: WooCommerce BC eSIM v10.1 Complete
Description: Plugin completo - 74 productos, selectores, F040, N009, emails, recargas
Version: 10.5.1
Author: HeroeSIM
*/
if(!defined('ABSPATH'))exit;

define('BCESIM_DIR',plugin_dir_path(__FILE__));
define('BCESIM_URL',plugin_dir_url(__FILE__));
define('BCESIM_VERSION','10.5.1');

add_action('plugins_loaded', 'bcesim_v10_load', 5);
function bcesim_v10_load(){
    $files = ['class-admin', 'class-api', 'class-products', 'class-cart', 'class-orders', 'class-webhook', 'class-emails', 'class-recharge'];
    foreach($files as $f){
        $file = BCESIM_DIR . "includes/$f.php";
        if(file_exists($file)){
            require_once $file;
        }
    }
}

add_action('wp_enqueue_scripts', 'bcesim_v10_enqueue');
function bcesim_v10_enqueue(){
    wp_enqueue_style('bcesim-css', BCESIM_URL . 'assets/css/frontend.css', [], BCESIM_VERSION . '.3');
    wp_enqueue_script('bcesim-js', BCESIM_URL . 'assets/js/frontend.js', ['jquery'], BCESIM_VERSION, true);
    
    // Selector de producto
    if(is_product()){
        wp_enqueue_script('bcesim-product', BCESIM_URL . 'assets/js/product-selector.js', ['jquery'], BCESIM_VERSION . '.2', true);
    }
    
    wp_localize_script('bcesim-js', 'bcesim_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('bcesim_nonce')
    ]);
}

register_activation_hook(__FILE__, 'bcesim_v10_activate');
function bcesim_v10_activate(){
    update_option('bc_appkey', 'tttt');
    update_option('bc_appsecret', '2c61a2963fdc44348f14d18a369f49c1');
    update_option('bc_salesmethod', '5');
    update_option('bc_language', '2');
    update_option('bc_env', 'test');
    update_option('bc_api_test_url', 'https://api-flow-ts.billionconnect.com/Flow/saler/2.0/invoke');
    update_option('bc_api_prod_url', 'https://api-flow.billionconnect.com/Flow/saler/2.0/invoke');
    
    $webhook_url = rest_url('bcesim/v1/webhook');
    update_option('bc_webhook_url', $webhook_url);
}

add_action('plugins_loaded', 'bcesim_v10_init', 10);
function bcesim_v10_init(){
    if(class_exists('BC_Admin_V10')) BC_Admin_V10::init();
    if(class_exists('BC_API_V10')) BC_API_V10::init();
    if(class_exists('BC_Products_V10')) BC_Products_V10::init();
    if(class_exists('BC_Cart_V10')) BC_Cart_V10::init();
    if(class_exists('BC_Orders_V10')) BC_Orders_V10::init();
    if(class_exists('BC_Webhook_V10')) BC_Webhook_V10::init();
    if(class_exists('BC_Emails_V10')) BC_Emails_V10::init();
    if(class_exists('BC_Recharge_V10')) BC_Recharge_V10::init();
}

add_shortcode('bcesim_destinations', 'bcesim_v10_destinations');
function bcesim_v10_destinations(){
    if(!class_exists('BC_API_V10')) return '<p>Plugin no inicializado.</p>';
    return BC_API_V10::render_destinations();
}

add_shortcode('bcesim_recharge', 'bcesim_v10_recharge');
function bcesim_v10_recharge(){
    if(!class_exists('BC_Recharge_V10')) return '<p>Sistema de recargas no disponible.</p>';
    return BC_Recharge_V10::render_form();
}
