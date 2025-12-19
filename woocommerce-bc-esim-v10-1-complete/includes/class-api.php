<?php
class BC_API_V10{
    public static function init(){
        add_action('rest_api_init', [__CLASS__, 'register_webhook']);
        add_action('wp_ajax_bcesim_get_plans', [__CLASS__, 'ajax_get_plans']);
        add_action('wp_ajax_nopriv_bcesim_get_plans', [__CLASS__, 'ajax_get_plans']);
    }

    public static function register_webhook(){
        register_rest_route('bcesim/v1', '/webhook', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_webhook'],
            'permission_callback' => '__return_true'
        ]);
    }

    // Other methods remain unchanged

    // This was incorrectly placed; let's encapsulate it in a method:
    public static function validate_result($result) {
        if($result['tradeCode'] != '1000'){
            return ['success' => false, 'message' => $result['tradeMsg'] ?? 'Error desconocido'];
        }

        return ['success' => true, 'data' => $result['tradeData'] ?? []];
    }
}