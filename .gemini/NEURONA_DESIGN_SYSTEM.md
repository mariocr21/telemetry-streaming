# 🎨 NEURONA DESIGN SYSTEM
## Sistema de Diseño Visual para Telemetría Off-Road

> **Filosofía:** "Datos que respiran, no solo se muestran"  
> **Diferenciador:** Layouts dinámicos + estética funcional racing  
> **Fecha:** 31 Diciembre 2025

---

## 🧬 DNA VISUAL DE NEURONA

### ¿Qué nos hace ÚNICOS vs Competencia?

| Aspecto | Starstream | **NEURONA** |
|---------|------------|-------------|
| Layout | Fijo, mismo para todos | **🔥 Dinámico, configurable por vehículo** |
| Mapa | Modal popup | **🗺️ INTEGRADO en pantalla principal** |
| Widgets | Predefinidos | **🧩 Mix & Match por el usuario** |
| Sensores | Lista fija | **🔗 Binding dinámico a cualquier sensor** |
| Temas | Uno solo | **🎨 Múltiples temas personalizables** |

---

## 🎯 PRINCIPIOS DE DISEÑO NEURONA

### 1. **"Datos que Respiran"**
Los valores no son estáticos - **pulsan, fluyen, reaccionan**.
- Micro-animaciones sutiles al cambiar valores
- Transiciones suaves (no saltos bruscos)
- Los valores críticos "llaman la atención" sin ser molestos

### 2. **"Jerarquía por Importancia"**
No todo es igualmente importante. El piloto debe ver:
1. **LO CRÍTICO** → Grande, central, colores de alerta
2. **LO OPERATIVO** → Mediano, visible, colores neutros
3. **LO INFORMATIVO** → Pequeño, periférico, bajo contraste

### 3. **"El Mapa es el Corazón"**
A diferencia de la competencia (que lo esconde en modal):
- El mapa siempre visible como elemento HERO
- Muestra la ruta, posición, velocidad en contexto geográfico
- Es nuestro diferenciador visual #1

### 4. **"Flexibilidad sobre Rigidez"**
El usuario controla su experiencia:
- Arrastrar widgets donde quiera
- Elegir qué tipo de visualización para cada sensor
- Guardar configuraciones por vehículo

---

## 🌈 PALETA DE COLORES NEURONA

### Colores Base (Dark Theme)
```css
/* Fondos */
--neurona-bg-deep:      #0a0a0f       /* Fondo principal - casi negro con tinte azul */
--neurona-bg-card:      #12121a       /* Cards - elevación sutil */
--neurona-bg-elevated:  #1a1a28       /* Elementos flotantes */

/* Superficie */
--neurona-surface-dim:  rgba(255,255,255, 0.03)  /* Bordes casi invisibles */
--neurona-surface-low:  rgba(255,255,255, 0.06)  /* Separadores */
--neurona-surface-med:  rgba(255,255,255, 0.12)  /* Hover states */

/* Texto */
--neurona-text-primary:   rgba(255,255,255, 0.95)
--neurona-text-secondary: rgba(255,255,255, 0.60)
--neurona-text-muted:     rgba(255,255,255, 0.35)
```

### Colores de Acento (Únicos de Neurona)
```css
/* Verde Eléctrico - Color Principal */
--neurona-primary:     #00E5A0    /* Verde menta eléctrico */
--neurona-primary-dim: #00B880    /* Para fondos/glow */

/* Cyan Neón - Secundario */
--neurona-accent:      #00D4FF    /* Cyan brillante para mapas/datos */

/* Dorado Industrial - Highlight */
--neurona-gold:        #FFB800    /* Para valores importantes */
```

### Colores Semánticos (Estado de Datos)
```css
/* Sistema de Zonas de Temperatura/Presión - estilo único */
--zone-cold:     #00B4D8    /* Azul frío - bajo lo normal */
--zone-optimal:  #00E5A0    /* Verde eléctrico - perfecto */
--zone-warm:     #FFB800    /* Dorado - calentando */
--zone-hot:      #FF6B35    /* Naranja - alto */
--zone-critical: #FF3366    /* Rosa/Rojo - peligro */
```

