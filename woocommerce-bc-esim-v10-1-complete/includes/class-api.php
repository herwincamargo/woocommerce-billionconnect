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
    
    private static function get_api_url(){
        $env = get_option('bc_env', 'test');
        return $env === 'prod' ? get_option('bc_api_prod_url') : get_option('bc_api_test_url');
    }
    
    private static function make_request($type, $data = []){
        $appkey = get_option('bc_appkey');
        $appsecret = get_option('bc_appsecret');
        
        $body = [
            'tradeType' => $type,
            'tradeTime' => date('Y-m-d H:i:s'),
            'tradeData' => $data
        ];
        
        $body_json = json_encode($body);
        $sign = md5($appsecret . $body_json);
        
        // 🆕 TIMEOUT AUMENTADO para F040 (crear órdenes tarda más)
        $timeout = ($type === 'F040') ? 60 : 30;
        
        error_log('BC eSIM: Llamando ' . $type . ' con timeout ' . $timeout . 's');
        error_log('BC eSIM: URL: ' . self::get_api_url());
        error_log('BC eSIM: Headers: AppKey=' . $appkey);
        
        $start_time = microtime(true);
        
        $response = wp_remote_post(self::get_api_url(), [
            'timeout' => $timeout,
            'headers' => [
                'Content-Type' => 'application/json;charset=UTF-8',
                'x-channel-id' => $appkey,
                'x-sign-method' => 'md5',
                'x-sign-value' => $sign
            ],
            'body' => $body_json,
            'sslverify' => true,
            'httpversion' => '1.1'
        ]);
        
        $elapsed = round(microtime(true) - $start_time, 2);
        error_log('BC eSIM: Tiempo de respuesta: ' . $elapsed . 's');
        
        if(is_wp_error($response)){
            $error_msg = $response->get_error_message();
            error_log('BC eSIM: ❌ Error WP: ' . $error_msg);
            
            // 🆕 Si es timeout, dar información útil
            if(strpos($error_msg, 'timed out') !== false || strpos($error_msg, 'timeout') !== false){
                error_log('BC eSIM: ⚠️ TIMEOUT DETECTADO después de ' . $elapsed . 's');
                error_log('BC eSIM: URL probablemente correcta pero servidor no responde');
                error_log('BC eSIM: Verifica con BillionConnect si tu cuenta puede usar ' . $type);
            }
            
            return ['success' => false, 'message' => $error_msg];
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body_response = wp_remote_retrieve_body($response);
        
        error_log('BC eSIM: HTTP Code: ' . $http_code);
        error_log('BC eSIM: Response length: ' . strlen($body_response) . ' bytes');
        
        if($http_code !== 200){
            error_log('BC eSIM: ❌ HTTP Error ' . $http_code);
            error_log('BC eSIM: Response: ' . substr($body_response, 0, 500));
            return ['success' => false, 'message' => 'HTTP Error ' . $http_code];
        }
        
        $data = json_decode($body_response, true);
        
        if(!$data || !isset($data['tradeCode'])){
            error_log('BC eSIM: ❌ Respuesta API inválida: ' . substr($body_response, 0, 500));
            return ['success' => false, 'message' => 'Respuesta API inválida'];
        }
        
        if($data['tradeCode'] != '1000'){
            error_log('BC eSIM: ❌ API Error Code: ' . $data['tradeCode']);
            error_log('BC eSIM: ❌ API Error Msg: ' . ($data['tradeMsg'] ?? 'Sin mensaje'));
            return ['success' => false, 'message' => $data['tradeMsg'] ?? 'Error desconocido'];
        }
        
        error_log('BC eSIM: ✅ ' . $type . ' exitoso en ' . $elapsed . 's');
        return ['success' => true, 'data' => $data['tradeData'] ?? []];
    }
    
    public static function test(){
        return self::make_request('F001', ['salesMethod' => '5', 'language' => '2']);
    }
    
    public static function get_countries(){
        return self::make_request('F001', ['salesMethod' => '5', 'language' => '2']);
    }
    
    public static function get_plans(){
        return self::make_request('F002', ['salesMethod' => '5', 'language' => '2', 'networkOperatorScope' => '2']);
    }
    
    public static function get_prices(){
        return self::make_request('F003', ['salesMethod' => '5']);
    }
    
    public static function ajax_get_plans(){
        check_ajax_referer('bcesim_nonce', 'nonce');
        
        $country_code = sanitize_text_field($_POST['country_code'] ?? '');
        
        if(empty($country_code)){
            wp_send_json_error(['msg' => 'Código de país requerido']);
        }
        
        $products = get_posts([
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                ['key' => '_bc_product', 'value' => 'yes', 'compare' => '=']
            ]
        ]);
        
        $plans = [];
        
        foreach($products as $post){
            $product = wc_get_product($post->ID);
            if(!$product) continue;
            
            $plan_data = json_decode($product->get_meta('_bc_plan_data'), true);
            if(!$plan_data) continue;
            
            // Verificar si cubre este país
            $countries = $plan_data['country'] ?? [];
            $has_country = false;
            
            foreach($countries as $c){
                if(isset($c['mcc']) && $c['mcc'] === $country_code){
                    $has_country = true;
                    break;
                }
            }
            
            if(!$has_country) continue;
            
            // Construir lista de países cubiertos
            $country_names = [];
            foreach($countries as $c){
                if(isset($c['name'])) $country_names[] = $c['name'];
            }
            $coverage = implode(', ', array_slice($country_names, 0, 3));
            if(count($country_names) > 3){
                $coverage .= ' +' . (count($country_names) - 3);
            }
            
            $plans[] = [
                'id' => $product->get_id(),
                'sku_id' => $product->get_meta('_bc_sku_id'),
                'name' => $product->get_name(), // ✅ Usar título de WC (limpio)
                'desc' => wp_trim_words(strip_tags($product->get_short_description()), 20, '...'), // ✅ Usar excerpt
                'planType' => $plan_data['planType'] ?? '0',
                'days' => $plan_data['days'] ?? '',
                'price' => floatval($product->get_price()),
                'url' => get_permalink($product->get_id()),
                'coverage' => $coverage
            ];
        }
        
        if(empty($plans)){
            wp_send_json_error(['msg' => 'No hay planes disponibles para este destino']);
        }
        
        wp_send_json_success(['plans' => $plans]);
    }
    
    public static function handle_webhook($request){
        $headers = $request->get_headers();
        $body = $request->get_body();
        $appsecret = get_option('bc_appsecret');
        
        $received = $headers['x_sign_value'][0] ?? '';
        $expected = md5($appsecret . $body);
        
        if($received !== $expected){
            return new WP_REST_Response(['error' => 'Firma inválida'], 403);
        }
        
        $data = json_decode($body, true);
        if(isset($data['tradeType']) && $data['tradeType'] === 'N009'){
            self::handle_esim($data);
        }
        
        return new WP_REST_Response(['success' => true], 200);
    }
    
    private static function handle_esim($data){
        $trade = $data['tradeData'] ?? [];
        $order_id = $trade['orderId'] ?? '';
        $esim_list = $trade['esimList'] ?? [];
        
        $orders = wc_get_orders(['meta_key' => '_bc_order_id', 'meta_value' => $order_id, 'limit' => 1]);
        if(empty($orders)) return;
        
        $order = $orders[0];
        $order->update_meta_data('_bc_esim_data', $esim_list);
        $order->add_order_note('eSIM recibido de BillionConnect');
        $order->save();
    }
    
    public static function render_destinations(){
        $result = self::get_countries();
        if(!$result['success']) return '<p>Error al cargar destinos desde la API.</p>';
        
        $countries = $result['data'];
        
        $by_continent = [];
        foreach($countries as $country){
            $continent = $country['continent'] ?? 'Otros';
            if(!isset($by_continent[$continent])) $by_continent[$continent] = [];
            $by_continent[$continent][] = $country;
        }
        
        ob_start();
        ?>
        <div class="bcesim-wrap">
            <div class="bcesim-search">
                <input type="text" id="bcesim-search" placeholder="Buscar destinos...">
                <span class="bcesim-search-icon">🔍</span>
            </div>
            
            <div class="bcesim-continents">
                <button class="bcesim-cont-btn active" data-cont="all">Todos</button>
                <?php foreach(array_keys($by_continent) as $cont): ?>
                <button class="bcesim-cont-btn" data-cont="<?php echo esc_attr($cont); ?>">
                    <?php echo esc_html($cont); ?>
                </button>
                <?php endforeach; ?>
            </div>
            
            <div class="bcesim-grid">
                <?php foreach($countries as $country): ?>
                <div class="bcesim-card" 
                     data-cont="<?php echo esc_attr($country['continent'] ?? 'Otros'); ?>"
                     data-name="<?php echo esc_attr(strtolower($country['name'])); ?>">
                    <?php if(!empty($country['url'])): ?>
                    <div class="bcesim-flag">
                        <img src="<?php echo esc_url($country['url']); ?>" alt="<?php echo esc_attr($country['name']); ?>">
                    </div>
                    <?php endif; ?>
                    <h3><?php echo esc_html($country['name']); ?></h3>
                    <p class="bcesim-from">Desde $<?php echo self::get_min_price($country['mcc']); ?> USD</p>
                    <button class="bcesim-view-btn" 
                            data-country="<?php echo esc_attr($country['mcc']); ?>" 
                            data-name="<?php echo esc_attr($country['name']); ?>">
                        Ver Planes →
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="bcesim-no-results" style="display:none;">
                <p>No se encontraron destinos</p>
            </div>
        </div>
        
        <div id="bcesim-modal" class="bcesim-modal">
            <div class="bcesim-modal-content">
                <span class="bcesim-modal-close">&times;</span>
                <h2>Planes para <span id="bcesim-country-name"></span></h2>
                
                <div class="bcesim-filters">
                    <button class="bcesim-filter-btn active" data-filter="all">Todos</button>
                    <button class="bcesim-filter-btn" data-filter="0">📦 Paquetes de Datos</button>
                    <button class="bcesim-filter-btn" data-filter="1">📅 Planes Diarios</button>
                </div>
                
                <div id="bcesim-plans-container">
                    <div class="bcesim-loading">Cargando planes...</div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private static function get_min_price($mcc){
        $products = get_posts([
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [['key' => '_bc_product', 'value' => 'yes']],
            'fields' => 'ids'
        ]);
        
        $min = 999999;
        
        foreach($products as $pid){
            $product = wc_get_product($pid);
            if(!$product) continue;
            
            $plan_data = json_decode($product->get_meta('_bc_plan_data'), true);
            if(!$plan_data) continue;
            
            $countries = $plan_data['country'] ?? [];
            $has = false;
            foreach($countries as $c){
                if(isset($c['mcc']) && $c['mcc'] === $mcc){
                    $has = true;
                    break;
                }
            }
            
            if($has){
                $price = floatval($product->get_price());
                if($price > 0 && $price < $min) $min = $price;
            }
        }
        
        return $min == 999999 ? '0.00' : number_format($min, 2);
    }
    
    // F040 - Crear orden
    public static function create_order($order_data){
        $appkey = get_option('bc_appkey');
        $appsecret = get_option('bc_appsecret');
        
        // Estructura correcta según API: subOrderList es requerido
        $body = [
            'tradeType' => 'F040',
            'tradeTime' => date('Y-m-d H:i:s'),
            'tradeData' => [
                'channelOrderId' => $order_data['channelOrderId'],
                'email' => $order_data['customerEmail'] ?? '',
                'subOrderList' => [[
                    'channelSubOrderId' => $order_data['channelOrderId'] . '-SUB',
                    'deviceSkuId' => $order_data['deviceSkuId'],
                    'planSkuCopies' => $order_data['planSkuCopies'],
                    'number' => $order_data['number']
                ]]
            ]
        ];
        
        $body_json = json_encode($body);
        $sign = md5($appsecret . $body_json);
        
        error_log('BC eSIM: F040 Request: ' . $body_json);
        
        // 🆕 RETRY LOGIC: Intentar hasta 2 veces si hay timeout
        $max_attempts = 2;
        $attempt = 0;
        $last_error = '';
        
        while($attempt < $max_attempts){
            $attempt++;
            error_log('BC eSIM: F040 Intento ' . $attempt . '/' . $max_attempts);
            
            $start_time = microtime(true);
            
            $response = wp_remote_post(self::get_api_url(), [
                'timeout' => 60, // 🆕 60 segundos para F040
                'headers' => [
                    'Content-Type' => 'application/json;charset=UTF-8',
                    'x-channel-id' => $appkey,
                    'x-sign-method' => 'md5',
                    'x-sign-value' => $sign
                ],
                'body' => $body_json,
                'sslverify' => true,
                'httpversion' => '1.1'
            ]);
            
            $elapsed = round(microtime(true) - $start_time, 2);
            error_log('BC eSIM: F040 Tiempo de respuesta: ' . $elapsed . 's');
            
            if(is_wp_error($response)){
                $last_error = $response->get_error_message();
                error_log('BC eSIM: ❌ F040 Intento ' . $attempt . ' falló: ' . $last_error);
                
                // Si es timeout y quedan intentos, esperar y reintentar
                if((strpos($last_error, 'timed out') !== false || strpos($last_error, 'timeout') !== false) && $attempt < $max_attempts){
                    error_log('BC eSIM: ⏳ Esperando 3 segundos antes de reintentar...');
                    sleep(3);
                    continue;
                }
                
                // Si no es timeout o es el último intento, retornar error
                return ['success' => false, 'message' => $last_error];
            }
            
            // Si llegamos aquí, tenemos respuesta
            $http_code = wp_remote_retrieve_response_code($response);
            $body_response = wp_remote_retrieve_body($response);
            
            error_log('BC eSIM: F040 HTTP Code: ' . $http_code);
            error_log('BC eSIM: F040 Response length: ' . strlen($body_response) . ' bytes');
            
            if($http_code !== 200){
                $last_error = 'HTTP Error ' . $http_code . ': ' . substr($body_response, 0, 200);
                error_log('BC eSIM: ❌ ' . $last_error);
                
                // Si quedan intentos, reintentar
                if($attempt < $max_attempts){
                    error_log('BC eSIM: ⏳ Esperando 3 segundos antes de reintentar...');
                    sleep(3);
                    continue;
                }
                
                return ['success' => false, 'message' => $last_error];
            }
            
            $result = json_decode($body_response, true);
            
            if(!$result || !isset($result['tradeCode'])){
                $last_error = 'Respuesta API inválida: ' . substr($body_response, 0, 200);
                error_log('BC eSIM: ❌ ' . $last_error);
                return ['success' => false, 'message' => $last_error];
            }
            
            if($result['tradeCode'] != '1000'){
                $last_error = $result['tradeMsg'] ?? 'Error desconocido (Code: ' . $result['tradeCode'] . ')';
                error_log('BC eSIM: ❌ F040 Error: ' . $last_error);
                
                // 🆕 Si es error de SKU, no reintentar
                if(strpos($last_error, '不支持') !== false || strpos($last_error, 'not support') !== false){
                    error_log('BC eSIM: ⚠️ Error de SKU no autorizado - no se reintenta');
                    return ['success' => false, 'message' => $last_error];
                }
                
                // Para otros errores, reintentar si quedan intentos
                if($attempt < $max_attempts){
                    error_log('BC eSIM: ⏳ Esperando 3 segundos antes de reintentar...');
                    sleep(3);
                    continue;
                }
                
                return ['success' => false, 'message' => $last_error];
            }
            
            // ✅ ÉXITO
            error_log('BC eSIM: ✅ F040 EXITOSO en intento ' . $attempt . ' (' . $elapsed . 's)');
            return ['success' => true, 'data' => $result['tradeData'] ?? []];
        }
        
        // Si llegamos aquí, agotamos todos los intentos
        return ['success' => false, 'message' => 'Timeout después de ' . $max_attempts . ' intentos: ' . $last_error];
    }
        
        if($result['tradeCode'] != '1000'){
            return ['success' => false, 'message' => $result['tradeMsg'] ?? 'Error desconocido'];
        }
        
        return ['success' => true, 'data' => $result['tradeData'] ?? []];
    }
    
    // F052 - Query recharge SKUs
    public static function query_recharge_skus($iccid){
        $appkey = get_option('bc_appkey');
        $appsecret = get_option('bc_appsecret');
        
        $body = [
            'tradeType' => 'F052',
            'tradeTime' => date('Y-m-d H:i:s'),
            'tradeData' => ['iccid' => $iccid]
        ];
        
        $body_json = json_encode($body);
        $sign = md5($appsecret . $body_json);
        
        $response = wp_remote_post(self::get_api_url(), [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json;charset=UTF-8',
                'x-channel-id' => $appkey,
                'x-sign-method' => 'md5',
                'x-sign-value' => $sign
            ],
            'body' => $body_json
        ]);
        
        if(is_wp_error($response)){
            return ['success' => false, 'message' => $response->get_error_message()];
        }
        
        $result = json_decode(wp_remote_retrieve_body($response), true);
        
        if(!$result || !isset($result['tradeCode'])){
            return ['success' => false, 'message' => 'Respuesta API inválida'];
        }
        
        if($result['tradeCode'] != '1000'){
            return ['success' => false, 'message' => $result['tradeMsg'] ?? 'Error desconocido'];
        }
        
        return ['success' => true, 'data' => $result['tradeData'] ?? []];
    }
    
    // F015 - Query acceleration packages (Data Add-On)
    public static function query_data_addon($iccid, $order_id = '', $day_type = '2'){
        $appkey = get_option('bc_appkey');
        $appsecret = get_option('bc_appsecret');
        $language = get_option('bc_language', '2');
        
        $body = [
            'tradeType' => 'F015',
            'tradeTime' => date('Y-m-d H:i:s'),
            'tradeData' => [[
                'orderId' => $order_id ?: 'TEMP',
                'iccid' => $iccid,
                'dayType' => $day_type, // 0-one day, 1-multi day, 2-data type
                'language' => $language
            ]]
        ];
        
        $body_json = json_encode($body);
        $sign = md5($appsecret . $body_json);
        
        error_log('BC eSIM: F015 Request: ' . $body_json);
        
        $response = wp_remote_post(self::get_api_url(), [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json;charset=UTF-8',
                'x-channel-id' => $appkey,
                'x-sign-method' => 'md5',
                'x-sign-value' => $sign
            ],
            'body' => $body_json
        ]);
        
        if(is_wp_error($response)){
            return ['success' => false, 'message' => $response->get_error_message()];
        }
        
        $result = json_decode(wp_remote_retrieve_body($response), true);
        
        error_log('BC eSIM: F015 Response: ' . json_encode($result));
        
        if(!$result || !isset($result['tradeCode'])){
            return ['success' => false, 'message' => 'Respuesta API inválida'];
        }
        
        if($result['tradeCode'] != '1000'){
            return ['success' => false, 'message' => $result['tradeMsg'] ?? 'Error desconocido'];
        }
        
        return ['success' => true, 'data' => $result['tradeData'] ?? []];
    }
}
