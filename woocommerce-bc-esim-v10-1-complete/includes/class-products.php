<?php
class BC_Products_V10{
    public static function init(){
        // No hay hooks adicionales, solo sincronización
    }
    
    public static function sync(){
        error_log('BC eSIM v10: Iniciando sincronización completa');
        
        $plans = BC_API_V10::get_plans();
        $prices = BC_API_V10::get_prices();
        
        if(!$plans['success']){
            error_log('BC eSIM: Error F002 - ' . ($plans['message'] ?? 'desconocido'));
            return ['success' => false, 'count' => 0];
        }
        
        if(!$prices['success']){
            error_log('BC eSIM: Error F003 - ' . ($prices['message'] ?? 'desconocido'));
            return ['success' => false, 'count' => 0];
        }
        
        $plans_data = $plans['data'];
        $prices_data = $prices['data'];
        
        error_log('BC eSIM: F002 devolvió ' . count($plans_data) . ' planes');
        error_log('BC eSIM: F003 devolvió ' . count($prices_data) . ' precios');
        
        // Indexar precios por SKU
        $prices_index = [];
        foreach($prices_data as $p){
            if(isset($p['skuId'])){
                $prices_index[$p['skuId']] = $p['price'];
            }
        }
        
        $count = 0;
        
        foreach($plans_data as $plan){
            // Excluir solo productos físicos (212)
            if(isset($plan['type']) && $plan['type'] === '212'){
                error_log('BC eSIM: Saltando producto físico SKU ' . ($plan['skuId'] ?? 'sin ID'));
                continue;
            }
            
            $sku_id = $plan['skuId'] ?? '';
            if(empty($sku_id)){
                error_log('BC eSIM: Plan sin skuId');
                continue;
            }
            
            $price_opts = $prices_index[$sku_id] ?? [];
            
            if(self::create_product($plan, $price_opts)){
                $count++;
            }
        }
        
        error_log('BC eSIM: Sincronización completada - ' . $count . ' productos procesados');
        return ['success' => true, 'count' => $count];
    }
    
