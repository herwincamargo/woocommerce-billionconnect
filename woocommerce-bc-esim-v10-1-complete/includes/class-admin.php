<?php
class BC_Admin_V10{
    public static function init(){
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'settings']);
        add_action('admin_post_bc_sync', [__CLASS__, 'sync_handler']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'scripts']);
        add_action('wp_ajax_bc_test', [__CLASS__, 'ajax_test']);
        add_action('admin_notices', [__CLASS__, 'notices']);
    }
    
    public static function menu(){
        add_menu_page('BC eSIM', 'BC eSIM', 'manage_options', 'bcesim', [__CLASS__, 'page'], 'dashicons-smartphone', 56);
        add_submenu_page('bcesim', 'Configuración', 'Configuración', 'manage_options', 'bcesim', [__CLASS__, 'page']);
        add_submenu_page('bcesim', 'Documentación', '📖 Documentación', 'manage_options', 'bcesim-docs', [__CLASS__, 'docs_page']);
    }
    
    public static function settings(){
        register_setting('bcesim_opts', 'bc_appkey');
        register_setting('bcesim_opts', 'bc_appsecret');
        register_setting('bcesim_opts', 'bc_env');
    }
    
    public static function scripts($hook){
        if(strpos($hook, 'bcesim') !== false){
            wp_enqueue_script('bcesim-admin', BCESIM_URL . 'assets/js/admin.js', ['jquery'], '9.0.0', true);
            wp_localize_script('bcesim-admin', 'bcesim_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bcesim_nonce')
            ]);
        }
    }
    
    public static function notices(){
        if(isset($_GET['sync_result']) && $_GET['page'] === 'bcesim'){
            $result = $_GET['sync_result'];
            $count = intval($_GET['sync_count'] ?? 0);
            
            if($result === 'ok'){
                echo '<div class="notice notice-success is-dismissible"><p>✅ Sincronizado: ' . $count . ' productos.</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>❌ Error en sincronización.</p></div>';
            }
        }
    }
    
    public static function page(){
        $total = wp_count_posts('product')->publish;
        $bc = count(get_posts([
            'post_type' => 'product',
            'posts_per_page' => -1,
            'meta_query' => [['key' => '_bc_product', 'value' => 'yes']],
            'fields' => 'ids'
        ]));
        ?>
        <div class="wrap">
            <h1>BC eSIM v9.0 - Final</h1>
            <div class="notice notice-success">
                <p><strong>✅ Versión Final:</strong></p>
                <ul>
                    <li>✅ Solo datos de API (F001, F002, F003)</li>
                    <li>✅ planType de API (no patrones)</li>
                    <li>✅ Paquetes: quantity fija</li>
                    <li>✅ Planes diarios: quantity variable</li>
                    <li>✅ Grid uniforme con flexbox</li>
                </ul>
            </div>
            
            <div style="display:flex;gap:20px;margin-top:20px;">
                <div style="flex:2;">
                    <div class="card">
                        <h2>Configuración</h2>
                        <form method="post" action="<?php echo admin_url('options.php'); ?>">
                            <?php settings_fields('bcesim_opts'); ?>
                            <table class="form-table">
                                <tr>
                                    <th>AppKey</th>
                                    <td><input type="text" name="bc_appkey" value="<?php echo esc_attr(get_option('bc_appkey', 'Hero')); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th>AppSecret</th>
                                    <td><input type="password" name="bc_appsecret" value="<?php echo esc_attr(get_option('bc_appsecret')); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th>Entorno</th>
                                    <td>
                                        <select name="bc_env" class="regular-text">
                                            <option value="test" <?php selected(get_option('bc_env', 'test'), 'test'); ?>>Test</option>
                                            <option value="prod" <?php selected(get_option('bc_env'), 'prod'); ?>>Producción</option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                            <button type="button" class="button" id="bc-test-btn">Probar Conexión</button>
                            <button type="submit" class="button-primary">Guardar</button>
                        </form>
                        <div id="bc-test-result" style="margin-top:15px;"></div>
                    </div>
                    
                    <div class="card" style="margin-top:20px;">
                        <h2>Sincronizar Catálogo</h2>
                        <div style="background:#f0f0f1;padding:15px;border-radius:4px;margin:15px 0;">
                            <strong>Estado:</strong> Total: <?php echo $total; ?> | BC: <?php echo $bc; ?>
                        </div>
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                            <input type="hidden" name="action" value="bc_sync">
                            <?php wp_nonce_field('bc_sync', 'bc_nonce'); ?>
                            <button type="submit" class="button button-large button-primary">🔄 Sincronizar</button>
                        </form>
                    </div>
                </div>
                
                <div style="flex:1;">
                    <div class="card">
                        <h3>📋 Shortcode</h3>
                        <code>[bcesim_destinations]</code>
                        <hr>
                        <h3>🎯 Flujo API</h3>
                        <ol style="font-size:13px;">
                            <li>F001 → Países</li>
                            <li>F002 → Productos</li>
                            <li>F003 → Precios</li>
                            <li>Crear productos WC</li>
                        </ol>
                        <hr>
                        <h3>🛒 Al vender</h3>
                        <p style="font-size:13px;">
                            WC Quantity → planSkuCopies<br>
                            Paquetes: quantity=1<br>
                            Diarios: quantity=1-30
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public static function sync_handler(){
        if(!current_user_can('manage_options')) wp_die('No autorizado');
        check_admin_referer('bc_sync', 'bc_nonce');
        
        $result = BC_Products_V10::sync();
        
        wp_redirect(add_query_arg([
            'page' => 'bcesim',
            'sync_result' => $result['success'] ? 'ok' : 'error',
            'sync_count' => $result['count'] ?? 0
        ], admin_url('admin.php')));
        exit;
    }
    
    public static function ajax_test(){
        check_ajax_referer('bcesim_nonce', 'nonce');
        if(!current_user_can('manage_options')) wp_send_json_error(['msg' => 'No autorizado']);
        
        $test = BC_API_V10::test();
        
        if($test['success']){
            wp_send_json_success(['msg' => '✅ Conectado con la API']);
        } else {
            wp_send_json_error(['msg' => '❌ ' . $test['message']]);
        }
    }
    
    public static function docs_page(){
        $webhook_url = get_rest_url(null, 'bcesim/v1/webhook');
        ?>
        <div class="wrap">
            <h1>📖 Documentación BC eSIM Plugin</h1>
            
            <div class="card" style="max-width:none;">
                <h2>🎯 Características del Plugin</h2>
                <ul style="font-size:15px;line-height:1.8;">
                    <li>✅ Sincronización automática de 74+ productos desde API BillionConnect</li>
                    <li>✅ Grid visual de países con banderas y precios</li>
                    <li>✅ Modal con filtros (Todos / Paquetes / Planes Diarios)</li>
                    <li>✅ Selector de días con preview de precio en tiempo real</li>
                    <li>✅ Títulos limpios (sin códigos Unicode)</li>
                    <li>✅ Descripción completa con instrucciones de instalación</li>
                    <li>✅ Características automáticas (excerpt)</li>
                    <li>✅ Imágenes de banderas automáticas</li>
                    <li>✅ Integración completa con WooCommerce</li>
                    <li>✅ Órdenes automáticas a BillionConnect (F040)</li>
                    <li>✅ Webhook para recibir QR codes (N009)</li>
                    <li>✅ Emails automáticos con QR al cliente</li>
                    <li>✅ Sistema de recargas con Data Add-On (F015)</li>
                </ul>
            </div>
            
            <div class="card" style="max-width:none;margin-top:20px;">
                <h2>🚀 Guía de Inicio Rápido</h2>
                <ol style="font-size:15px;line-height:2;">
                    <li><strong>Configuración:</strong> Ingresa tus credenciales de BillionConnect en BC eSIM → Configuración</li>
                    <li><strong>Sincronización:</strong> Haz clic en "Sincronizar Productos" para importar todos los eSIM</li>
                    <li><strong>Webhook:</strong> Copia esta URL y configúrala en el panel de BillionConnect:<br>
                        <code style="background:#f0f0f0;padding:8px 12px;display:inline-block;margin:10px 0;border-radius:4px;font-size:13px;"><?php echo esc_html($webhook_url); ?></code>
                    </li>
                    <li><strong>Página Principal:</strong> Usa el shortcode <code>[bcesim_grid]</code> para mostrar el catálogo</li>
                    <li><strong>Página de Recargas:</strong> Crea una página y usa <code>[bcesim_recharge]</code></li>
                </ol>
            </div>
            
            <div class="card" style="max-width:none;margin-top:20px;">
                <h2>📋 Shortcodes Disponibles</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:200px;">Shortcode</th>
                            <th>Descripción</th>
                            <th>Uso</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>[bcesim_grid]</code></td>
                            <td>Muestra el catálogo completo de eSIM con grid de países, modal y filtros</td>
                            <td>Página principal de productos eSIM</td>
                        </tr>
                        <tr>
                            <td><code>[bcesim_recharge]</code></td>
                            <td>Formulario para agregar datos (Data Add-On) a un eSIM existente usando ICCID</td>
                            <td>Página dedicada para recargas</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="card" style="max-width:none;margin-top:20px;">
                <h2>🔄 Tipos de Productos</h2>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                    <div style="border:2px solid #00a32a;padding:20px;border-radius:8px;">
                        <h3 style="color:#00a32a;margin-top:0;">📦 Paquetes de Datos (planType="0")</h3>
                        <p><strong>Datos TOTALES</strong> fijos para el período completo</p>
                        <p><strong>Ejemplo:</strong> "Europe33-4G-5GB" = 5GB totales</p>
                        <p><strong>Opciones típicas:</strong> 7, 15, 30 días</p>
                        <p><strong>Usuario elige:</strong> Cuántos días tiene el paquete válido</p>
                    </div>
                    <div style="border:2px solid #0073aa;padding:20px;border-radius:8px;">
                        <h3 style="color:#0073aa;margin-top:0;">📅 Planes Diarios (planType="1")</h3>
                        <p><strong>Datos POR DÍA</strong> renovables cada día</p>
                        <p><strong>Ejemplo:</strong> "USA-1GB/day" = 1GB cada día</p>
                        <p><strong>Opciones típicas:</strong> 1 a 30 días</p>
                        <p><strong>Usuario elige:</strong> Cuántos días necesita</p>
                    </div>
                </div>
            </div>
            
            <div class="card" style="max-width:none;margin-top:20px;">
                <h2>🔧 Flujo Completo de Compra</h2>
                <div style="background:#f0f8ff;padding:20px;border-radius:8px;border-left:4px solid #0073aa;">
                    <ol style="font-size:15px;line-height:2;">
                        <li><strong>Usuario selecciona país</strong> en el grid → Se abre modal</li>
                        <li><strong>Usuario filtra</strong> por tipo (Paquetes/Diarios)</li>
                        <li><strong>Usuario hace clic</strong> en "Ver Detalles" → Va a página del producto</li>
                        <li><strong>Usuario selecciona días</strong> en el dropdown → Ve preview del precio</li>
                        <li><strong>Usuario añade al carrito</strong> y completa pago</li>
                        <li><strong>Plugin llama F040</strong> → Crea orden en BillionConnect</li>
                        <li><strong>BillionConnect procesa</strong> → Genera QR code</li>
                        <li><strong>BC envía webhook N009</strong> → Plugin recibe QR</li>
                        <li><strong>Plugin envía email</strong> → Cliente recibe QR e instrucciones</li>
                        <li><strong>Cliente escanea QR</strong> → eSIM instalado ✅</li>
                    </ol>
                </div>
            </div>
            
            <div class="card" style="max-width:none;margin-top:20px;">
                <h2>🔄 Sistema de Recargas (Data Add-On)</h2>
                <p style="font-size:15px;">El sistema de recargas usa <strong>F015</strong> para consultar paquetes de datos adicionales y <strong>F016</strong> para agregarlos al eSIM existente.</p>
                <div style="background:#fff3cd;padding:20px;border-radius:8px;border-left:4px solid #ff9800;margin-top:15px;">
                    <h4 style="margin-top:0;">⚠️ Diferencia importante:</h4>
                    <ul>
                        <li><strong>F052:</strong> Devuelve <em>nuevos eSIM</em> compatibles (no recarga el mismo)</li>
                        <li><strong>F015:</strong> Devuelve <em>paquetes de datos adicionales</em> (Data Add-On) para agregar al eSIM existente ✅</li>
                    </ul>
                </div>
                <h4>Flujo de recarga:</h4>
                <ol style="font-size:15px;line-height:2;">
                    <li>Usuario entra a página con <code>[bcesim_recharge]</code></li>
                    <li>Ingresa su ICCID</li>
                    <li>Plugin llama <strong>F015</strong> con el ICCID</li>
                    <li>API devuelve paquetes de datos adicionales disponibles</li>
                    <li>Usuario selecciona y compra</li>
                    <li>Plugin llama <strong>F016</strong> para agregar datos</li>
                    <li>Datos agregados al eSIM automáticamente ✅</li>
                </ol>
            </div>
            
            <div class="card" style="max-width:none;margin-top:20px;">
                <h2>🐛 Solución de Problemas</h2>
                <div class="accordion">
                    <h4>❓ Los productos no se sincronizan</h4>
                    <p>Verifica que tus credenciales sean correctas y que la API esté respondiendo. Revisa el <code>debug.log</code> en <code>/wp-content/debug.log</code></p>
                    
                    <h4>❓ El cliente no recibe el email con QR</h4>
                    <p>Verifica que:
                        <ul>
                            <li>El webhook esté configurado correctamente en BillionConnect</li>
                            <li>La orden tenga estado "Completada" o "Procesando"</li>
                            <li>Revisa las notas del pedido en WooCommerce</li>
                            <li>Revisa el <code>debug.log</code> buscando líneas con "F040" y "N009"</li>
                        </ul>
                    </p>
                    
                    <h4>❓ El modal muestra códigos Unicode raros</h4>
                    <p>Asegúrate de tener la versión v10.1.9 o superior que limpia los títulos automáticamente</p>
                    
                    <h4>❓ El dropdown dice "paquetes" en lugar de "días"</h4>
                    <p>Limpia la caché del navegador (Ctrl+Shift+R) o abre en ventana incógnito</p>
                </div>
            </div>
            
            <div class="card" style="max-width:none;margin-top:20px;">
                <h2>📞 Soporte</h2>
                <p style="font-size:15px;">
                    <strong>Versión del plugin:</strong> <?php echo BCESIM_VERSION; ?><br>
                    <strong>Webhook URL:</strong> <code><?php echo esc_html($webhook_url); ?></code><br>
                    <strong>Productos sincronizados:</strong> <?php 
                        $bc_count = count(get_posts([
                            'post_type' => 'product',
                            'posts_per_page' => -1,
                            'meta_query' => [['key' => '_bc_product', 'value' => 'yes']],
                            'fields' => 'ids'
                        ]));
                        echo $bc_count;
                    ?> productos BC eSIM
                </p>
            </div>
        </div>
        <?php
    }
}