---

## 📐 TIPOGRAFÍA NEURONA

### Font Stack
```css
/* Valores numéricos - MONOSPACE para estabilidad */
font-family: 'JetBrains Mono', 'SF Mono', 'Fira Code', monospace;

/* Labels y UI */
font-family: 'Inter', 'SF Pro', -apple-system, sans-serif;
```

### Escala de Tamaños para Valores
```css
/* Hero Value - RPM, Speed (lo más importante) */
.value-hero { font-size: clamp(2.5rem, 5vw, 4rem); font-weight: 900; }

/* Primary Value - Temperaturas, Presiones */
.value-primary { font-size: clamp(1.5rem, 3vw, 2.5rem); font-weight: 700; }

/* Secondary Value - Voltaje, Corriente */
.value-secondary { font-size: clamp(1rem, 2vw, 1.5rem); font-weight: 600; }

/* Compact Value - En grids pequeños */
.value-compact { font-size: clamp(0.875rem, 1.5vw, 1.25rem); font-weight: 600; }
```

---

## 🧩 COMPONENTES CORE - ESTILO NEURONA

### 1. **Value Block** (Nuevo - Reemplaza TextGridWidget básico)
```
┌────────────────────────┐
│ ·· COOLANT             │  ← Label pequeño con dot indicator
│                        │
│      185°              │  ← Valor grande con color de zona
│                        │
│  ▁▂▃▄▅ 180-220         │  ← Mini sparkline opcional + rango
└────────────────────────┘
```
- Dot indicator cambia de color según zona
- Valor central GRANDE con color semántico
- Opcional: mini gráfico histórico debajo

### 2. **Radial Gauge NEURONA** (Mejora del actual)
```
        ╭───────────╮
       ╱   \   /     ╲       ← Arco con segmentos de zona
      │   2847      │       ← Valor central bold
      │    rpm      │       ← Unit pequeño
       ╲    ◆      ╱         ← Indicador de posición
        ╰───────────╯
         RPM                  ← Label debajo
```
- Arco segmentado con colores de zona (no gradiente continuo)
- Indicador de posición tipo "needle" moderno
- Valor digital sobrepuesto

### 3. **Gear Indicator NEURONA** (Único)
```
┌─────────────────────────────┐
│                             │
│    ◄  3  ►                  │  ← Número con flechas de contexto
│         GEAR                │
│  1  2  [3]  4  5  6        │  ← Escala visual de marchas
└─────────────────────────────┘
```
- Muestra la marcha actual Y el contexto (gears disponibles)
- Flechas indican que hay más arriba/abajo
- Escala visual muestra progresión

### 4. **Progress Bar NEURONA** (Mejora de LinearBar)
```
┌─────────────────────────────────────────┐
│ FUEL PRESSURE                    63 PSI │
│ ╔═════════════════════╗░░░░░░░░░░░░░░░ │  ← Barra segmentada
│ 0                                   100 │
└─────────────────────────────────────────┘
```
- Barra con segmentos/marcas (no lisa)
- Valor alineado a la derecha
- Marcas cada 20% o 25%

### 5. **Status Pill** (Para conexión, GPS, etc.)
```
┌────────────────┐
│ ● GPS LOCK     │  ← Pill con dot animado
└────────────────┘
```
- Dot pulsa cuando está activo
- Colores: Verde=OK, Amarillo=Conectando, Rojo=Error

---

## 🗂️ LAYOUTS PREDEFINIDOS NEURONA

### Layout 1: **"RACE FOCUS"**
```
┌─────────────────────────────────────────────────────────┐
│                    SHIFT LIGHTS                          │
├────────────────────────────────┬────────────────────────┤
│                                │ RPM    Speed   GEAR    │
│         MAP (65%)              │ ═══════════════════    │
│                                │ Temps   Fuel   Battery │
├────────────────────────────────┴────────────────────────┤
│  [Tire Temps]  [Pressures]  [Suspension]  [Custom]      │
└─────────────────────────────────────────────────────────┘
```
- Mapa dominante
- Sidebar con datos críticos de carrera
- Zona inferior para monitoreo secundario

