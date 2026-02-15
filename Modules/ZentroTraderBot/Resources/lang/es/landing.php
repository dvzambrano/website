<?php

return [
    'title' => 'Kashio',

    'meta' => [
        'title' => 'Tu cuenta personal en USD',
        'description' => 'Gestiona tu capital en dólares digitales de forma segura, estable y sin complicaciones.',
    ],

    'menu' => [
        'home' => 'Inicio',
        'about' => 'Sobre nosotros',
        'features' => 'Características',
        'steps' => 'Servicios',
        'wallet' => 'Planes',
        'contact' => 'Contacto',
        'get_started' => 'Empezar ahora',
    ],
    'hero' => [
        'title' => 'Tu cuenta personal en dólares digitales',
        'subtitle' => 'Protege tu capital de la devaluación de forma segura, rápida y sin complicaciones.',
        'cta' => 'Crear una cuenta',
    ],
    'about' => [
        'title' => '¿Quiénes somos?',
        'description' => 'Kashio es la evolución de tu billetera personal, diseñada para darte estabilidad financiera en un entorno digital.',
        'card_1' => [
            'title' => 'Balance en USD',
            'text' => 'Mantén tus ahorros en activos estables como USDT y USDC, protegidos de la volatilidad del mercado local.',
        ],
        'card_2' => [
            'title' => 'Disponibilidad 24/7',
            'text' => 'Accede a tus fondos, consulta tu balance y gestiona tus dólares en cualquier momento y desde cualquier lugar.',
        ],
    ],
    'features' => [
        'title' => 'Todo lo que necesitas para gestionar tu capital',
        'list' => [
            'feature_1' => 'Cero volatilidad',
            'feature_2' => 'Interfaz intuitiva',
            'feature_3' => 'Historial detallado',
            'feature_4' => 'Seguridad de grado bancario',
            'feature_5' => 'Soporte prioritario',
            'feature_6' => 'Acceso multi-dispositivo',
        ],
    ],
    'payments' => [
        'tab1' => 'Depósito',
        'tab2' => 'Seguridad',
        'tab3' => 'Transak',
        'subtitle' => '¿Cómo fondear tu cuenta?',
        'transak_notice' => 'Kashio utiliza la pasarela de Transak para garantizar depósitos seguros y directos.',
        'step_1' => 'Selecciona el monto a depositar',
        'step_2' => 'Paga con tu moneda local vía Transak',
        'step_3' => 'Recibe tus dólares en tu balance de Kashio',
    ],
    'faq' => [
        'title' => 'Preguntas Frecuentes',
        'questions' => [
            [
                'id' => '1',
                'question' => '¿Qué es exactamente una cuenta Kashio?',
                'answer' => 'Es un espacio seguro donde tu capital se mantiene en dólares digitales para evitar la devaluación. Es la evolución de tu billetera personal respaldada por ZentroTrader.'
            ],
            [
                'id' => '2',
                'question' => '¿Cómo cargo saldo en mi cuenta?',
                'answer' => 'El proceso es simple: utilizas nuestra integración con Transak para comprar USD con tu moneda local. El balance se actualiza automáticamente en tu perfil una vez confirmada la operación.'
            ],
            [
                'id' => '3',
                'question' => '¿Es seguro operar con Transak?',
                'answer' => 'Sí, Transak es una plataforma líder regulada. Kashio nunca almacena tus datos bancarios; solo recibimos el depósito final para acreditarlo a tu cuenta.'
            ],
            [
                'id' => '4',
                'question' => '¿Tengo disponibilidad inmediata de mis fondos?',
                'answer' => '¡Totalmente! Tus fondos están disponibles 24/7. Puedes gestionar y consultar tu balance en cualquier momento desde tu panel personal de forma transparente.'
            ],
            [
                'id' => '5',
                'question' => '¿Kashio cobra comisiones de mantenimiento?',
                'answer' => 'Contamos con un plan básico gratuito para que protejas tu dinero sin costos fijos. Solo aplicamos comisiones mínimas en operaciones de gestión de capital avanzadas.'
            ],
            [
                'id' => '6',
                'question' => '¿Qué respaldo tiene mi dinero?',
                'answer' => 'Tu dinero está respaldado por tecnología blockchain en activos estables (Stablecoins) y cuenta con la infraestructura de seguridad de grado bancario de ZentroTrader.'
            ],
        ]
    ],
    'footer' => [
        'title' => 'Suscríbete a nuestro boletín',
        'contact' => 'Soporte Kashio',
        'description' => 'La plataforma líder para la gestión de tus activos digitales respaldada por ZentroTrader.',
    ],
    'pricing' => [
        'title' => 'Planes y Beneficios',
        'currency' => '$',
        'plans' => [
            [
                'name' => 'Básico',
                'price' => '0',
                'color' => '#07d5c0',
                'img' => 'assets/img/pricing-free.png',
                'button' => 'Empezar Gratis',
                'featured' => false,
                'features' => ['Cuenta Personal USD', 'Depósitos vía Transak', 'Historial de movimientos'],
                'na' => ['Soporte Prioritario', 'Análisis Avanzado']
            ],
            [
                'name' => 'Starter',
                'price' => '19',
                'color' => '#65c600',
                'img' => 'assets/img/pricing-starter.png',
                'button' => 'Seleccionar',
                'featured' => true,
                'features' => ['Cuenta Personal USD', 'Depósitos vía Transak', 'Historial de movimientos', 'Soporte 24/7'],
                'na' => ['Análisis Avanzado']
            ],
            [
                'name' => 'Business',
                'price' => '29',
                'color' => '#ff901c',
                'img' => 'assets/img/pricing-business.png',
                'button' => 'Seleccionar',
                'featured' => false,
                'features' => ['Cuenta Personal USD', 'Depósitos vía Transak', 'Historial de movimientos', 'Soporte Prioritario', 'Análisis de Mercado'],
                'na' => []
            ],
            [
                'name' => 'Ultimate',
                'price' => '49',
                'color' => '#ff0071',
                'img' => 'assets/img/pricing-ultimate.png',
                'button' => 'Seleccionar',
                'featured' => false,
                'features' => ['Todo lo anterior', 'Límites extendidos', 'Acceso VIP ZentroTrader', 'Gestión de Activos', 'Asesoría Personalizada'],
                'na' => []
            ],
        ]
    ],
    'blog' => [
        'title' => 'Aprende a proteger tu capital',
        'read_more' => 'Leer más',
        'posts' => [
            [
                'date' => '15 de Febrero, 2026',
                'title' => '¿Qué son los Dólares Digitales y por qué son el refugio ideal?',
                'img' => 'assets/img/blog/blog-1.jpg',
                'link' => '#',
            ],
            [
                'date' => '10 de Febrero, 2026',
                'title' => 'Guía paso a paso: Cómo fondear tu cuenta Kashio usando Transak.',
                'img' => 'assets/img/blog/blog-2.jpg',
                'link' => '#',
            ],
            [
                'date' => '05 de Febrero, 2026',
                'title' => 'Seguridad Blockchain: Cómo protegemos tus activos en Kashio.',
                'img' => 'assets/img/blog/blog-3.jpg',
                'link' => '#',
            ],
        ]
    ],
    'team' => [
        'title' => 'El equipo detrás de Kashio',
        'members' => [
            [
                'name' => 'Donel Zambrano',
                'role' => 'Founder & Lead Developer',
                'desc' => 'Arquitecto principal de Kashio y ZentroTrader. Experto en democratizar el acceso a cuentas estables en USD digitales.',
                'img' => 'assets/img/team/team-1.jpg',
                'twitter' => '#',
                'linkedin' => '#',
                'facebook' => '#',
                'instagram' => '#'
            ],
            [
                'name' => 'Soporte Kashio',
                'role' => 'Atención al Cliente',
                'desc' => 'Equipo dedicado a asistirte en tus procesos de carga vía Transak y gestión de balance en tiempo real.',
                'img' => 'assets/img/team/team-2.jpg',
                'twitter' => '#',
                'linkedin' => '#',
                'facebook' => '#',
                'instagram' => '#'
            ],
            [
                'name' => 'ZentroTrader Tech',
                'role' => 'Infraestructura & Seguridad',
                'desc' => 'Especialistas en tecnología blockchain encargados de la custodia y seguridad de tus activos digitales.',
                'img' => 'assets/img/team/team-3.jpg',
                'twitter' => '#',
                'linkedin' => '#',
                'facebook' => '#',
                'instagram' => '#'
            ],
        ]
    ],
    'testimonials' => [
        'title' => 'Lo que dicen nuestros usuarios',
        'items' => [
            [
                'quote' => 'Desde que uso Kashio, no me preocupo por la devaluación. Tener mis ahorros en USD digitales me da una tranquilidad que no tenía antes.',
                'name' => 'Ricardo M.',
                'role' => 'Usuario Particular',
                'img' => 'assets/img/testimonials/testimonials-1.jpg',
                'stars' => 5
            ],
            [
                'quote' => 'Increíble lo fácil que es recargar con Transak. En pocos minutos pasé mi moneda local a dólares en mi cuenta personal de Kashio.',
                'name' => 'Sara W.',
                'role' => 'Freelancer',
                'img' => 'assets/img/testimonials/testimonials-2.jpg',
                'stars' => 5
            ],
            [
                'quote' => 'La interfaz es muy limpia. Puedo ver mi balance en USD al instante y sé que mi capital está respaldado por la tecnología de ZentroTrader.',
                'name' => 'Juan K.',
                'role' => 'Comerciante',
                'img' => 'assets/img/testimonials/testimonials-3.jpg',
                'stars' => 5
            ],
            [
                'quote' => 'Como emprendedor, necesitaba una cuenta en USD que fuera rápida. La integración con Transak funciona de maravilla.',
                'name' => 'Matt B.',
                'role' => 'Emprendedor',
                'img' => 'assets/img/testimonials/testimonials-4.jpg',
                'stars' => 4
            ],
        ]
    ],
    'portfolio' => [
        'title' => 'Conoce la interfaz de tu cuenta Kashio',
        'filters' => [
            'all' => 'Todo',
            'app' => 'App Móvil',
            'card' => 'Panel USD',
            'web' => 'Depósitos',
        ],
        'items' => [
            [
                'title' => 'Gestión Móvil',
                'category' => 'filter-app',
                'category_label' => 'App',
                'img' => 'assets/img/portfolio/portfolio-1.jpg',
                'desc' => 'Tu balance siempre contigo'
            ],
            [
                'title' => 'Recargas Seguras',
                'category' => 'filter-web',
                'category_label' => 'Transak',
                'img' => 'assets/img/portfolio/portfolio-2.jpg',
                'desc' => 'Compra USD con tu moneda local'
            ],
            [
                'title' => 'Balance en Tiempo Real',
                'category' => 'filter-card',
                'category_label' => 'USD Digital',
                'img' => 'assets/img/portfolio/portfolio-4.jpg',
                'desc' => 'Control total de tus activos'
            ],
            // Puedes añadir más elementos aquí siguiendo la misma estructura
        ]
    ],
    'contact' => [
        'title' => '¿Tienes dudas sobre tu cuenta USD?',
        'info' => [
            'address_title' => 'Sede Central',
            'address_text' => 'ZentroTrader Tech, Soporte Global Kashio',
            'phone_title' => 'Llámanos',
            'phone_text' => '+1 234 567 890',
            'email_title' => 'Escríbenos',
            'email_text' => 'soporte@kashio.com',
            'hours_title' => 'Horario de Atención',
            'hours_text' => 'Lunes - Viernes: 9:00AM - 06:00PM',
        ],
        'form' => [
            'name' => 'Tu Nombre',
            'email' => 'Tu Correo',
            'subject' => 'Asunto',
            'message' => 'Cuéntanos cómo podemos ayudarte con tu cuenta',
            'button' => 'Enviar Mensaje',
            'loading' => 'Cargando...',
            'sent' => 'Tu mensaje ha sido enviado. ¡Gracias!',
        ]
    ],
];