<?php
class BC_Orders_V10{
    public static function init(){
        add_action('woocommerce_thankyou', [__CLASS__, 'process_order'], 10, 1);
    }
    
    public static function process_order($order_id){
        if(!$order_id) return;
        
        error_log('BC eSIM: === PROCESANDO ORDEN WC #' . $order_id . ' ===');
        
        $order = wc_get_order($order_id);
        if(!$order) {
            error_log('BC eSIM: Orden no encontrada');
            return;
        }
        
        error_log('BC eSIM: Estado orden: ' . $order->get_status());
        error_log('BC eSIM: Email cliente: ' . $order->get_billing_email());
        
        // Verificar si ya se procesó
        if($order->get_meta('_bc_processed')) {
            error_log('BC eSIM: Orden ya procesada anteriormente');
            return;
        }
        
        $items = $order->get_items();
        error_log('BC eSIM: Items en orden: ' . count($items));
        
        foreach($items as $item_id => $item){
            $product = $item->get_product();
            
            if(!$product || $product->get_meta('_bc_product') !== 'yes') {
                error_log('BC eSIM: Item ' . $item_id . ' no es producto BC');
                continue;
            }
            
            $sku_id = $product->get_meta('_bc_sku_id');
            $copies = $item->get_meta('bc_copies', true) ?: 1;
            
            // 🔍 DEBUG CRÍTICO
            error_log('BC eSIM: ========== DEBUG ORDEN ==========');
            error_log('BC eSIM: Product ID: ' . $product->get_id());
            error_log('BC eSIM: Product Title: ' . $product->get_name());
            error_log('BC eSIM: SKU ID guardado: ' . $sku_id);
            error_log('BC eSIM: Copies elegidos: ' . $copies);
            error_log('BC eSIM: Plan data: ' . $product->get_meta('_bc_plan_data'));
            error_log('BC eSIM: ===================================');
            
            if(empty($sku_id)){
                error_log('BC eSIM: ❌ ERROR: SKU ID VACÍO para producto ' . $product->get_id());
                $order->add_order_note('❌ ERROR: Producto sin SKU ID válido');
                continue;
            }
            
            error_log('BC eSIM: Procesando SKU: ' . $sku_id . ', Copies: ' . $copies);
            
            // Llamar F040
            $result = BC_API_V10::create_order([
                'deviceSkuId' => $sku_id,
                'planSkuCopies' => strval($copies),
                'number' => '1',
                'channelOrderId' => 'WC-' . $order_id . '-' . $item_id,
                'customerEmail' => $order->get_billing_email(),
                'firstName' => $order->get_billing_first_name(),
                'lastName' => $order->get_billing_last_name()
            ]);
            
            if($result['success']){
                $bc_order_id = $result['data']['orderId'] ?? '';
                update_post_meta($order_id, '_bc_order_id_' . $item_id, $bc_order_id);
                $order->add_order_note('✅ Orden creada en BC: ' . $bc_order_id . ' (SKU: ' . $sku_id . ', Copies: ' . $copies . ')');
                error_log('BC eSIM: ✅ F040 ÉXITO - WC:' . $order_id . ' BC:' . $bc_order_id);
            } else {
                $error_msg = $result['message'] ?? 'Error desconocido';
                $order->add_order_note('❌ Error al crear orden en BC: ' . $error_msg);
                error_log('BC eSIM: ❌ F040 ERROR - ' . $error_msg);
            }
        }
        
        update_post_meta($order_id, '_bc_processed', 'yes');
        error_log('BC eSIM: === FIN PROCESAMIENTO ORDEN #' . $order_id . ' ===');
    }
}
