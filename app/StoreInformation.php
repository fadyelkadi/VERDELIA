<?php
declare(strict_types=1);

final class StoreInformation
{
    public const HISTORY = 'VERDELIA es una empresa ficticia creada para un proyecto académico. Su historia parte de una idea: ayudar a quienes viven o trabajan en espacios pequeños a elegir su primera planta y aprender a cuidarla.';
    public const MISSION = 'Queremos que elegir una planta sea sencillo. Reunimos plantas de interior, suculentas, macetas y accesorios, con guías para comparar la luz, el riego, los materiales y el uso de cada artículo.';
    public const VISION = 'Imaginamos hogares y lugares de trabajo donde cualquiera pueda empezar un rincón verde, aunque tenga poco espacio o sea su primera planta.';
    public const VALUES = [
        'Elegir con intención'=>'Empieza por la luz, el espacio disponible y el tiempo que puedes dedicarle a tu planta.',
        'Aprender sin prisa'=>'Las guías de las fichas explican cómo elegir el sustrato, cuándo regar y dónde colocar cada planta.',
        'Comprar con información clara'=>'Mostramos precio y existencias en cada ficha. En este proyecto académico la compra y el pago son simulados.',
    ];
    public const LOCATION = 'VERDELIA funciona como tienda en línea. El sitio no publica una dirección ni un horario de atención presencial.';
    public const CONTACT = 'En la página Contacto puedes consultar los correos publicados del equipo de diseño y desarrollo. El formulario revisa los campos en el navegador.';
    public const TEAM = [
        'Fady El Kadi'=>'fadyelkadi.17@gmail.com',
        'César Medina'=>'Cesar123@gmail.com',
        'Udon Sandrea'=>'udonandres@gmail.com',
        'Luis Gonzalez'=>'luismario2502@gmail.com',
    ];
    public const PURCHASE = 'Elige un producto, selecciona la cantidad y añádela al carrito. Inicia sesión o crea una cuenta para finalizar el pedido con el simulador de pago.';
    public const PAYMENT = 'Al finalizar el pedido puedes usar el simulador de pago. Permite aprobar, rechazar o cancelar el pago. No cobra dinero ni solicita los datos de una tarjeta o credenciales de PayPal.';
    public const SHIPPING = 'Los pedidos en el sitio no generan envíos ni entregas. La compra es una simulación.';
    public const PICKUP = 'La demostración no ofrece retiro en tienda. El stock del catálogo no confirma retiro en tienda, horario ni disponibilidad para hoy.';
    public const RETURNS = 'Como los pedidos son simulados, no hay productos entregados, devoluciones ni reembolsos reales.';
    public const CART = 'El carrito de invitado se conserva en la sesión del navegador. Al iniciar sesión, se fusiona con el carrito guardado en tu cuenta. Añadir al carrito no reserva existencias.';
    public const ORDERS = 'Inicia sesión y abre Mis pedidos para consultar el estado y el recibo de cada pedido de tu cuenta.';
    public const ACCOUNT = 'Puedes crear tu cuenta desde Registrarse. Con la sesión iniciada podrás revisar tus pedidos y conservar el carrito entre visitas.';
    public const PRIVACY = 'VERDELIA guarda las cuentas, los carritos, los pedidos y las referencias de pago simulado en su base de datos. La sesión utiliza una cookie para mantener el acceso y reconocer tu carrito.';
    public const IMAGES = 'Las imágenes son ilustrativas. El tamaño, el color y otros detalles pueden variar.';
    public const CHOOSE = 'Considera primero la luz disponible, el espacio y el tiempo que puedes dedicarle. Cada ficha indica la luz recomendada y muestra una guía de cuidados cuando está disponible.';

    public static function faq(): array
    {
        return [
            '¿Cómo hago un pedido?' => self::PURCHASE,
            '¿Cómo funciona el pago?' => self::PAYMENT,
            '¿Hay envíos o retiro en tienda?' => self::SHIPPING.' '.self::PICKUP,
            '¿Hay devoluciones o reembolsos?' => self::RETURNS,
            '¿El carrito conserva mis productos?' => self::CART,
            '¿Dónde veo el estado y el recibo de mi pedido?' => self::ORDERS,
            '¿Cómo elijo una planta para mi espacio?' => self::CHOOSE,
            '¿Las imágenes representan exactamente cada artículo?' => self::IMAGES,
        ];
    }
}
