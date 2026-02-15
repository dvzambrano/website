<?php

return [

    "mainmenu" => [
        "description" => "El puente más rápido y seguro para mover tu dinero entre Zelle, Bizum, Pago Móvil y más, usando la estabilidad del USD digital.",
        "body" => "🔒 Seguro: Tus fondos están protegidos por contratos inteligentes. 💸 Sin Gas: Tú no pagas comisiones de red, nosotros nos encargamos. 🚀 Rápido: Intercambios P2P en minutos.",

    ],
    "actionmenu" => [
        "header" => "Menú de acciones",
        "line1" => "Usando el “Botón de Acción”, puedes cambiar entre 2 niveles",
        "line2" => "Notificaciones: Cuando aparece una señal, el bot solo notifica a los usuarios",
        "line3" => "Ejecutar órdenes: El bot notifica a los usuarios de la comunidad y ejecuta las órdenes correspondientes",
        "line4" => "En este momento la opción “:bot_name” está seleccionada",
    ],
    "subscribtionmenu" => [
        "header" => "Menú de suscripción",
        "line1" => "Aquí puede ajustar sus preferencias",
        "line2" => "Usando el botón “Nivel”, puedes cambiar entre 3 niveles",
        "line3" => "solo recibirás señales de la comunidad",
        "line4" => "solo recibirás tus alertas personales",
        "line5" => "recibirás tanto alertas de la comunidad como las personales",
        "line6" => "Eres un suscriptor de nivel :level",
        "therefore" => "por lo tanto, puedes usar el botón “URL del Cliente” para obtener tu enlace de alertas de TradingView",
    ],
    "options" => [
        "subscribtion" => "Suscripción",
        "subscribtionlevel" => ":icon Nivel :char",
        "clienturl" => "URL de cliente",
        "backtosuscribemenu" => "Volver al menú suscripciones",
        "actionmenu" => "Nivel de acción",
        "actionlevel1" => "Notificaciones",
        "actionlevel2" => "Ejecutar ordenes",
        "selloffer" => "Vender USD",
        "buyoffer" => "Comprar USD",
        "topup" => "Depositar USD",
        "withdraw" => "Extraer USD",
    ],
    "prompts" => [
        "clienturl" => [
            "header" => "Su URL de cliente es la siguiente",
            "warning" => "Esta es la dirección que debe usar en TradingView para notificar al bot que desea trabajar con una alerta personalizada",
            "text" => "¿Está seguro que desea continuar?",
        ],
        "txsuccess" => "TX Exitosa",
        "txfail" => "TX Fallida",
        "buy" => [
            "exchangetitle" => "Depositar en :name",
            "update" => [
                "header" => "Actualización de Depósito",
                "completed" => "¡Sus fondos están en camino a su cuenta!",
                "failed" => "¡La transacción HA FALLADO!",
                "processing" => "Estamos procesando su solicitud.",
            ],
            "completed" => [
                "header" => "Saldo Acreditado!",
                "text" => "Hemos recibido un depósito para Ud de :amount :currency correctamente",
            ],

        ],
        "sell" => [
            "exchangetitle" => "Retirar de :name",
            "update" => [
                "header" => "Actualización de Extracción",
                "completed" => "¡Sus fondos están en camino a su cuenta!",
                "failed" => "¡La transacción HA FALLADO!",
                "processing" => "Estamos procesando su solicitud.",
            ],
            "completed" => [
                "header" => "Saldo Acreditado!",
                "text" => "Hemos recibido una extracción de Ud de :amount :currency correctamente",
            ],

        ],
        "fail" => [
            "suscriptor" => "Lo sentimos, no pudimos encontrar tu billetera configurada.",
            "widgeturl" => "Lo sentimos, hubo un error al generar tu sesión de pago. Por favor, intenta más tarde.",
        ]
    ],
];