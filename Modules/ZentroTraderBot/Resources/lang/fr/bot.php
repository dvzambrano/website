<?php

return [
    "mainmenu" => [
        "description" => "Le pont le plus rapide et le plus sûr pour transférer votre argent entre Zelle, Bizum, Pago Móvil et plus encore, en utilisant la stabilité de l'USD numérique.",
        "body" => "🔒 Sécurisé : Vos fonds sont protégés par des contrats intelligents. 💸 Sans Gaz : Vous ne payez pas de frais de réseau, nous nous en occupons. 🚀 Rapide : Échanges P2P en quelques minutes.",
    ],
    "actionmenu" => [
        "header" => "Menu d'actions",
        "line1" => "En utilisant le « Bouton d'action », vous pouvez basculer entre 2 niveaux",
        "line2" => "Notifications : Lorsqu'un signal apparaît, le bot n'en informe que les utilisateurs",
        "line3" => "Exécuter les ordres : Le bot informe la communauté et exécute les ordres correspondants",
        "line4" => "L'option « :bot_name » est actuellement sélectionnée",
    ],
    "subscribtionmenu" => [
        "header" => "Menu d'abonnement",
        "line1" => "Ici, vous pouvez ajuster vos préférences",
        "line2" => "En utilisant le bouton « Niveau », vous pouvez basculer entre 3 niveaux",
        "line3" => "vous ne recevrez que les signaux de la communauté",
        "line4" => "vous ne recevrez que vos alertes personnelles",
        "line5" => "vous recevrez à la fois les alertes de la communauté et les alertes personnelles",
        "line6" => "Vous êtes un abonné de niveau :level",
        "therefore" => "par conséquent, vous pouvez utiliser le bouton « URL du client » pour obtenir votre lien d'alerte TradingView",
    ],
    "options" => [
        "subscribtion" => "Abonnement",
        "subscribtionlevel" => ":icon Niveau :char",
        "clienturl" => "URL du client",
        "backtosuscribemenu" => "Retour au menu abonnement",
        "actionmenu" => "Niveau d'action",
        "actionlevel1" => "Notifications",
        "actionlevel2" => "Exécuter les ordres",
        "selloffer" => "Vendre USD",
        "buyoffer" => "Acheter USD",
        "balance" => "Consulter le solde",
        "topupcripto" => "Déposer Cripto",
        "topupramp" => "Déposer USD",
        "withdraw" => "Retirer USD",
    ],
    "prompts" => [
        "clienturl" => [
            "header" => "Votre URL de client est la suivante",
            "warning" => "C'est l'adresse que vous devez utiliser dans TradingView pour notifier le bot que vous souhaitez travailler avec une alerte personnalisée",
            "text" => "Êtes-vous sûr de vouloir continuer ?",
        ],
        "txsuccess" => "TX Réussie",
        "txfail" => "TX Échouée",
        "buy" => [
            "exchangetitle" => "Déposer sur :name",
            "update" => [
                "header" => "Mise à jour du dépôt",
                "completed" => "Vos fonds sont en route vers votre compte !",
                "failed" => "La transaction A ÉCHOUÉ !",
                "processing" => "Nous traitons votre demande.",
            ],
            "completed" => [
                "header" => "Solde Crédité !",
                "warning" => "Vous avez reçu un dépôt de :amount USD.",
                "text" => "Vos fonds sont désormais disponibles sur votre compte pour être utilisés.",
            ],
            "badcurrency" => [
                "header" => "Solde Reçu !",
                "warning" => "Vous avez reçu un dépôt de :amount :currency.",
                "text" => "Ces fonds sont en :currency, nous les changerons en USD pour les créditer sur votre compte...",
            ],
        ],
        "sell" => [
            "exchangetitle" => "Retirer de :name",
            "update" => [
                "header" => "Mise à jour du retrait",
                "completed" => "Vos fonds sont en route vers votre compte !",
                "failed" => "La transaction A ÉCHOUÉ !",
                "processing" => "Nous traitons votre demande.",
            ],
            "completed" => [
                "header" => "Solde Crédité !",
                "text" => "Nous avons reçu correctement votre retrait de :amount :currency",
            ],
        ],
        "fail" => [
            "suscriptor" => "Désolé, nous n'avons pas pu trouver votre portefeuille configuré.",
            "widgeturl" => "Désolé, une erreur s'est produite lors de la génération de votre session de paiement. Veuillez réessayer plus tard.",
        ],
        "topup" => [
            "cripto" => [
                "header" => "Voici votre adresse de dépôt",
                "line1" => "Nous opérons avec :token sur le réseau :network",
                "line2" => "Les fonds en :token (:network) seront disponibles automatiquement.",
                "line3" => "Toute autre devise doit obligatoirement être changée en :token.",
                "options" => [
                    "debridge" => "Déposer via DeBridge",
                    "seedphrase" => "Exporter la phrase graine",
                ],
            ],
        ],
        "seedphrase" => [
            "warning" => [
                "line1" => "Vous êtes sur le point d'afficher votre *PHRASE GRAINE*",
                "line2" => "Quiconque la voit aura le *CONTRÔLE TOTAL ET PERMANENT de tous vos fonds*",
                "line3" => "Assurez-vous que personne ne regarde votre écran",
            ],
            "export" => [
                "line1" => "Vos :count mots de sécurité",
                "line2" => "Copiez ou scannez ces informations rapidement :",
                'destroy' => [
                    "segs" => 'Ce message sera supprimé dans :count seconde|Ce message sera supprimé dans :count secondes',
                    'mins' => 'Ce message sera supprimé dans :count minute|Ce message sera supprimé dans :count minutes',
                ],
            ],
        ],
        "balance" => [
            "available" => "Solde disponible",
            "lastoperations" => "Dernières opérations",
        ],
    ],
];