# 📋 planType - Explicación Completa

## ✅ Valores según API BillionConnect:

### planType = "0" - PAQUETE TOTAL
```json
{
  "skuName": "Australia-4G-Optional-300MB",
  "planType": "0",
  "copies": "5",
  "totalDays": "5",
  "capacity": "307200"  // 300MB totales
}
```
**Significado:** 300MB TOTALES válidos por 5 días

### planType = "1" - PLAN DIARIO
```json
{
  "skuName": "Japan-4G-300MB/day",
  "planType": "1",
  "copies": "2",
  "totalDays": "2",
  "highFlowSize": "307200"  // 300MB por día
}
```
**Significado:** 300MB POR DÍA durante 2 días

## 🔑 copies SIEMPRE = DÍAS

En ambos casos, `copies` representa **días de servicio**:

- planType="0" con copies=7 → **7 días** de paquete total
- planType="1" con copies=7 → **7 días** de plan diario

## ✅ Por eso el selector SIEMPRE dice "días"

```
Selecciona días:  ✅ CORRECTO
  1 día - $7.00 USD
  2 días - $8.20 USD
  3 días - $9.40 USD
  ...
```

NO:
```
Selecciona paquetes:  ❌ INCORRECTO
  1 paquete - $7.00 USD
  2 paquetes - $8.20 USD
```

## 📊 Diferencia en la descripción:

**planType="0":**
- "📦 PAQUETE DE DATOS"
- "3GB totales"

**planType="1":**
- "📅 PLAN DIARIO"
- "1GB por día"

Pero el selector SIEMPRE muestra "días" porque copies = días.
