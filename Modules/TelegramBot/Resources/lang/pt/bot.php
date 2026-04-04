<?php

return [
    "mainmenu" => [
        "salutation" => "Bem-vindo ao :bot_name",
        "referral" => "Link de indicação",
        "question" => "Como posso ajudar você hoje?",
    ],
    "adminmenu" => [
        "header" => "Menu de administrador",
        "warning" => "Aqui você encontrará ferramentas úteis para a gestão integral do bot",
    ],
    "configmenu" => [
        "header" => "Menu de configurações",
        "warning" => "Aqui você encontrará ajustes do comportamento do bot",
    ],
    "role" => [
        "admin" => "Admin",
    ],
    "options" => [
        "config" => "Configuração",
        "help" => "Ajuda",
        "yes" => "Sim",
        "no" => "Não",
        "cancel" => "Cancelar",
        "delete" => "Excluir",
        "sendannouncement" => "Anúncio",
        "viewusers" => "Usuários inscritos",
        "backtomainmenu" => "Voltar ao menu principal",
        "backtoadminmenu" => "Voltar ao menu de administrador",
        "deleteprevmessages" => "Excluir mensagens anteriores",
        "keepprevmessages" => "Manter mensagens anteriores",
        "timezone" => "Fuso horário :timezone",
    ],
    "prompts" => [
        "whatsnext" => "O que você deseja fazer agora?",
        "chooseoneoption" => "Escolha uma das seguintes opções",
        "areyousure" => [
            "header" => "Solicitação de confirmação",
            "warning" => "CUIDADO: Esta ação não pode ser revertida",
            "text" => "Tem certeza de que deseja continuar?",
        ],
        "notimplemented" => [
            "header" => "Função não implementada",
            "warning" => "Esta função ainda não está pronta. Estamos trabalhando nela para os próximos dias.",
        ],
        "announcement" => [
            "prompt" => "Enviar anúncio",
            "header" => "ATENÇÃO: Anúncio do sistema",
            "whatsnext" => "Escreva o anúncio que deseja enviar",
            "preparing" => [
                "header" => "Preparando anúncios",
                "warning" => "Anúncios serão enviados para :amount inscritos...",
            ],
            "sending" => [
                "header" => "Enviando anúncios...",
                "warning" => "Progresso: :amount de :total anúncios enviados.",
            ],
            "sent" => [
                "header" => "Envio concluído!",
                'destroy' => [
                    "segs" => 'Esta mensagem será excluída em :count segundo|Esta mensagem será excluída em :count segundos',
                    'mins' => 'Esta mensagem será excluída em :count minuto|Esta mensagem será excluída em :count minutos',
                ],
                'duration' => [
                    "header" => "Tempo total:",
                    "segs" => ':count segundo|:count segundos',
                    'mins' => ':count minuto|:count minutos',
                ],
            ],
        ],
        "userwithnorole" => [
            "header" => "Novo usuário inscrito no bot",
            "warning" => "Convidado por",
        ],
        "usernamerequired" => [
            "line1" => "Para usar este bot, por favor configure um nome de usuário (@usuario) em sua conta do Telegram",
            "line2" => "Como configurar?",
            "line3" => "Vá em Configurações (ou Ajustes)",
            "line4" => "Selecione seu perfil e procure a opção Nome de usuário",
            "line5" => "Escolha um nome único que comece com @",
            "line6" => "Depois de configurar seu nome de usuário, clique no botão abaixo",
            "done" => "Pronto, já fiz!",
        ],
    ],
    "errors" => [
        "header" => "Erro",
        "unrecognizedcommand" => [
            "text" => "Não sei o que responder para “:text”",
            "hint" => "Você pode interagir com este bot usando /menu ou consulte /ajuda para obter assistência",
        ],
    ],
    "scanner" => [
        "prompt" => "Escaneie a etiqueta",
        "localmode" => "SEM CONEXÃO - MODO LOCAL",
        "opencamera" => "Abrir Câmera",
        "online" => "On-line",
        "offline" => "Off-line",
        "synchronizing" => "Sincronizando",
        "procesing" => "Processando",
        "fetch" => [
            "title" => "Concluído!",
            "desc" => "códigos processados",
        ],
        "localstoragedcodes" => "códigos salvos localmente",
        "localstorageaction" => "Os códigos serão salvos no telefone",
        "loadinggps" => "Obtendo localização GPS",
        "gpsdeniedtitle" => "Você deve ativar e conceder permissões para sua localização GPS",
        "retrygps" => "Conceder permissões de GPS",
    ],
];