### Layout 2: **"ENGINE MONITOR"**
```
┌─────────────────────────────────────────────────────────┐
│            RPM                    SPEED                  │
│         ╭─────╮                 ╭─────╮                 │
│        │ 4500 │               │ 67   │                 │
│         ╰─────╯                 ╰─────╯                 │
├────────────────────────────────┬────────────────────────┤
│ Coolant │ Oil │ Trans │ Intake │         MAP            │
│   185   │ 210 │  175  │  95    │                        │
├─────────────────────────────────┴───────────────────────┤
│   Oil Press │ Fuel Press │ Voltage │ Current │ Gear    │
└─────────────────────────────────────────────────────────┘
```
- Gauges de RPM/Speed prominentes
- Mapa secundario
- Enfoque en métricas de motor

### Layout 3: **"MINIMAL"**
```
┌─────────────────────────────────────────────────────────┐
│                                                          │
│              MAP (80% del espacio)                       │
│                                                          │
│  ┌──────┬──────┬──────┬──────┐                          │
│  │ SPD  │ GEAR │ TEMP │ FUEL │  ← Overlay sobre mapa    │
│  └──────┴──────┴──────┴──────┘                          │
└─────────────────────────────────────────────────────────┘
```
- Mapa máximo protagonismo
- Datos críticos en overlay semitransparente

---

## 🎬 ANIMACIONES Y TRANSICIONES

### Valores que Cambian
```css
/* Transición suave de números */
transition: color 0.3s ease-out;

/* Pulse sutil cuando cambia value */
@keyframes value-update {
  0% { transform: scale(1); }
  50% { transform: scale(1.02); }
  100% { transform: scale(1); }
}
```

### Estados Críticos
```css
/* Glow pulsante para valores críticos */
@keyframes critical-pulse {
  0%, 100% { 
    box-shadow: 0 0 10px var(--zone-critical);
  }
  50% { 
    box-shadow: 0 0 25px var(--zone-critical);
  }
}
```

### Transiciones de Zona
```css
/* Cambio de color suave entre zonas */
transition: 
  color 0.5s cubic-bezier(0.4, 0, 0.2, 1),
  background-color 0.5s cubic-bezier(0.4, 0, 0.2, 1);
```

---

## ✅ CHECKLIST DE IMPLEMENTACIÓN

### Fase 1: Fundamentos
- [ ] Implementar variables CSS del Design System
- [ ] Crear archivo `neurona-theme.css`
- [ ] Aplicar fuentes (JetBrains Mono, Inter)

### Fase 2: Componentes Core
- [ ] **ValueBlockWidget** - Nuevo componente estilo Neurona
- [ ] **RadialGaugeNeurona** - Mejora del actual
- [ ] **GearIndicatorWidget** - Nuevo con escala visual
- [ ] **ProgressBarNeurona** - Mejora con segmentos

### Fase 3: Layouts
- [ ] Plantillas de layout "Race Focus"
- [ ] Plantillas de layout "Engine Monitor"  
- [ ] Plantillas de layout "Minimal"

### Fase 4: Polish
- [ ] Animaciones de valores
- [ ] Estados críticos con glow
- [ ] Transiciones suaves

---

## 🆚 COMPARATIVA FINAL

| Característica | Starstream | **NEURONA** |
|----------------|-----------|-------------|
| Colores | Naranja/Verde genérico | Verde eléctrico único + gold |
| Layout | Rígido | Dinámico y personalizable |
| Mapa | Escondido | Protagonista |
| Widgets | Preset fijo | Catálogo + binding libre |
| Gear | Número simple | Escala visual de marchas |
| Temps | Cuadros con color | Value Blocks con sparkline |
| Animaciones | Básicas | Micro-interacciones pulidas |

---

*"No competimos copiando. Competimos innovando."*

