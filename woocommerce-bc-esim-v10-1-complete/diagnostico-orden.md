# 🔍 DIAGNÓSTICO - ¿Por qué no llega el email?

## ✅ El plugin SÍ llama F040:

El código en `class-orders.php` línea 27 SÍ ejecuta:
```php
BC_API_V10::create_order([...]);
```

## 🔎 POSIBLES RAZONES:

### 1️⃣ **La orden no se está completando**
- ¿Usaste pago de prueba?
- ¿La orden tiene estado "Completada" o "Procesando"?
- Hook: `woocommerce_thankyou` solo se ejecuta al completar pago

### 2️⃣ **F040 está fallando**
- ¿Las credenciales son correctas?
- ¿El SKU existe en BC?
- ¿El campo `bc_copies` se guardó bien?

### 3️⃣ **BC NO envía emails directamente**
- BC envía el QR via webhook N009
- Nuestro plugin recibe N009
- Nuestro plugin envía el email

### 4️⃣ **Webhook no configurado**
- ¿Configuraste la URL del webhook en BC panel?
- URL debe ser: `https://tusitio.com/wp-json/bcesim/v1/webhook`

## 📝 CÓMO DIAGNOSTICAR:

1. **Haz una compra de prueba**
2. **Revisa debug.log** busca:
   ```
   BC eSIM: F040 orden creada - WC:123 BC:456789
   ```
   
3. **Ve a la orden en WooCommerce → Notas**
   Debe decir:
   ```
   ✅ Orden creada en BC: 123456789
   ```

4. **Si NO aparece**, busca:
   ```
   BC eSIM: F040 error - [mensaje]
   ```

5. **Comparte ese log** para ver qué falla

## 🎯 FLUJO CORRECTO:

```
Usuario paga
    ↓
WooCommerce marca orden como "Completada"
    ↓
Hook woocommerce_thankyou
    ↓
Plugin llama F040 con datos
    ↓
BC procesa orden
    ↓
BC envía N009 al webhook (puede tardar minutos)
    ↓
Plugin recibe N009
    ↓
Plugin envía email con QR
```

## ❓ PREGUNTAS:

1. ¿Hiciste una compra completa o solo probaste añadir al carrito?
2. ¿La orden tiene estado "Completada"?
3. ¿Tienes acceso al debug.log?
4. ¿Configuraste el webhook en BC panel?

