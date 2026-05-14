<?php

return [
    "maintenance" => [
        "message" => "El bot se encuentra en mantenimiento.",
    ],
    "mainmenu" => [
        "salutation" => "Bienvenido al :bot_name",
        "referral" => "Enlace de referido",
        "question" => "¿En qué le puedo ayudar hoy?",
    ],
    "adminmenu" => [
        "header" => "Menú de administrador",
        "warning" => "Aquí encontrará herramientas útiles para la gestión integral del bot",
    ],
    "configmenu" => [
        "header" => "Menú de configuraciones",
        "warning" => "Aquí encontrará ajustes del comportamiento del bot",
        "chat_clean_active" => "Chat limpio: activo",
        "chat_clean_disabled" => "Chat limpio: desactivado",
    ],
    "role" => [
        "admin" => "Admin",
    ],
    "options" => [
        "config" => "Configuración",
        "help" => "Ayuda",
        "yes" => "Sí",
        "no" => "No",
        "cancel" => "Cancelar",
        "delete" => "Eliminar",
        "sendannouncement" => "Anuncio",
        "viewusers" => "Usuarios suscritos",
        "backtomainmenu" => "Volver al menú principal",
        "backtoadminmenu" => "Volver al menú de administrador",
        "deleteprevmessages" => "Eliminar mensajes previos",
        "keepprevmessages" => "No eliminar mensajes previos",
        "timezone" => "Zona horaria :timezone",
        "backtoconfigmenu" => "Volver al menú configuraciones",
    ],
    "prompts" => [
        "whatsnext" => "¿Qué desea hacer ahora?",
        "chooseoneoption" => "Escoja una de las siguientes opciones",
        "areyousure" => [
            "header" => "Solicitud de confirmación",
            "warning" => "CUIDADO: Esta acción no se puede revertir",
            "text" => "¿Está seguro que desea continuar?",
        ],
        "notimplemented" => [
            "header" => "Función no implementada",
            "warning" => "Esta función aun no está lista. Estamos trabajando en ella para sacarla en los próximos días.",
        ],
        "announcement" => [
            "prompt" => "Enviar anuncio",
            "header" => "ATENCION: Anuncio del sistema",
            "whatsnext" => "Escriba el anuncio que desea enviar",
            "preparing" => [
                "header" => "Preparando anuncios",
                "warning" => "Se enviarán anuncios a :amount suscriptores...",
            ],
            "sending" => [
                "header" => "Enviando anuncios...",
                "warning" => "Progreso: :amount de :total anuncios enviados.",
            ],
            "sent" => [
                "header" => "¡Envío completado!",
                'destroy' => [
                    "segs" => 'Este mensaje se eliminará en :count segundo|Este mensaje se eliminará en :count segundos',
                    'mins' => 'Este mensaje se eliminará en :count minuto|Este mensaje se eliminará en :count minutos',
                ],
                'duration' => [
                    "header" => "Tiempo total:",
                    "segs" => ':count segundo|:count segundos',
                    'mins' => ':count minuto|:count minutos',
                ],
            ],
        ],
        "userwithnorole" => [
            "header" => "Nuevo usuario suscrito al bot",
            "warning" => "Invitado por",
        ],
        "usernamerequired" => [
            "line1" => "Para usar este bot, por favor configura un nombre de usuario (@usuario) en tu cuenta de Telegram",
            "line2" => "¿Cómo configurarlo?",
            "line3" => "Ve a Configuración (o Ajustes)",
            "line4" => "Selecciona tu perfil y busca la opción Nombre de usuario",
            "line5" => "Elige un nombre único que comience con @",
            "line6" => "Una vez que hayas configurado tu nombre de usuario, haz clic en el siguiente botón",
            "done" => "Listo, ¡ya lo he hecho!",
        ],
    ],
    "errors" => [
        "header" => "Error",
        "unrecognizedcommand" => [
            "text" => "No se que responderle a “:text”",
            "hint" => "Ud puede interactuar con este bot usando /menu o chequee /ayuda para temas de ayuda",
        ],
    ],
    "scanner" => [
        "prompt" => "Escanea la etiqueta",
        "localmode" => "SIN CONEXIÓN - MODO LOCAL",
        "opencamera" => "Abrir Cámara",
        "online" => "En Línea",
        "offline" => "Sin conexión",
        "synchronizing" => "Sincronizando",
        "procesing" => "Procesando",
        "fetch" => [
            "title" => "¡Logrado!",
            "desc" => "códigos procesados",
        ],
        "localstoragedcodes" => "códigos guardados localmente",
        "localstorageaction" => "Los códigos se guardarán en el teléfono",
        "loadinggps" => "Obteniendo ubicación GPS",
        "gpsdeniedtitle" => "Debe activar y conceder permisos para su ubicación GPS",
        "retrygps" => "Conceder permisos GPS",
    ],
    "actors" => [
        "subscribers" => [
            "header" => "Usuarios suscritos",
            "body" => "Estos son los :count usuarios que se han suscrito al bot.",
        ],
        "usernotfound" => [
            "header" => "Usuario no encontrado",
            "before" => "El usuario",
            "after" => "no se encuentra suscrito a este bot.",
        ],
        "role" => [
            "modified" => "Rol de usuario modificado",
            "changed" => [
                "header" => "Su rol ha sido modificado",
                "body" => "Le recomendamos volver al /menu para verificar sus nuevas opciones",
            ],
        ],
        "utc" => [
            "prompt" => [
                "header" => "Ajustar zona horaria",
                "line1" => "Definir su zona horaria hara que el bot le personalice las fechas y horas.",
                "line2" => "Para establecer su zona horaria de la forma UTC-4 escriba solo -4.",
                "footer" => "Escriba en que zona horaria esta ud:",
            ],
            "updated" => [
                "header" => "Zona horaria actualizada",
                "body" => "Se ha actualizado su zona horaria satisfactoriamente.",
                "currenttime" => "Ahora son las",
            ],
            "error" => [
                "header" => "Zona con error",
                "before" => "No se puede establecer la zona horaria",
                "hint" => "Revise que haya enviado un numero valido con el que se pueda ajustar la hora.",
            ],
            "retry" => "Intentar nuevamente",
        ],
        "metadata" => [
            "add" => "Añadir metadato",
            "define" => [
                "header" => "Definir metadato al suscriptor",
                "footer" => "Escriba a continuacion:",
            ],
            "updated" => [
                "header" => "Metadato actualizado",
                "body" => "Se ha actualizado el metadato del suscriptor satisfactoriamente.",
                "back" => "Volver a mostrar el suscriptor",
            ],
        ],
    ],
    "wizard" => [
        "cancelled" => "Wizard cancelado.",
    ],
    "deleted" => [
        "title" => "Registro eliminado",
        "desc"  => "Se ha eliminado el registro de la base de datos satisfactoriamente.",
    ],
];