<?php
class BC_Cart_V10{
    public static function init(){
        // Mostrar selector de opciones en página producto
        add_action('woocommerce_before_add_to_cart_button', [__CLASS__, 'show_options_selector']);
        
        // Guardar opciones elegidas al añadir al carrito
        add_filter('woocommerce_add_cart_item_data', [__CLASS__, 'add_cart_item_data'], 10, 2);
        
        // Actualizar precio en carrito según opción elegida
        add_action('woocommerce_before_calculate_totals', [__CLASS__, 'update_cart_price']);
        
        // Mostrar info en carrito
        add_filter('woocommerce_get_item_data', [__CLASS__, 'display_cart_item_data'], 10, 2);
        
        // Mostrar botones después de añadir al carrito
        add_filter('woocommerce_add_to_cart_message_html', [__CLASS__, 'custom_add_to_cart_message'], 10, 2);
        
        // Script para mostrar mensaje flotante (siempre funciona)
        add_action('wp_footer', [__CLASS__, 'add_cart_success_script']);
    }
    
    public static function show_options_selector(){
        global $product;
        
        if(!$product || $product->get_meta('_bc_product') !== 'yes') return;
        
        $options = $product->get_meta('_bc_price_options');
        if(!$options) return;
        
        $options_array = json_decode($options, true);
        if(empty($options_array)) return;
        
        $plan_type = $product->get_meta('_bc_plan_type');
        
        // Insertar ANTES del botón de añadir al carrito
        echo '<div class="bc-price-options" ';
        echo 'data-options=\'' . esc_attr($options) . '\' ';
        echo 'data-plan-type="' . esc_attr($plan_type) . '" ';
        echo 'data-product-id="' . $product->get_id() . '"';
        echo ' style="order:-1;width:100%;margin-bottom:20px;">';  // order:-1 para que aparezca primero
        echo '</div>';
        
        // CSS para reordenar
        echo '<style>
        form.cart {
            display:flex;
            flex-direction:column;
        }
        form.cart .quantity {
            display:none !important; /* Ocultar cantidad, solo se necesita 1 eSIM */
        }
        form.cart button[type="submit"] {
            order:3;
            width:100% !important;
            max-width:600px !important;
            margin:30px auto 0 !important;
            padding:20px 40px !important;
            font-size:20px !important;
            font-weight:700 !important;
            background:#0073aa !important;
            border:none !important;
            border-radius:8px !important;
            cursor:pointer !important;
            transition:all 0.3s !important;
            box-shadow:0 4px 12px rgba(0,115,170,0.3) !important;
        }
        form.cart button[type="submit"]:hover {
            background:#005a87 !important;
            transform:translateY(-2px) !important;
            box-shadow:0 6px 16px rgba(0,115,170,0.4) !important;
        }
        .bc-options-selector {
            order:1;
        }
        </style>';
    }
    
    public static function add_cart_item_data($cart_item_data, $product_id){
        $product = wc_get_product($product_id);
        
        if($product && $product->get_meta('_bc_product') === 'yes'){
            if(isset($_POST['bc_copies'])){
                $cart_item_data['bc_copies'] = intval($_POST['bc_copies']);
            }
            if(isset($_POST['bc_price'])){
                $cart_item_data['bc_price'] = floatval($_POST['bc_price']);
            }
            if(isset($_POST['bc_sku_id'])){
                $cart_item_data['bc_sku_id'] = sanitize_text_field($_POST['bc_sku_id']);
            }
        }
        
        return $cart_item_data;
    }
    
    public static function update_cart_price($cart){
        if(is_admin() && !defined('DOING_AJAX')) return;
        
        foreach($cart->get_cart() as $cart_item){
            if(isset($cart_item['bc_price'])){
                $cart_item['data']->set_price($cart_item['bc_price']);
            }
        }
    }
    
    public static function display_cart_item_data($item_data, $cart_item){
        if(isset($cart_item['bc_copies'])){
            $days = intval($cart_item['bc_copies']);
            $label = $days === 1 ? '1 día' : $days . ' días';
            
            $item_data[] = [
                'name' => 'Duración',
                'value' => $label
            ];
        }
        
        return $item_data;
    }
    