    private static function create_product($plan, $price_opts){
        $sku_id = $plan['skuId'];
        
        // 🔍 LOG COMPLETO DEL PLAN
        error_log('BC eSIM: ===== CREANDO PRODUCTO =====');
        error_log('BC eSIM: SKU: ' . $sku_id);
        error_log('BC eSIM: Nombre: ' . ($plan['name'] ?? 'sin nombre'));
        error_log('BC eSIM: planType: ' . ($plan['planType'] ?? 'null'));
        error_log('BC eSIM: capacity: ' . ($plan['capacity'] ?? 'null'));
        error_log('BC eSIM: highFlowSize: ' . ($plan['highFlowSize'] ?? 'null'));
        error_log('BC eSIM: days: ' . ($plan['days'] ?? 'null'));
        error_log('BC eSIM: Opciones de precio: ' . count($price_opts));
        error_log('BC eSIM: ================================');
        
        // Buscar por SKU de WooCommerce
        $existing_id = wc_get_product_id_by_sku('BC-' . $sku_id);
        
        if(!$existing_id){
            // Buscar por meta
            $existing = get_posts([
                'post_type' => 'product',
                'meta_key' => '_bc_sku_id',
                'meta_value' => $sku_id,
                'posts_per_page' => 1,
                'fields' => 'ids'
            ]);
            $existing_id = $existing ? $existing[0] : 0;
        }
        
        $title = $plan['name'] ?? 'BC Product ' . $sku_id;
        
        // Limpiar título de códigos Unicode escapados (\u672c → quitar)
        $title = preg_replace('/\\\\u[0-9a-fA-F]{4}/', '', $title); // Quitar \u672c, \u65e5, etc
        $title = preg_replace('/[^\x20-\x7E\x80-\xFF]/u', '', $title); // Quitar caracteres no imprimibles
        $title = preg_replace('/\s+/', ' ', $title); // Normalizar espacios
        $title = trim($title, ' -');
        
        // Limpiar frases descriptivas innecesarias
        $title = preg_replace('/\s*-?\s*eSIM Carrier of \d+ days/i', '', $title); // "eSIM Carrier of 30 days"
        $title = preg_replace('/\(People\'?s Republic of ([^)]+)\)/i', '$1', $title); // "(People's Republic of China)" → "China"
        
        // Limpiar números sueltos al final (ej: "300MB-512kbps-13" → "300MB-512kbps")
        $title = preg_replace('/-\d+$/', '', $title);
        
        // Re-normalizar después de limpiezas
        $title = preg_replace('/\s+/', ' ', $title);
        $title = trim($title, ' -');
        
        error_log('BC eSIM: SKU ' . $sku_id . ' - Título limpio: "' . $title . '"');
        
        // 🔍 DEBUG: Ver qué datos tenemos
        error_log('BC eSIM: SKU ' . $sku_id . ' - planType: ' . ($plan['planType'] ?? 'null') . ', capacity: ' . ($plan['capacity'] ?? 'null') . ' KB, highFlowSize: ' . ($plan['highFlowSize'] ?? 'null') . ' KB');
        
        // Si el título contiene códigos técnicos, SIEMPRE reconstruirlo con país visible
        $has_technical_codes = preg_match('/(esim[0-9]+|new[0-9]+|tihjj|kbps|[0-9]{4,}|^[0-9])/i', $title);
        
        // SIEMPRE reconstruir si tiene códigos técnicos o está vacío
        if(empty($title) || strlen($title) < 5 || $has_technical_codes){
            $countries = $plan['country'] ?? [];
            
            // CRÍTICO: SIEMPRE obtener el país
            $country_name = '';
            if(!empty($countries)){
                if(count($countries) === 1){
                    $country_name = $countries[0]['name'] ?? '';
                } elseif(count($countries) > 1){
                    // Extraer nombre del plan si es multi-país
                    if(preg_match('/^([A-Za-z]+)\d+/i', $title, $matches)){
                        $region_name = $matches[1];
                        $country_name = $region_name . ' (' . count($countries) . ' países)';
                    } else {
                        $country_name = $countries[0]['name'] . ' +' . (count($countries)-1);
                    }
                }
            }
            
            // Si NO hay país en la API, usar el primer país del array como fallback
            if(empty($country_name) && !empty($countries) && isset($countries[0]['name'])){
                $country_name = $countries[0]['name'];
            }
            
            // Construir datos con GB - SOLO CAMPOS REALES DE LA API
            $gb = '';
            $gb_value = 0;
            
            // 🔍 DEBUG COMPLETO: Ver todos los campos de datos
            error_log('BC eSIM: SKU ' . $sku_id . ' - DATOS COMPLETOS:');
            error_log('  planType: ' . ($plan['planType'] ?? 'null'));
            error_log('  capacity: ' . ($plan['capacity'] ?? 'null'));
            error_log('  highFlowSize: ' . ($plan['highFlowSize'] ?? 'null'));
            error_log('  name: ' . ($plan['name'] ?? 'null'));
            
            // PLAN DIARIO: SIEMPRE usar highFlowSize (KB/día)
            if($plan['planType'] === '1'){
                if(!empty($plan['highFlowSize']) && intval($plan['highFlowSize']) > 0 && $plan['highFlowSize'] != '-1'){
                    // highFlowSize viene en KB, convertir a GB: KB / 1048576 = GB
                    $gb_value = round($plan['highFlowSize'] / 1048576, 2);
                    if($gb_value >= 1){
                        $gb = $gb_value . 'GB/día';
                    } else {
                        $mb_value = round($gb_value * 1024, 0);
                        $gb = $mb_value . 'MB/día';
                    }
                    error_log('BC eSIM: ✅ Diario con highFlowSize: ' . $plan['highFlowSize'] . ' KB = ' . $gb);
                } else {
                    error_log('BC eSIM: ❌ Plan diario SIN highFlowSize válido - valor: ' . ($plan['highFlowSize'] ?? 'null'));
                }
            }
            // PAQUETE: Intentar capacity primero, luego highFlowSize como fallback
            else {
                // Prioridad 1: capacity
                if(!empty($plan['capacity']) && intval($plan['capacity']) > 0 && $plan['capacity'] != '-1'){
                    // capacity viene en KB, convertir a GB: KB / 1048576 = GB
                    $gb_value = round($plan['capacity'] / 1048576, 2);
                    if($gb_value >= 1){
                        $gb = $gb_value . 'GB';
                    } else {
                        $mb_value = round($gb_value * 1024, 0);
                        $gb = $mb_value . 'MB';
                    }
                    error_log('BC eSIM: ✅ Paquete con capacity: ' . $plan['capacity'] . ' KB = ' . $gb);
                }
                // Prioridad 2: highFlowSize (fallback cuando capacity = -1)
                elseif(!empty($plan['highFlowSize']) && intval($plan['highFlowSize']) > 0 && $plan['highFlowSize'] != '-1'){
                    // highFlowSize viene en KB, convertir a GB: KB / 1048576 = GB
                    $gb_value = round($plan['highFlowSize'] / 1048576, 2);
                    if($gb_value >= 1){
                        $gb = $gb_value . 'GB';
                    } else {
                        $mb_value = round($gb_value * 1024, 0);
                        $gb = $mb_value . 'MB';
                    }
                    error_log('BC eSIM: ✅ Paquete con highFlowSize (fallback): ' . $plan['highFlowSize'] . ' KB = ' . $gb);
                } else {
                    error_log('BC eSIM: ❌ Paquete SIN capacity ni highFlowSize válidos - capacity: ' . ($plan['capacity'] ?? 'null') . ', highFlowSize: ' . ($plan['highFlowSize'] ?? 'null'));
                }
            }
            
            // Si no hay GB, construir título solo con país
            if(empty($gb)){
                error_log('BC eSIM: ⚠️ SKU ' . $sku_id . ' sin capacity/highFlowSize válido');
                
                // Construir título con país solamente
                if($country_name){
                    $title = $country_name . ' - eSIM';
                    error_log('BC eSIM: Título construido solo con país: ' . $title);
                } else {
                    // Último fallback: usar título limpio de API si existe
                    if(strlen($title) >= 5){
                        error_log('BC eSIM: Usando título original de API: ' . $title);
                    } else {
                        $title = 'eSIM Plan - SKU ' . $sku_id;
                        error_log('BC eSIM: ⚠️ Título fallback genérico: ' . $title);
                    }
                }
            } else {
                // SIEMPRE construir con país primero
                $title_parts = [];
                if($country_name) {
                    $title_parts[] = $country_name;
                } else {
                    // Fallback: Si no hay país, usar "eSIM Internacional"
                    $title_parts[] = 'eSIM Internacional';
                }
                
                if($gb) $title_parts[] = $gb;
                
                $title = implode(' - ', $title_parts);
                
                error_log('BC eSIM: Título reconstruido: ' . $title . ' (País: ' . $country_name . ', GB: ' . $gb . ')');
            }
        }
        
        $plan_type = $plan['planType'] ?? '0';
        
        error_log('BC eSIM: planType detectado: "' . $plan_type . '"' . ($plan_type === '1' ? ' (DIARIO)' : ' (PAQUETE)'));
        
        // ✅ NUEVO: Guardar TODAS las opciones de precio
        $price_options = [];
        $base_price = 0;
        
        if(is_array($price_opts) && !empty($price_opts)){
            foreach($price_opts as $opt){
                $copies = $opt['copies'] ?? '1';
                $price = floatval($opt['settlementPrice'] ?? 0);
                
                if($price > 0){
                    $price_options[] = [
                        'copies' => $copies,
                        'price' => $price
                    ];
                    
                    // Primera opción como precio base
                    if($base_price == 0){
                        $base_price = $price;
                    }
                }
            }
        }
        
        if($base_price == 0 || empty($price_options)){
            error_log('BC eSIM: SKU ' . $sku_id . ' sin opciones de precio válidas');
            return false;
        }
        
        // ✅ NUEVO: Construir descripción completa
        $description = self::build_full_description($plan);
        
        // ✅ NUEVO: Construir excerpt (características)
        $excerpt = self::build_excerpt($plan);
        
        // ✅ VALIDACIÓN FINAL: Asegurar que el título NUNCA esté vacío
        if(empty($title) || strlen(trim($title)) < 3){
            error_log('BC eSIM: ❌ ERROR - Título vacío para SKU ' . $sku_id . ', usando fallback');
            $title = 'eSIM Plan - SKU ' . $sku_id;
        }
        
        error_log('BC eSIM: ✅ Título FINAL para SKU ' . $sku_id . ': "' . $title . '"');
        
        $data = [
            'post_title' => $title,
            'post_content' => $description,
            'post_excerpt' => $excerpt,
            'post_status' => 'publish',
            'post_type' => 'product'
        ];
        
        if($existing_id){
            $data['ID'] = $existing_id;
            wp_update_post($data);
            $pid = $existing_id;
        } else {
            $pid = wp_insert_post($data);
        }
        
        $product = wc_get_product($pid);
        
        // SKU de WooCommerce
        $product->set_sku('BC-' . $sku_id);
        $product->set_regular_price($base_price);
        $product->set_price($base_price);
        $product->set_virtual(true);
        $product->set_sold_individually(false); // ✅ Permitir dropdown
        
        $product->save();
        
        // ✅ NUEVO: Asignar imagen de bandera del país
        $countries = $plan['country'] ?? [];
        if(!empty($countries) && isset($countries[0]['mcc'])){
            $country_code = strtolower($countries[0]['mcc']);
            $flag_url = 'https://flagcdn.com/w320/' . $country_code . '.png';
            
            // Descargar y asignar como imagen destacada
            $image_id = self::set_product_image_from_url($pid, $flag_url, $title);
            if($image_id){
                set_post_thumbnail($pid, $image_id);
                error_log('BC eSIM: Imagen de bandera asignada para ' . $pid);
            }
        }
        
        // ✅ NUEVO: Guardar opciones en metadata
        update_post_meta($pid, '_bc_product', 'yes');
        update_post_meta($pid, '_bc_sku_id', $sku_id);
        update_post_meta($pid, '_bc_plan_type', $plan_type);
        update_post_meta($pid, '_bc_plan_data', json_encode($plan));
        update_post_meta($pid, '_bc_price_options', json_encode($price_options));
        
        error_log('BC eSIM: Producto ' . $pid . ' guardado - SKU: BC-' . $sku_id . ' - ' . count($price_options) . ' opciones');
        
        return true;
    }
    
