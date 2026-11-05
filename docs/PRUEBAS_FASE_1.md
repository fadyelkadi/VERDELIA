# Registro de pruebas — Fase 1

Fecha: 2026-11-03. Esta fase entrega vistas y estados de prototipo; no implementa persistencia, autenticación, checkout real ni CRUD.

Registro histórico del prototipo inicial. Las páginas y flujos se conectaron después a MySQL; para el estado actual y pruebas de punta a punta, consulta [`PRUEBAS_FINALES.md`](PRUEBAS_FINALES.md).

## Comprobaciones

| Prueba | Estado | Resultado |
|---|---|---|
| Lint de todos los archivos PHP | Aprobado. | `php -l` pasó en los 17 archivos PHP de `app/` y `public/`. |
| Sintaxis JavaScript | Aprobado. | `node --check public/assets/js/site.js` pasó; Node se usó solo como comprobador, no es requisito de ejecución. |
| Rutas/páginas declaradas | Aprobado en CLI. | Se renderizaron 21 rutas, incluidas tienda, cuatro categorías, producto, carrito, login/registro, checkout, páginas institucionales, administración/subpáginas y 404; sin avisos PHP. |
| Página en servidor local | Aprobada en navegador. | PHP integrado respondió HTTP 200 en `127.0.0.1:18088`; inicio, catálogo, ficha y carrito se abrieron en el navegador. |
| Interacción de carrito | Aprobada como demostración. | Añadir Monstera desde la ficha mostró el mensaje de vista previa; el carrito de la pestaña reflejó el producto y la cantidad. No se escribió en MySQL. |
| Responsive 360/768/1440 | Aprobado visualmente. | Se revisó a 360 y 768 px, y en escritorio a 1024 y 1440 px. Se amplió el breakpoint móvil/tablet a 800 px al detectar desbordamiento horizontal a 768 px; la captura final a 1440 px confirmó la composición de escritorio. |
| Recursos locales sin internet | Aprobado por revisión del HTML y captura. | 12 imágenes PNG, logo SVG y favicon SVG dentro de `public/assets`; los productos se ven en el navegador. No hay recursos remotos en runtime. |
| Formularios, navegación completa y consola | Parcial. | Hay validación y mensajes demo implementados; no se hizo un recorrido individual de todos los formularios/enlaces ni una auditoría de consola. |
| Arranque/DB y respaldo Fase 0 | Sin cambios; integración pendiente. | Se conservaron scripts y `database/backups/verdelia_demo.sql`. La conexión real de XAMPP/MySQL y las pruebas de restauración siguen pendientes de Fase 0. |

## Guion de navegador pendiente

1. Inicia XAMPP/MySQL si se desea comprobar también el bootstrap y ejecuta `INICIAR_VERDELIA.bat`.
2. Revisa Inicio, Tienda, cada filtro, detalle, login/registro, carrito, checkout, menú móvil, administración y páginas institucionales.
3. Prueba ancho 360, 768 y 1440 px; navega por teclado y confirma que no aparece scroll horizontal.
4. Intenta añadir un producto al carrito: debe mostrarse como vista previa de pestaña y nunca como pedido guardado.
5. Envía formularios de ejemplo: solo deben mostrar sus mensajes demo.
6. Recorre todos los enlaces y formularios, y revisa la consola antes de aprobar la revisión funcional completa.
