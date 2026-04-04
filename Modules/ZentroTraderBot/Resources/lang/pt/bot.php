<?php

return [
    "mainmenu" => [
        "description" => "A ponte mais rápida e segura para movimentar seu dinheiro entre Zelle, Bizum, Pago Móvil e mais, usando a estabilidade do USD digital.",
        "body" => "🔒 Seguro: Seus fundos estão protegidos por contratos inteligentes. 💸 Sem Gas: Você não paga taxas de rede, nós cuidamos disso. 🚀 Rápido: Trocas P2P em minutos.",
    ],
    "actionmenu" => [
        "header" => "Menu de ações",
        "line1" => "Usando o “Botão de Ação”, você pode alternar entre 2 níveis",
        "line2" => "Notificações: Quando um sinal aparece, o bot apenas notifica os usuários",
        "line3" => "Executar ordens: O bot notifica a comunidade e executa as ordens correspondentes",
        "line4" => "No momento, a opção “:bot_name” está selecionada",
    ],
    "subscribtionmenu" => [
        "header" => "Menu de assinatura",
        "line1" => "Aqui você pode ajustar suas preferências",
        "line2" => "Usando o botão “Nível”, você pode alternar entre 3 níveis",
        "line3" => "você receberá apenas sinais da comunidade",
        "line4" => "você receberá apenas seus alertas pessoais",
        "line5" => "você receberá alertas da comunidade e pessoais",
        "line6" => "Você é um assinante de nível :level",
        "therefore" => "portanto, você pode usar o botão “URL do Cliente” para obter seu link de alertas do TradingView",
    ],
    "options" => [
        "subscribtion" => "Assinatura",
        "subscribtionlevel" => ":icon Nível :char",
        "clienturl" => "URL do cliente",
        "backtosuscribemenu" => "Voltar ao menu de assinaturas",
        "actionmenu" => "Nível de ação",
        "actionlevel1" => "Notificações",
        "actionlevel2" => "Executar ordens",
        "selloffer" => "Vender USD",
        "buyoffer" => "Comprar USD",
        "balance" => "Consultar saldo",
        "topupcripto" => "Depositar Cripto",
        "topupramp" => "Depositar USD",
        "withdraw" => "Extrair USD",
    ],
    "prompts" => [
        "clienturl" => [
            "header" => "Sua URL de cliente é a seguinte",
            "warning" => "Este é o endereço que você deve usar no TradingView para notificar o bot que deseja trabalhar com um alerta personalizado",
            "text" => "Tem certeza de que deseja continuar?",
        ],
        "txsuccess" => "TX Bem-sucedida",
        "txfail" => "TX Falhou",
        "buy" => [
            "exchangetitle" => "Depositar em :name",
            "update" => [
                "header" => "Atualização de Depósito",
                "completed" => "Seus fundos estão a caminho da sua conta!",
                "failed" => "A transação FALHOU!",
                "processing" => "Estamos processando sua solicitação.",
            ],
            "completed" => [
                "header" => "Saldo Creditado!",
                "warning" => "Você recebeu un depósito de :amount USD.",
                "text" => "Seus fundos já estão disponíveis em sua conta para serem utilizados.",
            ],
            "badcurrency" => [
                "header" => "Saldo Recebido!",
                "warning" => "Você recebeu um depósito de :amount :currency.",
                "text" => "Estes fundos estão em :currency, vamos trocá-los para USD para creditá-los em sua conta...",
            ],
        ],
        "sell" => [
            "exchangetitle" => "Retirar de :name",
            "update" => [
                "header" => "Atualização de Extração",
                "completed" => "Seus fundos estão a caminho da sua conta!",
                "failed" => "A transação FALHOU!",
                "processing" => "Estamos processando sua solicitação.",
            ],
            "completed" => [
                "header" => "Saldo Creditado!",
                "text" => "Recebemos sua extração de :amount :currency corretamente",
            ],
        ],
        "fail" => [
            "suscriptor" => "Desculpe, não conseguimos encontrar sua carteira configurada.",
            "widgeturl" => "Desculpe, houve um erro ao gerar sua sessão de pagamento. Por favor, tente mais tarde.",
        ],
        "topup" => [
            "cripto" => [
                "header" => "Este é o seu endereço de depósito",
                "line1" => "Operamos com :token na rede :network",
                "line2" => "Os fundos em :token (:network) estarão disponíveis automaticamente.",
                "line3" => "Outra moeda requer troca para :token obrigatoriamente.",
                "options" => [
                    "debridge" => "Depositar usando DeBridge",
                    "seedphrase" => "Exportar frase semente",
                ],
            ],
        ],
        "seedphrase" => [
            "warning" => [
                "line1" => "Você está prestes a exibir sua *FRASE SEMENTE*",
                "line2" => "Qualquer pessoa que a veja terá *CONTROLE TOTAL E PERMANENTE de todos os seus fundos*",
                "line3" => "Certifique-se de que ninguém está olhando para sua tela",
            ],
            "export" => [
                "line1" => "Suas :count palavras de segurança",
                "line2" => "Copie ou escaneie estas informações rapidamente:",
                'destroy' => [
                    "segs" => 'Esta mensagem será excluída em :count segundo|Esta mensagem será excluída em :count segundos',
                    'mins' => 'Esta mensagem será excluída em :count minuto|Esta mensagem será excluída em :count minutos',
                ],
            ],
        ],
        "balance" => [
            "available" => "Saldo disponível",
            "lastoperations" => "Últimas operações",
        ],
    ],
];