    private static function build_full_description($plan){
        $html = '<div class="bc-product-details" style="margin:20px 0;padding:20px;background:#f9f9f9;border-radius:8px;">';
        
        // Badge tipo de plan
        if(isset($plan['planType'])){
            if($plan['planType'] === '1'){
                $html .= '<p style="display:inline-block;background:#0073aa;color:#fff;padding:5px 12px;border-radius:4px;font-size:13px;font-weight:600;margin-bottom:15px;">📅 PLAN DIARIO</p>';
            } else {
                $html .= '<p style="display:inline-block;background:#00a32a;color:#fff;padding:5px 12px;border-radius:4px;font-size:13px;font-weight:600;margin-bottom:15px;">📦 PAQUETE DE DATOS</p>';
            }
        }
        
        // Validez
        if(!empty($plan['validityPeriod'])){
            $html .= '<p style="margin:10px 0;"><strong>⏰ Validez:</strong> ' . $plan['validityPeriod'] . ' días para activar y usar</p>';
        }
        
        // Datos
        if($plan['planType'] === '1' && !empty($plan['highFlowSize']) && intval($plan['highFlowSize']) > 0){
            $gb = round($plan['highFlowSize'] / 1048576, 2);
            $html .= '<p style="margin:10px 0;"><strong>📊 Datos por día:</strong> ' . $gb . ' GB/día</p>';
            error_log('BC eSIM: SKU ' . $plan['skuId'] . ' - highFlowSize: ' . $plan['highFlowSize'] . ' KB -> ' . $gb . ' GB/día');
        } elseif(!empty($plan['capacity']) && intval($plan['capacity']) > 0){
            $gb = round($plan['capacity'] / 1048576, 2);
            $html .= '<p style="margin:10px 0;"><strong>📊 Datos totales:</strong> ' . $gb . ' GB</p>';
            error_log('BC eSIM: SKU ' . $plan['skuId'] . ' - capacity: ' . $plan['capacity'] . ' KB -> ' . $gb . ' GB totales');
        } else {
            error_log('BC eSIM: SKU ' . $plan['skuId'] . ' - SIN DATOS - planType: ' . $plan['planType'] . ', capacity: ' . ($plan['capacity'] ?? 'null') . ', highFlowSize: ' . ($plan['highFlowSize'] ?? 'null'));
        }
        
        // Velocidad reducida
        if(!empty($plan['limitFlowSpeed'])){
            $html .= '<p style="margin:10px 0;"><strong>🐌 Velocidad después del límite:</strong> ' . $plan['limitFlowSpeed'] . ' kbps</p>';
        }
        
        // Países cubiertos
        if(!empty($plan['country']) && is_array($plan['country'])){
            $countries = array_map(function($c){ return $c['name']; }, $plan['country']);
            $html .= '<p style="margin:10px 0;"><strong>🌍 Cobertura:</strong> ' . implode(', ', $countries) . '</p>';
        }
        
        // Operadores
        if(!empty($plan['operatorInfo']) && is_array($plan['operatorInfo'])){
            $operators = array_unique(array_map(function($o){ return $o['operator']; }, $plan['operatorInfo']));
            $html .= '<p style="margin:10px 0;"><strong>📡 Operadores:</strong> ' . implode(', ', $operators) . '</p>';
        }
        
        // APN
        if(!empty($plan['country'][0]['apn'])){
            $html .= '<p style="margin:10px 0;"><strong>🔧 APN:</strong> <code>' . $plan['country'][0]['apn'] . '</code></p>';
        }
        
        // Hotspot
        if(isset($plan['hotspotSupport'])){
            $support = $plan['hotspotSupport'] === '0' ? '✅ Sí' : '❌ No';
            $html .= '<p style="margin:10px 0;"><strong>📶 Hotspot:</strong> ' . $support . '</p>';
        }
        
        // Recargable
        if(isset($plan['rechargeableProduct']) && $plan['rechargeableProduct'] === '1'){
            $html .= '<p style="margin:10px 0;background:#e7f7e7;padding:10px;border-left:4px solid #00a32a;"><strong>🔄 Recargable:</strong> Sí, puedes recargar este eSIM cuando se acaben los datos</p>';
        }
        
        $html .= '</div>';
        
        // ✅ AGREGAR: Instrucciones generales de uso
        $html .= '<div class="bc-instructions" style="margin:30px 0;padding:25px;background:#fff;border:2px solid #0073aa;border-radius:8px;">';
        
        $html .= '<h3 style="color:#0073aa;margin-top:0;">📱 Acerca del producto eSIM</h3>';
        
        $html .= '<div style="background:#f0f8ff;padding:15px;border-radius:6px;margin:15px 0;">';
        $html .= '<p style="margin:5px 0;"><strong>✓</strong> La eSIM es válida durante <strong>90-180 días</strong> a partir de la fecha de compra (según el plan).</p>';
        $html .= '<p style="margin:5px 0;"><strong>✓</strong> Compartir Hotspot: Compatible en la mayoría de planes.</p>';
        $html .= '<p style="margin:5px 0;"><strong>⚠️</strong> La velocidad de red se reducirá después de agotar los datos de alta velocidad.</p>';
        $html .= '</div>';
        
        $html .= '<div style="background:#fff3cd;padding:15px;border-radius:6px;margin:15px 0;border-left:4px solid #ff9800;">';
        $html .= '<p style="margin:0;"><strong>⚠️ Nota para Turquía:</strong> Por favor complete la descarga e instalación de su eSIM <strong>antes de la salida</strong>. Debido a restricciones de políticas locales en Turquía, es posible que no pueda descargar el eSIM después de llegar.</p>';
        $html .= '</div>';
        
        $html .= '<h4 style="color:#0073aa;margin-top:25px;">🔍 Verifica si tu dispositivo es compatible</h4>';
        $html .= '<p>Marca <strong>*#06#</strong> en tu teléfono. Si se muestra un <strong>EID</strong>, tu dispositivo es compatible con eSIM.</p>';
        
        $html .= '<h4 style="color:#0073aa;margin-top:25px;">📧 Cómo obtener tu código QR de eSIM</h4>';
        $html .= '<ol>';
        $html .= '<li>Después de realizar tu pedido, recibirás un <strong>email con tu código QR</strong>.</li>';
        $html .= '<li>También puedes encontrarlo en <strong>Mi Cuenta → Pedidos → Ver pedido</strong>.</li>';
        $html .= '<li>El email incluye el <strong>QR code</strong> y el <strong>código manual</strong> para instalación.</li>';
        $html .= '</ol>';
        
        $html .= '<h4 style="color:#0073aa;margin-top:25px;">💡 Consejos importantes</h4>';
        $html .= '<ol>';
        $html .= '<li><strong>Conexión WiFi requerida:</strong> Necesitas Internet estable para instalar el eSIM.</li>';
        $html .= '<li><strong>Cuándo instalar:</strong> Recomendamos instalar después de llegar a tu destino o antes de abordar.</li>';
        $html .= '<li><strong>Desactiva roaming:</strong> Apaga la itinerancia de datos de tu SIM original para evitar cargos.</li>';
        $html .= '<li><strong>Múltiples SIMs:</strong> Puedes tener varias eSIMs instaladas, pero activa solo la que usarás.</li>';
        $html .= '</ol>';
        
        $html .= '<h4 style="color:#0073aa;margin-top:25px;">📲 Cómo instalar tu eSIM (iPhone)</h4>';
        $html .= '<ol>';
        $html .= '<li>Ve a <strong>Ajustes → Datos móviles → Agregar eSIM</strong>.</li>';
        $html .= '<li>Escanea el <strong>código QR</strong> que recibiste por email.</li>';
        $html .= '<li>O ingresa manualmente la dirección <strong>SM-DP+</strong> y código de activación.</li>';
        $html .= '<li>Cuando veas "¡Bienvenido a Billion Connect!", haz clic en <strong>Aceptar</strong>.</li>';
        $html .= '<li><strong>Importante:</strong> Mantén el eSIM desactivado hasta llegar a tu destino.</li>';
        $html .= '<li>Renombra el eSIM como "BC" o el nombre del país para identificarlo fácilmente.</li>';
        $html .= '</ol>';
        
        $html .= '<h4 style="color:#0073aa;margin-top:25px;">🌍 Al llegar a tu destino</h4>';
        $html .= '<ol>';
        $html .= '<li><strong>Apaga</strong> tu SIM principal (o desactiva datos móviles).</li>';
        $html .= '<li><strong>Activa</strong> SOLAMENTE el eSIM de Billion Connect.</li>';
        $html .= '<li><strong>Activa</strong> "Roaming de datos" en el eSIM.</li>';
        $html .= '<li>El APN se configurará automáticamente. Si no, ingrésalo manualmente (viene en el email).</li>';
        $html .= '<li>¡Listo! Ya tienes Internet en tu destino. 🎉</li>';
        $html .= '</ol>';
        
        $html .= '<div style="background:#e8f5e9;padding:15px;border-radius:6px;margin:20px 0;border-left:4px solid #4caf50;">';
        $html .= '<p style="margin:0;"><strong>✅ Soporte:</strong> Si tienes problemas con la instalación o activación, contáctanos a <strong>soporte@heroesim.com</strong> o por WhatsApp.</p>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    private static function build_excerpt($plan){
        $parts = [];
        
        // CRÍTICO: País/Región PRIMERO
        if(!empty($plan['country']) && is_array($plan['country'])){
            if(count($plan['country']) === 1){
                $parts[] = '🌍 ' . $plan['country'][0]['name'];
            } elseif(count($plan['country']) > 1){
                $first_country = $plan['country'][0]['name'];
                $parts[] = '🌍 ' . $first_country . ' +' . (count($plan['country']) - 1) . ' países';
            }
        }
        
        // Tipo de plan
        if(isset($plan['planType'])){
            if($plan['planType'] === '1'){
                $parts[] = '📅 Plan diario renovable';
            } else {
                $parts[] = '📦 Paquete para todo el viaje';
            }
        }
        
        // Datos
        if($plan['planType'] === '1' && !empty($plan['highFlowSize']) && intval($plan['highFlowSize']) > 0){
            $gb = round($plan['highFlowSize'] / 1048576, 2);
            $parts[] = '📊 ' . $gb . ' GB por día';
        } elseif(!empty($plan['capacity']) && intval($plan['capacity']) > 0){
            $gb = round($plan['capacity'] / 1048576, 2);
            $parts[] = '📊 ' . $gb . ' GB totales';
        }
        
        // Validez
        if(!empty($plan['validityPeriod'])){
            $parts[] = '⏰ Válido ' . $plan['validityPeriod'] . ' días';
        }
        
        // Hotspot
        if(isset($plan['hotspotSupport'])){
            if($plan['hotspotSupport'] === '0'){
                $parts[] = '📶 Compartir WiFi';
            }
        }
        
        // NOTA: NO mostrar velocidad reducida ni información técnica negativa
        
        return implode(' • ', $parts);
    }
    
    private static function set_product_image_from_url($product_id, $image_url, $product_name){
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $tmp = download_url($image_url);
        
        if(is_wp_error($tmp)){
            return false;
        }
        
        $file_array = [
            'name' => basename($image_url),
            'tmp_name' => $tmp
        ];
        
        $id = media_handle_sideload($file_array, $product_id, $product_name);
        
        if(is_wp_error($id)){
            @unlink($file_array['tmp_name']);
            return false;
        }
        
        return $id;
    }
}
