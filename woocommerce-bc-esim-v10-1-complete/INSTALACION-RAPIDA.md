# 🚀 INSTALACIÓN RÁPIDA v10.1

## PASO 1: Instalar Plugin
1. Desactivar versión anterior
2. Subir `woocommerce-bc-esim-v10-1-complete.zip`
3. Activar plugin

## PASO 2: Configurar
1. Ir a **BC eSIM** en el menú
2. Configurar:
   - AppKey: Hero
   - AppSecret: (tu secret)
   - Entorno: test o prod
3. Guardar

## PASO 3: Configurar Webhook en BillionConnect
1. URL: `https://tusitio.com/wp-json/bcesim/v1/webhook`
2. Copiar desde el panel del plugin

## PASO 4: Sincronizar
1. Click "🔄 Sincronizar"
2. Esperar proceso
3. Verificar en debug.log: "Sincronización completada - X productos"
   (X = cantidad de productos en tu cuenta BC)

## PASO 5: Agregar Shortcodes
**Grid de países:**
```
[bcesim_destinations]
```

**Página de recargas:**
```
[bcesim_recharge]
```

## PASO 6: Probar
1. Ve al grid
2. Click en un país
3. Elige un plan
4. Verifica que aparece el **dropdown de días**
5. Cambia la cantidad de días
6. Verifica que el **precio se actualiza**
7. Añade al carrito
8. Completa compra
9. Espera email con QR

## ✅ VERIFICACIÓN

- [ ] Productos sincronizados (revisa cantidad en log)
- [ ] Grid muestra países
- [ ] Modal funciona con filtros
- [ ] Dropdown aparece en página producto
- [ ] Precio actualiza al cambiar días
- [ ] Descripción completa visible
- [ ] Carrito funciona
- [ ] Orden se crea (revisa debug.log)
- [ ] Webhook recibe QR
- [ ] Email llega con QR

## 🐛 SI ALGO FALLA

1. Activa debug en wp-config.php:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

2. Revisa `/wp-content/debug.log`

3. Busca:
- "BC eSIM: F002 devolvió X planes"
- "BC eSIM: Sincronización completada - X productos"
- "BC eSIM: F040 orden creada"
- "BC eSIM: N009 webhook recibido"
- "BC eSIM: Email enviado"

## 📊 CANTIDAD DE PRODUCTOS

El plugin importa TODOS los productos eSIM de tu cuenta BC.
La cantidad varía según tu catálogo en BillionConnect.
Revisa el log para ver cuántos se importaron.

## 📧 NOTA SOBRE EMAILS

Si no llegan emails de BC:
1. BC envía QR via webhook N009 (no email directo)
2. Nuestro plugin recibe N009
3. Nuestro plugin envía email al cliente
4. Verifica en debug.log: "N009 webhook recibido"
5. Verifica: "Email enviado a: cliente@ejemplo.com"

¡LISTO! 🎉
