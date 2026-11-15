# Asistente de VERDELIA

El chat de la tienda funciona con el catálogo y el historial de VERDELIA. Los visitantes y clientes pueden usarlo sin iniciar sesión.

- «¿Cuánto cuesta la Monstera?» consulta el precio vigente de la ficha.
- «¿Hay stock de Sansevieria?» consulta las unidades disponibles.
- «¿Y cuántas quedan?» usa el último producto identificado. La conversación se conserva al navegar por la tienda en la misma pestaña.
- «Recomiéndame una planta sin sol directo por menos de $20» filtra por luz y presupuesto en USD. Si ninguna planta cumple las condiciones, el chat lo indica.
- Para consultas sobre seguridad de mascotas o cuidados que la ficha no especifica, el chat explica qué información falta.
- «¿Cuál es la tendencia de demanda del Aloe?» usa regresión lineal sobre seis meses completos de ventas confirmadas y estima el primer mes posterior a ese historial. Si faltan observaciones o se pide otro período, el chat lo indica. El historial inicial contiene pedidos con pago simulado.

## Ejecutar en Windows

Extrae el ZIP completo, inicia MySQL en XAMPP y ejecuta `INICIAR_VERDELIA.bat`. Si el doble clic no abre la consola, sigue los pasos de [instalación con XAMPP](../INSTALACION_URBE.md). Puedes consultar el pronóstico con el motor PHP incluido.

Para usar el motor Python en una PC que ya tiene Python 3, abre CMD en la carpeta de VERDELIA y ejecuta:

```bat
set "VERDELIA_PYTHON_EXE=C:\ruta\a\python.exe"
INICIAR_VERDELIA.bat
```

Sustituye la ruta por la de tu ejecutable. Puedes obtenerla con `python -c "import sys; print(sys.executable)"`. Python recibe la serie mensual de unidades y calcula la pendiente y proyección por mínimos cuadrados con su biblioteca estándar. Si el proceso falla, el servidor usa el mismo método en PHP. El chat funciona con XAMPP sin servicios externos.
