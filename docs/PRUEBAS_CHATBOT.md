# Pruebas del chatbot

Validación del 15 de noviembre de 2026. ZIP extraído en Windows en una ruta con espacios y paréntesis. Arranque mediante el BAT con PHP 8.0.28, MariaDB 10.4.32 en una instancia temporal y Python 3.14.2 para el motor opcional.

- **Catálogo y recomendaciones.** Se verificaron consultas por nombres con tilde y preposiciones, SKU creados en administración, cambios de precio y stock, exclusión de plantas agotadas de las recomendaciones y exclusión de productos desactivados.
- **Regresión y recuperación.** Python y PHP devolvieron la misma estimación para el Aloe. También se comprobó el aviso ante historial insuficiente y el respaldo PHP ante salida inválida de Python o al vencer el tiempo de espera.
- **Validación HTTP.** El chat rechazó peticiones GET, consultas vacías, JSON inválido, mensajes demasiado largos y tokens ausentes o de otra sesión.
- **Navegador.** Se enviaron consultas con el teclado, se verificó el foco al cerrar con Escape y se siguió el enlace a la ficha recomendada. El panel se ajustó para quedar dentro de un área móvil de 375 × 667 píxeles con barra de desplazamiento.

Las 13 suites PHP de `tests/` pasaron con datos de demostración. Para la prueba transaccional de cuentas se incorporó su respaldo histórico únicamente en la extracción temporal de QA. La suite `admin_http_integration.php` incluye las comprobaciones que cubren los fallos encontrados de nombre completo y SKU.

La prueba se hizo en el equipo de desarrollo. La ejecución de este ZIP actualizado en una PC de URBE queda por comprobar.
