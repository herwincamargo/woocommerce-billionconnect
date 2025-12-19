# 📊 DIFERENCIA ENTRE TIPOS DE PLANES

## Según la API de BillionConnect:

### 📦 PAQUETES DE DATOS (planType="0")

**Características:**
- Datos TOTALES fijos (ej: 3GB totales)
- Opciones de F003 típicas: 7, 15, 30 días
- El cliente compra el paquete completo por X días
- capacity: Datos totales en KB
- Ejemplos:
  * "Europe33-4G-3GB" → 3GB totales
  * Puede elegir: 7 días, 15 días, o 30 días

**Opciones comunes en F003:**
```json
{ "copies": "7", "settlementPrice": "XX.XX" }
{ "copies": "15", "settlementPrice": "XX.XX" }
{ "copies": "30", "settlementPrice": "XX.XX" }
```

### 📅 PLANES DIARIOS (planType="1")

**Características:**
- Datos POR DÍA (ej: 1GB/día)
- Opciones de F003: 1 a 30 días (flexible)
- El cliente elige cuántos días necesita
- highFlowSize: Datos por día en KB
- Ejemplos:
  * "USA-1GB/day" → 1GB cada día
  * Puede elegir: 1, 2, 3, 4, 5... hasta 30 días

**Opciones comunes en F003:**
```json
{ "copies": "1", "settlementPrice": "7.00" }
{ "copies": "2", "settlementPrice": "14.00" }
{ "copies": "3", "settlementPrice": "21.00" }
...
{ "copies": "30", "settlementPrice": "210.00" }
```

## ✅ CONCLUSIÓN

El plugin ya maneja esto correctamente:
- Lee TODAS las opciones de F003
- Las muestra en el dropdown
- El usuario elige la que necesita

No importa si son 3 opciones (7,15,30) o 30 opciones (1-30),
el plugin las importa y muestra todas.

## 🎯 EN LA PRÁCTICA

**Paquete de datos:**
```
Selecciona días:
  7 días → $49.00 USD
  15 días → $89.00 USD
  30 días → $149.00 USD
```

**Plan diario:**
```
Selecciona días:
  1 día → $7.00 USD
  2 días → $14.00 USD
  3 días → $21.00 USD
  ...
  30 días → $210.00 USD
```

El código NO necesita cambios, solo importa lo que la API devuelve.
