=== WooCommerce BC eSIM v10.1 Complete ===
Version: 10.1.0
Requires WooCommerce: 5.0+

🚀 PLUGIN COMPLETO Y FUNCIONAL

CARACTERÍSTICAS:
✅ Importa TODOS los productos de la API BillionConnect
✅ Dropdown con opciones de F003 en página producto
✅ Descripción completa de productos
✅ F040 - Crear órdenes REALES en BillionConnect
✅ N009 - Webhook recibe QR automáticamente
✅ Emails automáticos con QR al cliente
✅ F052 - Sistema de recargas
✅ Grid de países + Modal con filtros

INSTALACIÓN:

1. Desactivar versión anterior
2. Subir y activar este plugin
3. Configurar en "BC eSIM":
   - AppKey
   - AppSecret
   - Entorno (test/prod)
4. Configurar Webhook en BillionConnect:
   URL: https://tusitio.com/wp-json/bcesim/v1/webhook
5. Sincronizar catálogo
6. Verificar cantidad de productos en debug.log
7. Shortcodes:
   [bcesim_destinations] - Grid de países
   [bcesim_recharge] - Sistema recargas

FLUJO COMPLETO:

COMPRA:
Usuario → Grid → Modal → Producto → Dropdown → Carrito → Pago
→ F040 crea orden → N009 recibe QR → Email con QR → Cliente

RECARGA:
Usuario → [bcesim_recharge] → Ingresa ICCID → F052 busca planes
→ Selecciona plan → Carrito → Pago → F040 recarga

DEBUG:
Activa WP_DEBUG en wp-config.php
Revisa /wp-content/debug.log
Busca: "BC eSIM: Sincronización completada - X productos"

ARCHIVOS INCLUIDOS:
✅ includes/class-admin.php
✅ includes/class-api.php (F001,F002,F003,F040,F052,N009)
✅ includes/class-products.php (catálogo completo)
✅ includes/class-cart.php (dropdown)
✅ includes/class-orders.php (F040)
✅ includes/class-webhook.php (N009)
✅ includes/class-emails.php
✅ includes/class-recharge.php (F052)
✅ assets/js/frontend.js (grid+modal)
✅ assets/js/product-selector.js (dropdown)
✅ assets/css/frontend.css

¡TODO LISTO PARA PRODUCCIÓN!
