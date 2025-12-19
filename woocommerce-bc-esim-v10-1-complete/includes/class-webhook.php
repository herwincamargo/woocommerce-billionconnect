<?php
class BC_Webhook_V10{
    public static function init(){
        add_action('rest_api_init', [__CLASS__, 'register_webhook']);
    }
    
    public static function register_webhook(){
        register_rest_route('bcesim/v1', '/webhook', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_webhook'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    public static function handle_webhook($request){
        $headers = $request->get_headers();
        $body = $request->get_body();
        
        error_log('BC eSIM: Webhook recibido - ' . $body);
        
        $appsecret = get_option('bc_appsecret');
        
        $received = $headers['x_sign_value'][0] ?? '';
        $expected = md5($appsecret . $body);
        
        if($received !== $expected){
            error_log('BC eSIM: Webhook firma inválida');
            return new WP_REST_Response(['error' => 'Firma inválida'], 403);
        }
        
        $data = json_decode($body, true);
        
        if(!$data || !isset($data['tradeType'])){
            error_log('BC eSIM: Webhook datos inválidos');
            return new WP_REST_Response(['error' => 'Datos inválidos'], 400);
        }
        
        if($data['tradeType'] === 'N009'){
            self::handle_esim_delivery($data);
        }
        
        return new WP_REST_Response(['success' => true], 200);
    }
    
    private static function handle_esim_delivery($data){
        $trade = $data['tradeData'] ?? [];
        $bc_order_id = $trade['orderId'] ?? '';
        $esim_list = $trade['esimList'] ?? [];
        
        if(empty($bc_order_id)){
            error_log('BC eSIM: N009 sin orderId');
            return;
        }
        
        error_log('BC eSIM: N009 procesando orden BC: ' . $bc_order_id);
        
        // Buscar orden WC por bc_order_id en metadata
        global $wpdb;
        $order_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key LIKE '_bc_order_id_%' 
            AND meta_value = %s 
            LIMIT 1",
            $bc_order_id
        ));
        
        if(!$order_id){
            error_log('BC eSIM: N009 orden WC no encontrada para BC: ' . $bc_order_id);
            return;
        }
        
        $order = wc_get_order($order_id);
        if(!$order){
            error_log('BC eSIM: N009 orden WC inválida: ' . $order_id);
            return;
        }
        
        // Guardar eSIM data
        update_post_meta($order_id, '_bc_esim_data', json_encode($esim_list));
        $order->add_order_note('✅ eSIM recibido de BillionConnect - ' . count($esim_list) . ' eSIM(s)');
        
        error_log('BC eSIM: N009 guardado para orden WC: ' . $order_id);
        
        // Enviar email
        if(class_exists('BC_Emails_V10')){
            BC_Emails_V10::send_esim_email($order, $esim_list);
        }
    }
}
