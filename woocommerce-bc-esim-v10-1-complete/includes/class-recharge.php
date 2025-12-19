<?php
class BC_Recharge_V10{
    public static function init(){
        add_action('wp_ajax_bcesim_search_iccid', [__CLASS__, 'ajax_search_iccid']);
        add_action('wp_ajax_nopriv_bcesim_search_iccid', [__CLASS__, 'ajax_search_iccid']);
        add_shortcode('bcesim_recharge', [__CLASS__, 'render_form']);
    }
    
    public static function render_form(){
        ob_start();
        ?>
        <div class="bcesim-recharge-wrap" style="max-width:900px;margin:40px auto;">
            <div style="padding:40px;border:2px solid #ddd;border-radius:12px;background:#fff;">
                <h2 style="margin-top:0;text-align:center;font-size:32px;">🔄 Agregar Datos a tu eSIM</h2>
                <p style="text-align:center;font-size:18px;color:#666;margin-bottom:30px;">Ingresa el código ICCID de tu eSIM para buscar paquetes de datos adicionales (Data Add-On).</p>
                
                <div style="padding:20px;border-radius:8px;margin-bottom:30px;border:1px solid #ddd;">
                    <h4 style="margin-top:0;">📱 ¿Dónde encuentro mi ICCID?</h4>
                    <ol style="margin:10px 0;padding-left:20px;">
                        <li><strong>iPhone:</strong> Ajustes → General → Información → Busca "ICCID"</li>
                        <li><strong>Android:</strong> Ajustes → Acerca del teléfono → Estado → Información SIM</li>
                        <li><strong>Marca *#06#</strong> en tu teléfono para ver todos los códigos</li>
                        <li>También está en el <strong>email original</strong> que recibiste al comprar</li>
                    </ol>
                    <p style="margin:10px 0 0;font-size:14px;color:#666;"><strong>Ejemplo:</strong> 8981200391682039741</p>
                </div>
                
                <div class="bcesim-recharge-form" style="margin:30px 0;">
                    <label for="iccid" style="display:block;margin-bottom:10px;font-weight:700;font-size:18px;">Código ICCID:</label>
                    <input type="text" id="iccid" placeholder="Ingresa tu código ICCID (19-20 dígitos)" maxlength="22" 
                           style="width:100%;padding:16px;font-size:18px;border:2px solid #ddd;border-radius:8px;margin-bottom:20px;box-sizing:border-box;font-family:monospace;">
                    <button id="search-iccid" class="button button-primary button-large" 
                            style="width:100%;padding:18px;font-size:20px;font-weight:700;border:none;border-radius:8px;cursor:pointer;transition:all 0.3s;">
                        🔍 Buscar Paquetes de Datos
                    </button>
                </div>
                
                <div id="recharge-results"></div>
            </div>
        </div>
        
        <style>
        .bcesim-recharge-wrap #search-iccid:hover {
            transform:translateY(-2px);
            opacity:0.9;
        }
        .bcesim-recharge-wrap #search-iccid:active {
            transform:translateY(0);
        }
        .bcesim-recharge-wrap #iccid:focus {
            outline:none;
            border-color:#0073aa;
            box-shadow:0 0 0 3px rgba(0,115,170,0.1);
        }
        </style>
        
        <script>
        jQuery(document).ready(function($){
            $('#search-iccid').on('click', function(){
                const iccid = $('#iccid').val().trim();
                
                if(!iccid){
                    alert('⚠️ Por favor ingresa un código ICCID válido');
                    $('#iccid').focus();
                    return;
                }
                
                if(iccid.length < 15){
                    alert('⚠️ El ICCID debe tener al menos 15 dígitos');
                    $('#iccid').focus();
                    return;
                }
                
                $(this).prop('disabled', true).html('⏳ Buscando paquetes...');
                $('#recharge-results').html('<div style="text-align:center;padding:40px;"><div style="display:inline-block;width:50px;height:50px;border:5px solid #f3f3f3;border-top:5px solid #0073aa;border-radius:50%;animation:spin 1s linear infinite;"></div><p style="margin-top:20px;font-size:18px;color:#666;">Consultando paquetes de datos disponibles...</p></div>');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'bcesim_search_iccid',
                        nonce: '<?php echo wp_create_nonce('bcesim_nonce'); ?>',
                        iccid: iccid
                    },
                    success: function(res){
                        if(res.success){
                            let html = '<div style="margin-top:30px;">';
                            html += '<div style="padding:20px;border-radius:8px;border:2px solid #4caf50;margin-bottom:25px;">';
                            html += '<h3 style="margin:0 0 10px 0;font-size:24px;">✅ ¡Paquetes de datos encontrados!</h3>';
                            html += '<p style="margin:0;color:#555;">Encontramos ' + res.data.plans.length + ' paquete(s) de datos adicionales (Data Add-On) compatible(s) con tu eSIM.</p>';
                            html += '</div>';
                            
                            res.data.plans.forEach(function(plan){
                                html += '<div style="border:2px solid #ddd;padding:25px;margin:20px 0;border-radius:12px;transition:all 0.3s;" class="recharge-plan-card">';
                                html += '<h4 style="margin-top:0;font-size:22px;">' + plan.name + '</h4>';
                                if(plan.desc){
                                    html += '<p style="color:#666;margin:10px 0;">' + plan.desc + '</p>';
                                }
                                html += '<div style="display:flex;align-items:center;justify-content:space-between;margin:20px 0;flex-wrap:wrap;gap:15px;">';
                                html += '<div style="font-size:32px;font-weight:bold;">$' + plan.price.toFixed(2) + ' <span style="font-size:18px;color:#999;">USD</span></div>';
                                html += '<a href="' + plan.url + '?iccid=' + iccid + '" class="button button-primary" style="padding:15px 30px;font-size:18px;font-weight:700;border-radius:8px;text-decoration:none;">Agregar datos →</a>';
                                html += '</div>';
                                html += '</div>';
                            });
                            
                            html += '<div style="padding:20px;border-radius:8px;border:1px solid #ddd;margin-top:25px;">';
                            html += '<p style="margin:0;"><strong>💡 Consejo:</strong> Los datos adicionales se agregarán automáticamente a tu eSIM actual. No necesitas reinstalar nada.</p>';
                            html += '</div>';
                            html += '</div>';
                            $('#recharge-results').html(html);
                        } else {
                            $('#recharge-results').html('<div style="padding:25px;border-radius:8px;border:2px solid #d63638;margin-top:20px;"><p style="margin:0;font-size:18px;"><strong>❌ No se encontraron paquetes</strong></p><p style="margin:10px 0 0;color:#666;">' + res.data.msg + '</p></div>');
                        }
                    },
                    error: function(){
                        $('#recharge-results').html('<div style="padding:25px;border-radius:8px;border:2px solid #d63638;margin-top:20px;"><p style="margin:0;"><strong>❌ Error de conexión</strong></p><p style="margin:10px 0 0;color:#666;">No se pudo conectar con el servidor. Por favor intenta de nuevo.</p></div>');
                    },
                    complete: function(){
                        $('#search-iccid').prop('disabled', false).html('🔍 Buscar Paquetes de Datos');
                    }
                });
            });
            
            // Permitir buscar con Enter
            $('#iccid').on('keypress', function(e){
                if(e.which === 13){
                    $('#search-iccid').click();
                }
            });
        });
        </script>
        
        <style>
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .recharge-plan-card:hover {
            border-color:#0073aa;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    public static function ajax_search_iccid(){
        check_ajax_referer('bcesim_nonce', 'nonce');
        
        $iccid = sanitize_text_field($_POST['iccid'] ?? '');
        
        if(empty($iccid)){
            wp_send_json_error(['msg' => 'ICCID requerido']);
        }
        
        // Llamar F015 - Query data add-on packages
        $result = BC_API_V10::query_data_addon($iccid);
        
        if(!$result['success']){
            wp_send_json_error(['msg' => $result['message'] ?? 'No se pudo consultar la API']);
        }
        
        $addon_data = $result['data'][0] ?? [];
        $acceleration_skus = $addon_data['accelerationSku'] ?? [];
        
        if(empty($acceleration_skus)){
            wp_send_json_error(['msg' => 'No hay paquetes de datos adicionales disponibles para este eSIM. Verifica el ICCID o contacta soporte.']);
        }
        
        // Procesar paquetes de add-on
        $plans = [];
        foreach($acceleration_skus as $acceleration_group){
            $sku_list = $acceleration_group['accelerationSkuList'] ?? [];
            
            foreach($sku_list as $sku){
                $sku_id = $sku['skuId'] ?? '';
                $name = $sku['name'] ?? 'Paquete de datos';
                $price = floatval($sku['settlementPrice'] ?? 0);
                
                if(empty($sku_id) || $price <= 0) continue;
                
                // Buscar si existe producto WC para este SKU
                $products = get_posts([
                    'post_type' => 'product',
                    'meta_key' => '_bc_sku_id',
                    'meta_value' => $sku_id,
                    'posts_per_page' => 1
                ]);
                
                if(!empty($products)){
                    $product = wc_get_product($products[0]->ID);
                    $plans[] = [
                        'id' => $product->get_id(),
                        'sku_id' => $sku_id,
                        'name' => $product->get_name(),
                        'desc' => $product->get_short_description(),
                        'price' => floatval($product->get_price()),
                        'url' => get_permalink($product->get_id())
                    ];
                } else {
                    // Si no existe producto WC, crear entrada temporal
                    $plans[] = [
                        'id' => 0,
                        'sku_id' => $sku_id,
                        'name' => $name,
                        'desc' => '',
                        'price' => $price,
                        'url' => '#' // Necesita crear producto o comprar directamente
                    ];
                }
            }
        }
        
        if(empty($plans)){
            wp_send_json_error(['msg' => 'Paquetes encontrados en la API pero no están sincronizados en la tienda. Contacta al administrador.']);
        }
        
        wp_send_json_success(['plans' => $plans]);
    }
}
