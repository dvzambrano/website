<?php

return [

    "mainmenu" => [
        "description" => "El puente más rápido y seguro para mover tu dinero usando la estabilidad del USD digital.",
        "line1" => "Rápido: Intercambios P2P en minutos.",
        "line2" => "Seguro: Fondos protegidos por contratos inteligentes.",
    ],
    "actionmenu" => [
        "header" => "Menú de acciones",
        "line1" => 'Usando el botón de Acción, puedes cambiar entre 2 niveles',
        "line2" => "Notificaciones: Cuando aparece una señal, el bot solo notifica a los usuarios",
        "line3" => "Ejecutar órdenes: El bot notifica a los usuarios de la comunidad y ejecuta las órdenes correspondientes",
        "line4" => 'En este momento la opción ":bot_name" está seleccionada',
    ],
    "subscribtionmenu" => [
        "header" => "Menú de suscripción",
        "line1" => "Aquí puede ajustar sus preferencias",
        "line2" => 'Usando el botón Nivel, puedes cambiar entre 3 niveles',
        "line3" => "solo recibirás señales de la comunidad",
        "line4" => "solo recibirás tus alertas personales",
        "line5" => "recibirás tanto alertas de la comunidad como las personales",
        "line6" => "Eres un suscriptor de nivel :level",
        "therefore" => 'por lo tanto, puedes usar el botón URL del Cliente para obtener tu enlace de alertas de TradingView',
    ],

    "p2pmenu" => [
        "header" => "Mercado P2P",
        "line1" => "Compra y vende USD de forma segura, directa y sin intermediarios",
        "line2" => "Garantía Escrow: Todos los fondos están protegidos por contratos inteligentes hasta que el pago sea confirmado por ambas partes.",
        "line3" => "Tu Actividad",
        "line4" => "Ofertas publicadas: :amount",
        "line5" => "Calificación: :amount",
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
        "balance" => "Consultar saldo",
        "topupcripto" => "Depositar Criptomonedas",
        "topupramp" => "Depositar USD",
        "withdraw" => "Extraer USD",
        "viewp2poffers" => "Ver mercado de ofertas",
        "myoffers" => "Mis Ofertas Activas",
        "mypaymentmethods" => "Métodos de Pago",
        "backtop2pmenu" => "Volver al Mercado P2P",
        // Offer actions
        "delete_offer" => "Eliminar",
        "send_proof" => "Enviar Comprobante",
        "send_evidence" => "Enviar evidencias del intercambio",
        "recover_not_paid" => "Ha pasado :time y no me han pagado",
        "recover_long_wait" => "Ha pasado más de :time y no me han pagado",
        "confirm_received" => "He recibido el Pago",
        "not_received" => "No lo he recibido",
        "open_dispute" => "Abrir Disputa",
        "talk_arbiter" => "Hablar con un Árbitro",
        "view_my_offer" => "Ver mi Oferta",
        "publish" => "Publicar",
        "back" => "Atrás",
        "cancel" => "Cancelar",
        "previous" => "Anterior",
        "next" => "Siguiente",
        "message_buyer" => "Enviar mensaje al Comprador",
        "message_seller" => "Enviar mensaje al Vendedor",
        "myalerts" => "Alertas de Ofertas",
        "view_profile" => "Ver Perfil",
        "view_profile_seller" => "Ver Perfil del Vendedor",
        "view_profile_buyer" => "Ver Perfil del Comprador",
    ],

    // =========================================================
    // TRADER PROFILE — Vista pública del perfil
    // =========================================================

    "profile" => [
        "header" => "PERFIL DEL TRADER",
        "vip" => "VIP",
        "trades" => "Trades completados",
        "completion_rate" => "Tasa de completado",
        "avg_response" => "Tiempo de respuesta",
        "avg_release" => "Liberación de fondos",
        "member_since" => "Trader desde",
        "time_minutes" => ":n min",
        "time_hours" => ":n h",
        "time_days" => ":n días",
        "rating" => "Calificación promedio",
        "payment_methods" => "Opera con",
        "last_reviews" => "Últimas reseñas",
        "no_reviews" => "Sin reseñas aún",
        "not_found" => "No se pudo encontrar el perfil de este trader",
    ],

    // =========================================================
    // INTERNAL CHAT — Chat anónimo entre comprador y vendedor
    // =========================================================

    "chat" => [
        "active" => [
            "line1" => "Estás en modo chat con :counterpart.",
            "line2" => "Todo lo que envíes será transmitido anónimamente.",
            "line3" => "Usa el botón para salir.",
        ],
        "exit_btn" => "Salir del chat",
        "reply_btn" => "Responder",
        "exited" => "Has salido del modo chat.",
        "message_sent" => "Mensaje enviado.",
        "buyer_says" => "Comprador",
        "seller_says" => "Vendedor",
        "counterpart_buyer" => "el comprador",
        "counterpart_seller" => "el vendedor",
        "counterpart_unavailable" => "La contraparte no está disponible en este momento.",
        "unsupported_media" => "(Contenido multimedia no compatible con el chat interno)",
    ],

    // =========================================================
    // SUPPORT TICKET — Chat directo con el equipo de soporte
    // =========================================================

    "support" => [
        "btn_open_ticket" => "Soporte",
        "ticket_opened" => "Tu ticket de soporte ha sido creado. Escríbenos tu consulta.",
        "chat_line2" => "Todo lo que envíes llegará a nuestro equipo de soporte.",
        "chat_line3" => "Usa el botón para salir del modo chat.",
        "exit_btn" => "Cerrar chat",
        "exited" => "Has cerrado el ticket de soporte.",
        "message_sent" => "Mensaje enviado a soporte.",
        "already_open" => "Ya tienes un ticket de soporte abierto. Escribe tu consulta directamente.",
        "new_ticket_intro" => "Nuevo ticket de soporte de",
        "user_reconnected" => "El usuario se ha reconectado al ticket:",
        "error_no_group" => "El grupo de soporte no está configurado. Contacta al administrador.",
        "error_create_ticket" => "No se pudo crear el ticket. Inténtalo más tarde.",
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
                "completed" => "Sus fondos están en camino a su cuenta.",
                "failed" => "La transacción HA FALLADO.",
                "processing" => "Estamos procesando su solicitud.",
            ],
            "completed" => [
                "header" => "Saldo Acreditado",
                "warning" => "Ha recibido un depósito de :amount USD.",
                "text" => "Sus fondos ya están disponibles en su cuenta para ser utilizados.",
            ],
            "badcurrency" => [
                "header" => "Saldo Recibido",
                "warning" => "Ha recibido un depósito de :amount :currency.",
                "text" => "Estos fondos están en :currency, los cambiaremos a USD para acreditarlos en su cuenta...",
            ],
        ],
        "sell" => [
            "exchangetitle" => "Retirar de :name",
            "update" => [
                "header" => "Actualización de Extracción",
                "completed" => "Sus fondos están en camino a su cuenta.",
                "failed" => "La transacción HA FALLADO.",
                "processing" => "Estamos procesando su solicitud.",
            ],
            "completed" => [
                "header" => "Saldo Acreditado",
                "text" => "Hemos recibido una extracción de Ud de :amount :currency correctamente",
            ],
        ],
        "fail" => [
            "suscriptor" => "Lo sentimos, no pudimos encontrar tu billetera configurada.",
            "widgeturl" => "Lo sentimos, hubo un error al generar tu sesión de pago. Por favor, intenta más tarde.",
        ],
        "topup" => [
            "cripto" => [
                "header" => "Esta es su dirección de depósito",
                "line1" => "Operamos con :token en la red de :network",
                "line2" => "Los fondos en :token (:network) estarán disponibles automáticamente.",
                "line3" => "Otra moneda requiere cambiarse a :token obligatoriamente.",
                "options" => [
                    "debridge" => "Depositar usando DeBridge",
                    "seedphrase" => "Exportar frase semilla",
                ],
            ],
        ],
        "seedphrase" => [
            "warning" => [
                "line1" => "Lo que está a punto de exhibir es su FRASE SEMILLA",
                "line2" => "Cualquiera que la vea tendrá CONTROL TOTAL Y PERMANENTE de todos tus fondos",
                "line3" => "Asegúrese de que nadie está mirando su pantalla",
            ],
            "export" => [
                "line1" => "Tus :count palabras de seguridad",
                "line2" => "Copie o escanee esta información rapidamente:",
                'destroy' => [
                    "segs" => 'Este mensaje se eliminará en :count segundo|Este mensaje se eliminará en :count segundos',
                    'mins' => 'Este mensaje se eliminará en :count minuto|Este mensaje se eliminará en :count minutos',
                ],
            ],
        ],
        "balance" => [
            "available" => "Saldo disponible",
            "locked" => "Bloqueado",
            "lastoperations" => "Últimas operaciones",
        ],
    ],

    // =========================================================
    // OFFER OBSERVER — Notificaciones por cambio de estado
    // =========================================================

    "offer" => [
        "locked" => [
            "title" => "Intercambio asegurado",
            "buyer" => [
                "funds_blocked" => "Se han bloqueado :amount USD",
                "you_receive" => "Ud recibe :net USD",
                "proceed" => "Ahora es seguro proceder:",
                "make_payment" => "Realice el pago de :price :currency a:",
                "then_proof" => "y luego, entregue su comprobante para verificación.",
                "time_margin" => "Tiene un margen de :time para completar su pago.",
                "after_timeout" => "Luego de ese tiempo los USD estarán disponibles para que el vendedor los recupere.",
            ],
            "seller" => [
                "funds_blocked" => "Se han bloqueado :amount USD de su cuenta",
                "buyer_will_pay" => "El comprador realizará el pago de :price :currency a:",
                "then_proof" => "Y luego, enviará su comprobante para verificación.",
                "never_confirm" => "Nunca confirme la transacción sin comprobar el recibo de los :price :currency en su cuenta",
            ],
        ],
        "completed" => [
            "title" => "FELICIDADES: transaccion completada",
            "title_dispute" => "TRANSACCION COMPLETADA",
            "success" => "El intercambio se realizó con éxito.",
            "finalized_by_arbitrage" => "La transacción ha sido finalizada tras el arbitraje.",
            "seller_dispute_status" => "Estado final: Fondos procesados.",
            "buyer_dispute_status" => "Estado final: Saldo actualizado.",
            "deducted_from_seller" => "Se han descontado :amount USD de su cuenta.",
            "released_to_buyer" => "Se han liberado :net USD a su cuenta.",
            "rate_invite" => "Su experiencia ayudaría a crear un lugar más seguro para todos",
            "rate_instruction" => "Toca un emoji para valorar este intercambio",
        ],
        "disputed" => [
            "title" => "Transaccion en DISPUTA",
            "claim_started" => "Se ha iniciado una reclamación de esta operación.",
            "arbiter_will_review" => "Un árbitro revisará el caso pronto.",
            "send_evidence_note" => "Envíe evidencia de que cumplió con su parte del acuerdo.",
            // Forum topic
            "forum_header" => "DISPUTA ABIERTA",
            "forum_buyer" => "Comprador",
            "forum_seller" => "Vendedor",
            "proofs_section" => "COMPROBANTES Y EVIDENCIAS",
            "by_buyer" => "Archivos del Comprador",
            "by_seller" => "Archivos del Vendedor",
            "no_proofs" => "Sin comprobantes ni evidencias registrados.",
            // Arbiter action buttons (inline keyboard)
            "arbiter_actions" => "Acciones del árbitro:",
            "btn_reqnew" => "Solicitar más evidencias",
            "btn_reqctr" => "Solicitar evidencias a contraparte",
            "btn_favor_buyer" => "Fallo a favor del COMPRADOR",
            "btn_favor_seller" => "Fallo a favor del VENDEDOR",
            // requestNewEvidenceFromUser
            "insufficient_title" => "Evidencias insuficientes",
            "insufficient_body" => "El árbitro ha revisado tus evidencias y necesita información adicional. Por favor envía nuevas evidencias del intercambio.",
            "insufficient_thread_note" => "Se solicitaron nuevas evidencias al usuario",
            "insufficient_sent" => "Solicitud enviada al usuario.",
            // requestEvidenceFromCounterpart
            "ctrpart_title" => "Se requieren evidencias",
            "ctrpart_line1" => "Se necesitan evidencias de su parte para resolver la disputa.",
            "ctrpart_line2" => "Tiene :time para enviarlas o el fallo sería en su contra.",
            "ctrpart_thread_note" => "Se solicitaron evidencias a la contraparte",
            "ctrpart_sent" => "Solicitud enviada a la contraparte.",
            // solveDispute
            "solved_thread" => "DISPUTA RESUELTA",
            "solved_done" => "Disputa resuelta en la blockchain.",
        ],
        "cancelled" => [
            "title" => "Oferta Cancelada",
            "cancelled_by_buyer" => "La Oferta ha sido cancelada por el comprador.",
            "cancelled_by_self" => "Ud ha cancelado la aplicación a la Oferta.",
            "funds_returned" => "Estamos procesando la devolución de :amount USD a su cuenta.",
        ],
        "cancelled_by_withdrawal" => [
            "title" => "Ofertas Canceladas Automáticamente",
            "reason" => "Se detectó una salida de :amount USD de su cuenta.",
            'offers' => 'Se ha cancelado :count oferta por fondos insuficientes|Se han cancelado :count ofertas por fondos insuficientes',
            "info" => "Las ofertas de venta requieren que los fondos estén disponibles en su cuenta.",
        ],
        "signed" => [
            "pending_title" => "Confirmacion Pendiente",
            "counterpart_confirmed" => "La contraparte ya ha confirmado esta transacción.",
            "proceed_confirm" => "Proceda a confirmar; evite que haya retrasos.",
            "waiting" => "Estamos esperando por Ud...",
        ],
        "solved" => [
            "title" => "Transaccion REVISADA",
            "admin_reviewed" => "Un administrador ha revisado las evidencias presentadas.",
            "winner" => "La disputa ha finalizado a su favor.",
            "funds_released" => "Se han liberado :net USD a su cuenta.",
            "thanks" => "Gracias por confiar en nosotros.",
            "loser" => "Le informamos que el arbitraje ha concluido a favor de la contraparte.",
            "contact_support" => "Si tiene dudas, contacte a soporte con el ID único de la transacción.",
        ],
        "expired" => [
            "title" => "Transaccion EXPIRADA",
            "seller_reported" => "El vendedor ha informado que esta operación no fue pagada en :time.",
            "auto_dispute" => "Se abrirá una DISPUTA automáticamente.",
            "funds_frozen" => "Los fondos estarán congelados hasta que la administración revise el caso.",
        ],
        "pending" => [
            "title" => "Procesando transacción...",
            "creating_seller" => [
                "title" => "Alguien ha aplicado a su oferta.",
                "line1" => "Los fondos están siendo movidos desde su cuenta al sistema de garantía.",
                "line2" => "El proceso puede tardar entre 1 y 3 minutos: le notificaremos en breve sobre el siguiente paso.",
            ],
            "creating_buyer" => [
                "title" => "Procesando tu solicitud de intercambio.",
                "line1" => "Los fondos del vendedor están siendo enviados al sistema de garantía para que pueda realizar el pago con confianza.",
                "line2" => "El proceso puede tardar entre 1 y 3 minutos: le notificaremos en breve sobre el siguiente paso.",
            ],
            "cancelling" => [
                "line1" => "Se detectó tu cancelación.",
                "line2" => "Esto puede tardar entre 1 y 3 minutos; por ahora no necesita hacer nada más.",
            ],
            "signing_proof" => [
                "title" => "Tu comprobante de pago fue enviado.",
                "line1" => "Ahora esperamos que el vendedor confirme la recepción.",
                "line2" => "No necesita hacer nada más por ahora; le notificaremos sobre el siguiente paso.",
            ],
            "signing_proof_seller" => [
                "title" => "El comprador ha enviado su comprobante de pago",
                "line1" => "Revisa tu cuenta bancaria para verificar que el pago fue recibido.",
                "line2" => "SOLO CUANDO HAYAS CONFIRMADO la recepción, presiona el botón para liberar los fondos.",
            ],
            "signing_confirm" => [
                "line1" => "Tu confirmación fue enviada.",
                "line2" => "Estamos cerrando el intercambio. Puede tardar 1 a 3 minutos. Te avisaremos cuando esté listo.",
            ],
            "closing_buyer" => [
                "line1" => "¡El vendedor confirmó la recepción de tu pago!",
                "line2" => "Los fondos están siendo liberados hacia tu cuenta.",
                "line3" => "Puede tardar entre 1 y 3 minutos. No necesitas hacer nada más.",
            ],
            "closing_seller" => [
                "line1" => "¡Confirmaste exitosamente la recepción del pago!",
                "line2" => "Estamos liberando los fondos a la contraparte.",
                "line3" => "Esto puede tardar entre 1 y 3 minutos; por ahora no necesita hacer nada más.",
            ],
            "expiring" => [
                "line1" => "Se detectó la expiración del intercambio.",
                "line2" => "Esto puede tardar entre 1 y 3 minutos; por ahora no necesita hacer nada más.",
            ],
            "dispute" => [
                "opener_line1" => "Tu solicitud de disputa fue registrada.",
                "opener_line2" => "Un árbitro revisará el caso lo antes posible.",
                "buyer_opened_counterpart_line1" => "El comprador ha abierto una disputa sobre este intercambio.",
                "buyer_opened_counterpart_line2" => "Un árbitro revisará el caso en breve. Asegúrese de tener evidencias listas para enviár cuando se lo soliciten.",
                "seller_opened_counterpart_line1" => "El vendedor ha abierto una disputa sobre este intercambio.",
                "seller_opened_counterpart_line2" => "Un árbitro revisará el caso en breve. Asegúrese de tener evidencias listas para enviár cuando se lo soliciten.",
            ],
            "resolving" => [
                "line1" => "Un árbitro está revisando este caso.",
                "line2" => "En breve se emitirá el veredicto en base a las evidencias aportadas...",
            ],
        ],
    ],

    // =========================================================
    // WIZARD — Asistente de creación de ofertas
    // =========================================================

    "wizard" => [
        "title" => "Asistente de creación de ofertas",
        "cancelled_title" => "Operacion cancelada.",
        "cancelled" => "Ud ha cancelado la publicación de su Oferta satisfactoriamente.",
        "step" => "Paso :n de :total",
        "step1" => [
            "subtitle" => "Definir el monto de la transacción",
            "invalid_amount" => ":value no es un monto válido",
            "ask_sell" => "Cuántos de sus :balance USD disponibles desea vender? Escriba solo el número. Ejemplo: :example",
            "ask_buy" => "Cuántos USD desea comprar? Escriba solo el número. Ejemplo: 100",
            "selling_too_much" => "Intentas vender :amount USD",
            "ask_sell_available" => "Cuántos de sus :balance USD disponibles desea vender?",
            "number_hint" => "Escriba solo el número. Ejemplo:",
        ],
        "step2" => [
            "subtitle" => "Moneda local del intercambio",
            "ask_sell" => "En qué moneda recibirá el pago?",
            "ask_buy" => "En qué moneda enviará el pago?",
            "select_available" => "Seleccione una de las disponibles",
        ],
        "step3" => [
            "subtitle" => "Precio de venta USD/:coin",
            "invalid_price" => ":value no es un precio válido",
            "ask_sell" => "Cuántos :coin desea recibir por cada USD?",
            "ask_buy" => "Cuántos :coin desea pagar por cada USD?",
            "example" => "Por ejemplo:",
        ],
        "step4" => [
            "subtitle" => "Método de pago deseado",
            "ask_sell" => "Por qué vía deben enviarle sus :currency?",
            "ask_buy" => "Por qué vía enviará usted los :currency?",
            "select_available" => "Seleccione una de las disponibles",
        ],
        "step5" => [
            "subtitle" => "Datos de la cuenta",
            "ask_sell" => "Escriba los detalles de su cuenta :method:",
            "ask_buy" => "Escriba los detalles o bancos desde donde pagará por :method:",
            "be_explicit" => "Recuerde ser explícito, cualquier dato faltante podría afectar el tiempo de la transacción.",
            "saved_value" => "Datos guardados",
            "saved_hint" => "Pulse el botón para usar estos datos, o escriba datos diferentes solo para esta oferta.",
            "use_saved" => "Usar mis datos guardados",
        ],
        "confirm" => [
            "title" => "Resumen de su Oferta",
            "selling" => "Vendes",
            "buying" => "Compras",
            "receiving" => "Recibes",
            "paying" => "Pagas",
        ],
    ],

    // =========================================================
    // PUBLISH — Respuesta tras publicar una oferta
    // =========================================================

    "offer_publish" => [
        "title" => "SU OFERTA YA ESTA ACTIVA",
        "published" => "Su anuncio ha sido publicado en nuestro canal.",
        "security_notes" => "NOTAS IMPORTANTES DE SEGURIDAD:",
        "sell_lock" => "Bloqueo de Garantia: Tan pronto como un interesado aplique a su oferta, los fondos serán bloqueados automáticamente. Esto garantiza al comprador que existen y estarán disponibles para su compra.",
        "sell_rule" => "Regla de Oro: NUNCA libere los fondos hasta que haya verificado manualmente la recepción del pago en su cuenta.",
        "buy_custody" => "Custodia Segura: Una vez que el vendedor acepte su compra, sus USD quedarán bloqueados por el sistema hasta que usted confirme el pago fiat.",
        "buy_rule" => "Regla de Oro: Realice el pago únicamente por los medios acordados y conserve su comprobante.",
        "arbitrage" => "Sistema de arbitraje: Nuestro equipo de soporte está listo para intervenir en caso de cualquier disputa durante el proceso.",
        "goodluck_sell" => "Suerte con tu venta.",
        "goodluck_buy" => "Suerte con tu compra.",
        "notify" => "Le notificaremos en cuanto alguien aplique.",
        "error" => "Error al publicar en el canal.",
        "error_retry" => "Por favor, intente nuevamente o contacte a soporte.",
    ],

    // =========================================================
    // SHOW — Mostrar una oferta
    // =========================================================
    "show_offer" => [
        "status_title" => [
            "new" => "NUEVA OFERTA",
            "recent" => "OFERTA RECIENTE",
            "available" => "OFERTA DISPONIBLE",
            "locked" => "OFERTA EN CURSO",
            "signed" => "OFERTA EN CURSO",
            "disputed" => "OFERTA EN DISPUTA",
            "solved" => "DISPUTA RESUELTA",
            "expired" => "OFERTA EXPIRADA",
            "cancelled" => "OFERTA CANCELADA",
            "completed" => "OFERTA COMPLETADA",
            "default" => "OFERTA ACTUALIZADA",
        ],
        "not_found_title" => "Que raro",
        "not_found" => "No he encontrado la oferta",
    ],

    // =========================================================
    // APPLY — Flujo de aplicar a una oferta
    // =========================================================

    "apply_offer" => [
        "being_processed" => "Esta oferta ya está siendo procesada por otro usuario.",
        "not_available" => "La oferta :code ya no está disponible.",
        "step1" => "Paso 1/3: Generando firma de seguridad...",
        "step2" => "Paso 2/3: Asegurando el intercambio...",
        "step3" => "Paso 3/3: Moviendo fondos para garantizar el intercambio...",
        "no_hash" => "No se pudo obtener el hash de la transacción.",
        "network_error" => "Error en la red:",
    ],

    // =========================================================
    // RECOVER — Recuperar/Expirar una oferta
    // =========================================================

    "recover_offer" => [
        "checking" => "Verificando condiciones de recuperación...",
        "not_found_blockchain" => "No se encontró el intercambio.",
        "wait" => "Aún no puedes reclamar. Faltan :minutes minutos para que expire el plazo del comprador.",
        "wait_scheduled" => "Te enviaremos un aviso cuando puedas iniciar la recuperación de tus fondos.",
        "ready_title" => "Ya puedes reclamar",
        "ready_body" => "El comprador no completó el pago en el tiempo establecido. Puedes iniciar la recuperación de tus fondos ahora.",
        "ready_button" => "Reclamar fondos ahora",
        "requesting" => "Solicitando devolución sin gas...",
        "success" => "Fondos en revision. El intercambio ha sido cancelado por expiración: un árbitro revisará que no haya pendientes y sus :amount USD serán devueltos a su cuenta.",
        "rejected" => "La red rechazó la solicitud de expiración.",
        "error" => "Error al recuperar:",
    ],

    // =========================================================
    // SIGN — Firmar una oferta (buyer/seller)
    // =========================================================

    "sign_offer" => [
        "not_found" => "Oferta no encontrada.",
        "wrong_state" => "Esta oferta no puede ser firmada en su estado actual.",
        "account_not_found" => "No se encontró tu cuenta.",
        "sending_proof" => "Enviando confirmación de pago...",
        "no_confirm_payment" => "No se pudo confirmar el pago.",
        "proof_sent" => "Comprobante enviado.",
        "confirming_receipt" => "Confirmando recepción del pago...",
        "no_sign" => "No se pudo confirmar.",
        "confirmation_sent" => "Confirmación enviada.",
        "error" => "Error:",
    ],

    // =========================================================
    // CANCEL ON-CHAIN — Cancelar una oferta bloqueada
    // =========================================================

    "cancel_onchain" => [
        "not_found" => "Oferta no encontrada.",
        "wrong_state" => "Solo puedes cancelar intercambios que estén bloqueados.",
        "not_buyer" => "Solo el comprador puede cancelar este intercambio.",
        "processing" => "Procesando cancelación...",
        "no_cancel" => "No se pudo cancelar el intercambio.",
        "sent" => "Cancelación enviada...",
        "error" => "Error al cancelar:",
    ],

    // =========================================================
    // DELETE OFFER — Eliminar oferta abierta (solo DB)
    // =========================================================

    "delete_offer" => [
        "not_found" => "Oferta no encontrada.",
        "no_permission" => "No tienes permiso para cancelar esta oferta.",
        "wrong_state" => "Esta oferta ya está en proceso de intercambio y no puede ser cancelada.",
        "success_title" => "Oferta eliminada",
        "success" => "La oferta ha sido retirada del mercado con éxito.",
        "error" => "Ocurrió un error al procesar la cancelación.",
    ],

    // =========================================================
    // RATE — Valorar una oferta
    // =========================================================

    "rate_offer" => [
        "success_title" => "Valoración recibida",
        "thanks" => "Gracias por ayudar a la comunidad",
        "not_found_title" => "Que raro",
        "not_found" => "No he encontrado la oferta",
        "selected" => "Has seleccionado :stars/5",
        "comment_prompt" => "Si desea, escriba un comentario sobre su experiencia en este intercambio.",
        "comment_prompt_italic" => "O pulse Omitir para continuar sin comentario.",
        "comment_skip" => "Omitir comentario",
        "comment_saved" => "Su comentario ha sido guardado.",
        "cancelled" => "Valoración cancelada.",
    ],

    // =========================================================
    // EVIDENCE — Envío de evidencias en disputa
    // =========================================================

    "evidence_offer" => [
        "title" => "Envío de evidencias",
        "instructions" => "Para enviar sus evidencias del intercambio, comparta en este chat:",
        "screenshots" => "Capturas de pantalla del pago",
        "receipts" => "Comprobantes bancarios o de transferencia",
        "arbiter_note" => "Un árbitro revisará las evidencias y resolverá la disputa.",
    ],

    // =========================================================
    // PROOF WIZARD — Asistente de envio de comprobante
    // =========================================================

    "proof_wizard" => [
        "title" => "Enviar Comprobante de Pago",
        "wizard_started" => "Asistente de comprobante iniciado. Envie su imagen cuando este listo.",
        "instructions" => "Envie al menos una imagen de su comprobante de pago",
        "image_received" => "Imagen recibida. Lleva :count en total.",
        "ask_more" => "Desea enviar alguna imagen mas?",
        "yes_more" => "Si, enviar otra",
        "no_done" => "No, eso es todo",
        "no_images" => "No ha enviado ninguna imagen. Por favor envie al menos una imagen.",
        "invalid_content" => "Solo se aceptan imagenes.",
        "cancelled" => "Ha cancelado el envio del comprobante.",
        "seller_notified" => "Su comprobante ha sido enviado. El vendedor debera confirmar la recepcion del pago.",
        "seller_notification_title" => "Comprobante de pago",
        "seller_notification_body" => "El comprador afirma haber realizado el pago.",
        "seller_notification_warning" => "No confirme la transaccion sin antes verificar manualmente que el dinero fue recibido.",
    ],

    // =========================================================
    // EVIDENCE WIZARD — Asistente de envio de evidencias
    // =========================================================

    "evidence_wizard" => [
        "title" => "Enviar Evidencias del Intercambio",
        "instructions" => "Envie las imagenes de sus evidencias del intercambio.",
        "image_received" => "Imagen recibida: :count en total.",
        "ask_more" => "¿Desea enviar alguna más?",
        "yes_more" => "Si, enviar otra",
        "no_done" => "No, eso es todo",
        "no_images" => "No ha enviado ninguna imagen. Por favor envie al menos una imagen.",
        "invalid_content" => "Solo se aceptan imagenes. Por favor envie una foto o un archivo de imagen (JPG, PNG, etc.).",
        "cancelled" => "Ha cancelado el envio de evidencias.",
        "arbiter_dispute_context" => "El árbitro revisará el caso. Envíe las evidencias que demuestren que cumplió con su parte del acuerdo.",
        "arbiter_notified" => "Sus evidencias han sido enviadas al equipo de arbitraje para su revision.",
        "arbiter_notification_title" => "Nuevo envio de evidencias en disputa",
        "arbiter_notification_body" => "El usuario ha enviado evidencias para la revision del caso. Vea las imagenes adjuntas.",
        // Forum thread
        "thread_more_requested" => "El árbitro ha solicitado más evidencias a las partes.",
        // Solicitud de más evidencias
        "more_requested_title" => "El árbitro solicita más evidencias",
        "more_requested_body" => "El árbitro necesita información adicional para resolver la disputa. Por favor envíe nuevas evidencias.",
        "more_requested_sent" => "Solicitud de evidencias enviada a ambas partes.",
    ],

    // =========================================================
    // PROOF RESUBMIT — Vendedor dice no haber recibido, comprador reenvía evidencias
    // =========================================================

    "proof_resubmit" => [
        "wizard_context" => "El vendedor indica que no ha recibido el pago. Envíe nuevas evidencias que demuestren el pago, o abra una disputa si considera que actúa de mala fe.",
        "seller_notified" => "Se notificó al comprador. Recibirá nuevas evidencias en breve.",
        "new_evidence_title" => "El comprador ha enviado nuevas evidencias",
        "new_evidence_body" => "Revisa las imágenes adjuntas. Confirma si recibiste el pago o indica que no lo has recibido.",
        "buyer_submitted" => "Tus evidencias fueron enviadas al vendedor. Puedes abrir una disputa si consideras que no actúa de buena fe.",
        "cancelled" => "Has cancelado el envío de evidencias.",
        "opening_dispute" => "Abriendo disputa...",
        "dispute_opened" => "Disputa abierta. El árbitro revisará el caso.",
        "dispute_error" => "No se pudo abrir la disputa.",
    ],

    // =========================================================
    // PAYMENT WIZARD — Asistente de configuración de métodos de pago
    // =========================================================

    "payment_wizard" => [
        "title" => "Mis Métodos de Pago",
        "cancelled_title" => "Asistente cerrado.",
        "cancelled" => "Sus datos ya guardados se han conservado.",
        "saved_title" => "Configuración completada",
        "saved_body" => "Sus métodos de pago han quedado guardados correctamente.",
        "current_value" => "Valor actual",
        "not_configured" => "Sin configurar",
        "ask_details" => "Escriba sus datos de :method (correo, número de cuenta, teléfono, etc.) y pulse Enter para guardar.",
        "next_hint" => "Pulse Siguiente para saltar este método sin modificarlo.",
        "next" => "Siguiente",
        "clear" => "Limpiar",
    ],

    // =========================================================
    // ACTIVE OFFERS — Lista de ofertas activas
    // =========================================================

    "active_offers" => [
        "empty" => "No tienes ofertas activas en este momento.",
        "empty_cta" => "Publica una o explora el mercado.",
        "title" => "Tus Ofertas Activas",
    ],

    // =========================================================
    // ALERTS WIZARD — Asistente de creación de alertas
    // =========================================================

    "alerts_wizard" => [
        "title" => "Asistente de Alertas de Ofertas",
        "cancelled_title" => "Operación cancelada.",
        "cancelled" => "Has cancelado la configuración de la alerta.",
        "step" => "Paso :n de :total",
        "any" => "Cualquiera",
        "unlimited" => "Sin límite",
        "step1" => [
            "subtitle" => "Tipo de oferta a vigilar",
            "ask" => "¿Sobre qué tipo de oferta quieres recibir alertas?",
            "select" => "Elige una opción",
            "option_buy" => "Oferta de Compra",
            "option_sell" => "Oferta de Venta",
        ],
        "step2" => [
            "subtitle" => "Método de pago",
            "ask" => "¿Por qué método de pago quieres filtrar?",
            "select" => "Elige un método o selecciona cualquiera",
            "any" => "Cualquier método",
        ],
        "step3" => [
            "subtitle" => "Precio máximo por USD",
            "ask" => "¿Cuál es el precio máximo que aceptas pagar por cada USD?",
            "example" => "Por ejemplo:",
            "any" => "Sin límite de precio",
            "invalid" => ":value no es un precio válido",
        ],
        "confirm" => [
            "title" => "Resumen de tu Alerta",
            "type" => "Tipo de oferta",
            "method" => "Método de pago",
            "max_price" => "Precio máximo",
            "save" => "Guardar Alerta",
        ],
    ],

    // =========================================================
    // ALERTS — Listado y gestión de alertas
    // =========================================================

    "alerts" => [
        "title" => "Mis Alertas de Ofertas",
        "empty" => "No tienes alertas configuradas aún.",
        "create" => "Crear nueva alerta",
        "view_mine" => "Mis Alertas",
        "delete" => "Eliminar",
        "deleted" => "Alerta eliminada.",
        "saved" => "¡Tu alerta ha sido configurada!",
        "watching" => "Estamos observando las nuevas ofertas y te notificaremos en cuanto aparezca una que coincida con tu criterio.",
        "type_buy" => "Oferta de Compra",
        "type_sell" => "Oferta de Venta",
        "method_any" => "Cualquier método",
        "price_unlimited" => "Sin límite de precio",
        "price_max" => "Máx. :price / USD",
    ],

    // =========================================================
    // ALERT MATCH — Notificación de coincidencia
    // =========================================================

    "alert_match" => [
        "title" => "Oferta encontrada",
        "line1" => "Una nueva oferta coincide con tus criterios de alerta:",
        "view" => "Ver Oferta",
    ],

    // =========================================================
    // BUY MATCH — Sugerencia de ventas al publicar una compra
    // =========================================================

    "buy_match" => [
        "title" => "Ofertas de venta disponibles para ti",
        "subtitle" => "Estas son las 3 mejores ofertas de venta que se ajustan a tu oferta de compra. Puedes aplicar directamente a cualquiera de ellas:",
        "offer_label" => "Opción #:n",
        "hint" => "Toca cualquier botón para ver los detalles y aplicar.",
        "view_all" => "Ver todas las ofertas",
    ],

    // =========================================================
    // OPTIONS — nuevas entradas para el menú
    // =========================================================

    // =========================================================
    // NETWORK STATUS — /network
    // =========================================================
    "network" => [
        "header" => "ESTADO DE",
        "token_label" => "Token Principal",
        "gas" => "Gas Actual",
        "tx_cost" => "Costo de Tx",
        "fee_escrow" => "Fee Escrow",
        "min_fee" => "MinFee Actual",
        "avg_trade" => "Basado en trades promedio de",
        "alert" => "ALERTA",
        "loss" => "Estás operando en pérdida con trades de",
        "healthy" => "SISTEMA SALUDABLE",
        "margin_intro" => "Tienes un margen del",
        "margin_over" => "sobre el MinFee.",
        "btn_reload" => "Volver a cargar",
        "error_connect" => "Error: No se pudo conectar con la Blockchain.",
        "error_report" => "Error al procesar el reporte",
    ],

    // =========================================================
    // DEPOSIT WIZARD — Asistente de deposito via TronDealer
    // =========================================================
    "deposit_wizard" => [
        "error_no_pairs" => "No se pudieron obtener monedas disponibles. Intenta mas tarde.",
        "error_unavailable" => "No hay monedas disponibles en este momento.",
        "header" => "Deposito via Swap",
        "select_pair" => "Selecciona la red y moneda desde donde enviaras los fondos:",
        "polygon_notice" => "Los fondos llegaran como :token en :network.",
        "min_amount" => "Minimo: :amount :asset",
        "max_amount" => "Maximo: :amount :asset",
        "error_below_min" => "El valor :amount :asset es menor al minimo permitido (:min).",
        "error_above_max" => "El valor :amount :asset supera el maximo permitido (:max).",
        "ask_amount" => "Cuanto :asset deseas enviar?",
        "selected_network" => "Red seleccionada: :asset (:chain)",
        "amount_hint" => "Escribe el monto a depositar (solo numeros, ej: 50):",
        "error_quote" => "No se pudo obtener la cotizacion. Intenta de nuevo.",
        "error_quote_now" => "Cotizacion no disponible en este momento.",
        "quote_header" => "Resumen del Deposito",
        "quote_you_send" => "Envias:",
        "quote_you_receive" => "Recibes aprox:",
        "quote_disclaimer" => "El monto recibido es estimado e incluye las comisiones del servicio.",
        "quote_confirm" => "Confirmas que deseas hacer este deposito?",
        "error_create" => "No se pudo crear el deposito. Intenta de nuevo mas tarde.",
        "success_header" => "Swap creado exitosamente",
        "swap_id_label" => "ID de referencia:",
        "send_to" => "Envia :amount :asset (:chain) a esta direccion:",
        "expires_label" => "Expira:",
        "monitor_notice" => "Monitorearemos el deposito automaticamente y te notificaremos al completarse.",
        "cancelled" => "Deposito cancelado.",
        "btn_back" => "Volver",
        "btn_cancel" => "Cancelar",
        "btn_confirm" => "Confirmar",
    ],

    // =========================================================
    // TDEPOSIT — Vista de swap activo y botones de menu
    // =========================================================
    "tdeposit" => [
        "active_header" => "Tienes un deposito activo",
        "send_to" => "Envia :amount :asset (:chain) a:",
        "expires_label" => "Expira:",
        "notify_notice" => "Cuando recibamos los fondos te notificaremos.",
        "btn_deposit" => "Depositar otras monedas",
        "btn_myswaps" => "Mis depositos",
    ],

    // =========================================================
    // DEPOSITS VIEW — Lista de swaps del usuario
    // =========================================================
    "deposits_view" => [
        "header" => "Mis Swaps",
        "empty" => "No tienes swaps registrados.",
        "expires" => "Expira:",
        "btn_refresh" => "Actualizar",
        "status" => [
            "pending" => "Pendiente",
            "waiting_deposit" => "Esperando deposito",
            "deposit_detected" => "Deposito detectado",
            "processing" => "Procesando",
            "completed" => "Completado",
            "expired" => "Expirado",
            "failed" => "Fallido",
            "refund_required" => "Requiere reembolso",
            "refunded" => "Reembolsado",
            "rejected" => "Rechazado",
        ],
    ],

    // =========================================================
    // AGENTS — Etiquetas de rol
    // =========================================================
    "agents" => [
        "role_none" => "Sin rol",
        "role_user" => "Usuario",
        "role_admin" => "Admin",
    ],

    // =========================================================
    // TRADING — Mensajes de ordenes DCA
    // =========================================================
    "trading" => [
        "long_opening" => "Cambiando :amount :quote a :asset...",
        "completed" => "Completado",
        "exit_closing" => "Cerrando :count ordenes acumuladas: :amount :asset...",
    ],

    // =========================================================
    // WALLET ERRORS — Mensajes de error de billetera
    // =========================================================
    "wallet_error" => [
        "no_wallet" => "No tienes wallet configurada.",
        "no_wallet_user" => "El usuario :id no tiene wallet configurada.",
    ],

    // =========================================================
    // SWAP (TronDealer) — Notificaciones del flujo de deposito
    // =========================================================
    "swap" => [
        "deposit_detected" => [
            "title" => "Deposito detectado",
            "body" => "Recibimos tu deposito de :amount :asset (:chain).",
            "footer" => "Esperando confirmaciones on-chain para ejecutar el swap.",
        ],
        "processing" => [
            "title" => "Procesando swap",
            "body" => "Las confirmaciones de tu deposito de :amount :asset fueron recibidas.",
            "footer" => "Ejecutando el swap hacia :token en :network. Te avisamos al completarse.",
        ],
        "completed" => [
            "title" => "Swap completado",
            "sent" => "Enviaste :amount :asset (:chain).",
            "received" => "Recibido en contrato: :amount_out :token (:network).",
            "footer" => "Tu saldo estara disponible en breve.",
        ],
        "expired" => [
            "title" => "Swap expirado",
            "body" => "No se recibio ningun deposito dentro del tiempo limite para tu swap de :amount :asset.",
            "footer" => "Puedes iniciar un nuevo deposito cuando quieras.",
        ],
        "failed" => [
            "title" => "Swap fallido",
            "body" => "Hubo un problema procesando tu swap de :amount :asset.",
            "footer" => "Si enviaste fondos, el proveedor los devolvera automaticamente. Contacta soporte si no recibes el reembolso.",
        ],
        "rejected" => [
            "title" => "Swap rechazado",
            "body" => "Tu swap de :amount :asset fue rechazado por el proveedor.",
            "footer" => "Si enviaste fondos, seran devueltos automaticamente. Contacta soporte si necesitas ayuda.",
        ],
        "refund_required" => [
            "title" => "Reembolso en proceso",
            "body" => "Tu swap de :amount :asset no pudo completarse.",
            "footer" => "Los fondos seran devueltos a la direccion de origen.",
        ],
        "refunded" => [
            "title" => "Reembolso realizado",
            "body" => "Tu swap de :amount :asset no pudo completarse.",
            "footer" => "Los fondos seran devueltos a la direccion de origen.",
        ],
        "btn_mainmenu" => "Menu principal",
    ],

    // =========================================================
    // CONTRACT — /contract (solo admins)
    // =========================================================
    "contract" => [
        "header" => "Estado del Contrato Escrow",
        "network_label" => "Red",
        "token_label" => "Token",
        "locked" => "Fondos bloqueados en trades activos",
        "fees" => "Fees disponibles para retirar",
        "address_label" => "Contrato",
        "btn_withdraw" => "Extraer fees",
        "btn_refresh" => "Actualizar",
        "btn_status" => "Ver estado del contrato",
        "access_denied" => "Acceso denegado. Este comando es solo para administradores.",
        "access_denied_short" => "Acceso denegado.",
        "error_connect" => "Error: No se pudo conectar con la Blockchain.",
        "error_withdraw" => "Error al ejecutar el retiro de fees. Revisa los logs para más detalles.",
        "withdraw_confirm_title" => "¿Retirar las fees acumuladas del contrato?",
        "withdraw_confirm_warning" => "Esta acción ejecutará una transacción on-chain y transferirá los fondos disponibles al árbitro.",
        "withdraw_success" => "Fees retiradas exitosamente",
    ],
];
