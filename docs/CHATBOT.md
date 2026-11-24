# Asistente de VERDELIA

El chat de la tienda funciona con el catálogo y el historial de VERDELIA. Los visitantes y clientes pueden usarlo sin iniciar sesión.

- «¿Cuánto cuesta la Monstera?» consulta el precio vigente de la ficha.
- «¿Hay stock de Sansevieria?» consulta las unidades disponibles.
- «¿Y cuántas quedan?» usa el último producto identificado. La conversación se conserva al navegar por la tienda en la misma pestaña.
- Después de una recomendación, «¿Cómo se llama esta planta?» o «¿Cuál sería el nombre más apropiado para esta mata?» retoman el producto. Si se mostraron varias opciones, el chat pide elegir una.
- «¿Qué es Suculenta Echeveria?», «¿Cómo se riega la costilla de Adán?» y «¿Cuántos litros caben en la regadera mini?» consultan las mismas guías que aparecen en las fichas. Las guías incluyen nombres comunes y científicos cuando se conocen, cuidados por planta y especificaciones de macetas y accesorios.
- «Quiero un cují» y «Prosopis juliflora» reconocen el mismo árbol y aclaran si no está publicado en el catálogo.
- «Quiero una planta» o «Busco una maceta» muestra opciones disponibles en el primer mensaje. Cada recomendación incluye una imagen, descripción, datos de la ficha y enlace al producto. Si falta un dato útil, el chat hace una pregunta opcional al final y aprovecha la respuesta para filtrar las opciones.
- Si prefieres no indicar un presupuesto, el asistente conserva los demás criterios y muestra las opciones sin aplicar un límite de precio. Las fichas solo presentan las medidas y condiciones que constan en el catálogo.
- «Recomiéndame una planta sin sol directo por menos de Bs. REF 20» filtra por luz, presupuesto en bolívares de referencia y existencias. Cada recomendación muestra precio y unidades disponibles.
- «Quiero una maceta barata» ordena los artículos disponibles por precio. También puedes indicar tamaño y cantidad; si escribes «en total», compara el presupuesto con el subtotal y lo muestra.
- Puedes elegir una tarjeta con «la primera», «la segunda» o «la opción 2» y luego pedir precio, stock o comparar cuidados.
- Las preguntas combinadas sobre producto, precio, existencias y envío se responden con los datos del catálogo y las condiciones de la demostración. Los pedidos son simulados y no se envían.
- Puedes preguntar por la historia, misión y visión de la empresa ficticia, cómo comprar, cómo funciona el simulador de pago, el carrito, los pedidos, las devoluciones, el contacto o la privacidad. El chat enlaza las páginas con más información.
- «No tengo presupuesto» se interpreta como que no se indicó un límite; «gratis» sí significa que el precio debe ser cero. El chat conserva y actualiza estas preferencias durante la conversación.
- «Quiero una maceta irrompible» muestra macetas disponibles, pero aclara si el catálogo no documenta su resistencia. También corrige algunas erratas comunes, como «meceta».
- Si alguien pregunta por cannabis, el asistente aclara que VERDELIA no lo comercializa. Cuando conoce la luz del espacio, ofrece alternativas ornamentales disponibles del catálogo.
- Un patio o jardín no determina por sí solo la cantidad de luz: el asistente conserva la condición explícita y pregunta si hace falta.
- Si ninguna planta cumple las condiciones, el chat lo indica en vez de sugerir productos agotados o inventar compatibilidades.
- Si preguntan por una región de origen, como la Amazonía, el chat no muestra plantas al azar: las fichas actuales no guardan procedencia geográfica, así que aclara que no puede verificarla.
- Para consultas sobre seguridad de mascotas o cuidados que la ficha no especifica, el chat explica qué información falta.
- Si una mascota o un niño ingirió una planta, el chat indica buscar ayuda veterinaria o médica/toxicológica de inmediato; no diagnostica. Para preguntas de seguridad de bebés o niños, no afirma que una especie sea segura sin datos verificados.
- El chat no promete que las plantas curen enfermedades, eliminen moho o humedad, ni que sobrevivan a temperaturas extremas que el catálogo no documenta.
- Si se pregunta por retiro en tienda, el stock en línea no se presenta como confirmación de horario o disponibilidad para hoy. El asistente tampoco crea ni reserva pedidos: dirige al cliente al carrito y al checkout.
- Si el mensaje afirma a la vez que no hay límite de precio y que sí existe un máximo, el asistente pide elegir qué criterio usar antes de recomendar.
- Si no encuentra una planta que cumpla los criterios, describe solo los criterios que la persona indicó y no atribuye fallos a preferencias que no mencionó.
- «¿Cuál es la tendencia de demanda del Aloe?» usa regresión lineal sobre seis meses completos de ventas confirmadas y estima el primer mes posterior a ese historial. Si faltan observaciones o se pide otro período, el chat lo indica. El historial inicial contiene pedidos con pago simulado.

## Ejecutar en Windows

Extrae el ZIP completo, inicia MySQL en XAMPP y ejecuta `INICIAR_VERDELIA.bat`. El sitio se sirve con Apache de XAMPP 8.0.28; si el doble clic no abre la consola, sigue los pasos de [instalación con XAMPP](../INSTALACION_URBE.md). Puedes consultar el pronóstico con el motor PHP incluido.

Para usar el motor Python en una PC que ya tiene Python 3, abre CMD en la carpeta de VERDELIA y ejecuta:

```bat
set "VERDELIA_PYTHON_EXE=C:\ruta\a\python.exe"
INICIAR_VERDELIA.bat
```

Sustituye la ruta por la de tu ejecutable. Puedes obtenerla con `python -c "import sys; print(sys.executable)"`. Python recibe la serie mensual de unidades y calcula la pendiente y proyección por mínimos cuadrados con su biblioteca estándar. Si el proceso falla, el servidor usa el mismo método en PHP. El chat funciona con XAMPP sin servicios externos.