    public static function custom_add_to_cart_message($message, $products){
        $cart_url = wc_get_cart_url();
        $checkout_url = wc_get_checkout_url();
        
        $custom_message = '<div style="background:#e8f5e9;padding:20px;border-left:4px solid #4caf50;border-radius:4px;">';
        $custom_message .= '<p style="margin:0 0 20px 0;font-size:18px;font-weight:600;color:#2e7d32;">✅ Producto añadido al carrito correctamente</p>';
        $custom_message .= '<div style="display:flex;gap:15px;flex-wrap:wrap;">';
        $custom_message .= '<a href="' . esc_url($cart_url) . '" class="button" style="flex:1;min-width:200px;padding:15px 30px;font-size:18px;font-weight:700;text-align:center;background:#fff;color:#0073aa;border:2px solid #0073aa;text-decoration:none;border-radius:6px;transition:all 0.3s;">🛒 Ver Carrito</a>';
        $custom_message .= '<a href="' . esc_url($checkout_url) . '" class="button alt" style="flex:1;min-width:200px;padding:15px 30px;font-size:18px;font-weight:700;text-align:center;background:#0073aa;color:#fff;border:none;text-decoration:none;border-radius:6px;box-shadow:0 4px 12px rgba(0,115,170,0.3);transition:all 0.3s;">💳 Finalizar Compra →</a>';
        $custom_message .= '</div>';
        $custom_message .= '</div>';
        
        return $custom_message;
    }
    
    public static function add_cart_success_script(){
        if(!is_product()) return;
        
        $cart_url = wc_get_cart_url();
        $checkout_url = wc_get_checkout_url();
        ?>
        <script>
        jQuery(document).ready(function($){
            // Interceptar evento de añadir al carrito
            $('form.cart').on('submit', function(e){
                var form = $(this);
                
                // Esperar a que se añada al carrito
                setTimeout(function(){
                    // Verificar si se añadió correctamente (sin errores)
                    if(!$('.woocommerce-error').length){
                        // Mostrar mensaje flotante
                        var message = '<div id="bcesim-cart-success" style="position:fixed;top:20px;right:20px;z-index:999999;background:#fff;border:2px solid #4caf50;border-radius:8px;padding:25px;box-shadow:0 8px 24px rgba(0,0,0,0.2);max-width:400px;animation:slideIn 0.3s ease-out;">';
                        message += '<p style="margin:0 0 15px 0;font-size:18px;font-weight:700;color:#2e7d32;">✅ ¡Producto añadido!</p>';
                        message += '<div style="display:flex;flex-direction:column;gap:10px;">';
                        message += '<a href="<?php echo esc_js($cart_url); ?>" class="button" style="padding:12px 20px;text-align:center;font-size:16px;font-weight:600;text-decoration:none;border-radius:6px;border:2px solid #0073aa;color:#0073aa;background:#fff;transition:all 0.3s;">🛒 Ver Carrito</a>';
                        message += '<a href="<?php echo esc_js($checkout_url); ?>" class="button button-primary" style="padding:12px 20px;text-align:center;font-size:16px;font-weight:600;text-decoration:none;border-radius:6px;border:none;background:#0073aa;color:#fff;box-shadow:0 4px 12px rgba(0,115,170,0.3);transition:all 0.3s;">💳 Finalizar Compra →</a>';
                        message += '</div>';
                        message += '<button onclick="jQuery(\'#bcesim-cart-success\').fadeOut()" style="position:absolute;top:10px;right:10px;background:none;border:none;font-size:20px;cursor:pointer;color:#666;padding:0;width:30px;height:30px;line-height:1;">×</button>';
                        message += '</div>';
                        
                        $('body').append(message);
                        
                        // Auto-cerrar después de 10 segundos
                        setTimeout(function(){
                            $('#bcesim-cart-success').fadeOut(400, function(){
                                $(this).remove();
                            });
                        }, 10000);
                    }
                }, 1000);
            });
        });
        </script>
        <style>
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        #bcesim-cart-success a:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        </style>
        <?php
    }
}
