# Asistente de VERDELIA

El chat de la tienda funciona con el catálogo y el historial de VERDELIA. Los visitantes y clientes pueden usarlo sin iniciar sesión.

- «¿Cuánto cuesta la Monstera?» consulta el precio vigente de la ficha.
- «¿Hay stock de Sansevieria?» consulta las unidades disponibles.
- «Recomiéndame una planta para poca luz y de cuidado fácil» ordena plantas disponibles según su grado de compatibilidad con la luz y el cuidado indicados. La respuesta enlaza a las fichas para revisar sus condiciones.
- «¿Cuál es la tendencia de demanda del Aloe?» estima unidades vendidas en el próximo período mensual usando regresión lineal sobre el historial de ventas confirmadas. Si faltan observaciones, el chat lo indica. Los pedidos de la demo son simulados.

## Ejecutar en URBE

Extrae el ZIP completo, inicia MySQL en XAMPP y ejecuta `INICIAR_VERDELIA.bat`. Si el doble clic no abre la consola, sigue los pasos de [instalación en URBE](../INSTALACION_URBE.md). Puedes consultar el pronóstico con el motor PHP incluido.

Para usar el motor Python en una PC que ya tiene Python 3, abre CMD en la carpeta de VERDELIA y ejecuta:

```bat
set "VERDELIA_PYTHON_EXE=C:\ruta\a\python.exe"
INICIAR_VERDELIA.bat
```

Sustituye la ruta por la de tu ejecutable. Puedes obtenerla con `python -c "import sys; print(sys.executable)"`. Python recibe la serie mensual de unidades y calcula la pendiente y proyección por mínimos cuadrados con su biblioteca estándar. Si el proceso falla, el servidor usa el mismo método en PHP. El chat funciona con XAMPP sin servicios externos.
