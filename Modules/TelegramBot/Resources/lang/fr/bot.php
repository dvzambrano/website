<?php

return [
    "maintenance" => [
        "message" => "Le bot est actuellement en maintenance.",
    ],
    "mainmenu" => [
        "salutation" => "Bienvenue sur :bot_name",
        "referral" => "Lien de parrainage",
        "question" => "Comment puis-je vous aider aujourd'hui ?",
    ],
    "adminmenu" => [
        "header" => "Menu administrateur",
        "warning" => "Vous trouverez ici des outils utiles pour la gestion intégrale du bot",
    ],
    "configmenu" => [
        "header" => "Menu de configuration",
        "warning" => "Vous trouverez ici les paramètres de comportement du bot",
    ],
    "role" => [
        "admin" => "Admin",
    ],
    "options" => [
        "config" => "Configuration",
        "help" => "Aide",
        "yes" => "Oui",
        "no" => "Non",
        "cancel" => "Annuler",
        "delete" => "Supprimer",
        "sendannouncement" => "Annonce",
        "viewusers" => "Utilisateurs inscrits",
        "backtomainmenu" => "Retour au menu principal",
        "backtoadminmenu" => "Retour au menu administrateur",
        "deleteprevmessages" => "Supprimer les messages précédents",
        "keepprevmessages" => "Conserver les messages précédents",
        "timezone" => "Fuseau horaire :timezone",
        "backtoconfigmenu" => "Retour au menu de configuration",
    ],
    "prompts" => [
        "whatsnext" => "Que souhaitez-vous faire maintenant ?",
        "chooseoneoption" => "Choisissez l'une des options suivantes",
        "areyousure" => [
            "header" => "Demande de confirmation",
            "warning" => "ATTENTION : Cette action est irréversible",
            "text" => "Êtes-vous sûr de vouloir continuer ?",
        ],
        "notimplemented" => [
            "header" => "Fonction non implémentée",
            "warning" => "Cette fonction n'est pas encore prête. Nous y travaillons pour une sortie prochaine.",
        ],
        "announcement" => [
            "prompt" => "Envoyer une annonce",
            "header" => "ATTENTION : Annonce du système",
            "whatsnext" => "Saisissez l'annonce que vous souhaitez envoyer",
            "preparing" => [
                "header" => "Préparation des annonces",
                "warning" => "Les annonces seront envoyées à :amount abonnés...",
            ],
            "sending" => [
                "header" => "Envoi des annonces...",
                "warning" => "Progression : :amount sur :total annonces envoyées.",
            ],
            "sent" => [
                "header" => "Envoi terminé !",
                'destroy' => [
                    "segs" => 'Ce message sera supprimé dans :count seconde|Ce message sera supprimé dans :count secondes',
                    'mins' => 'Ce message sera supprimé dans :count minute|Ce message sera supprimé dans :count minutes',
                ],
                'duration' => [
                    "header" => "Temps total :",
                    "segs" => ':count seconde|:count secondes',
                    'mins' => ':count minute|:count minutes',
                ],
            ],
        ],
        "userwithnorole" => [
            "header" => "Nouvel utilisateur inscrit au bot",
            "warning" => "Invité par",
        ],
        "usernamerequired" => [
            "line1" => "Pour utiliser ce bot, veuillez configurer un nom d'utilisateur (@utilisateur) dans votre compte Telegram",
            "line2" => "Comment le configurer ?",
            "line3" => "Allez dans Paramètres (ou Réglages)",
            "line4" => "Sélectionnez votre profil et cherchez l'option Nom d'utilisateur",
            "line5" => "Choisissez un nom unique commençant par @",
            "line6" => "Une fois votre nom d'utilisateur configuré, cliquez sur le bouton ci-dessous",
            "done" => "C'est fait, je l'ai fait !",
        ],
    ],
    "errors" => [
        "header" => "Erreur",
        "unrecognizedcommand" => [
            "text" => "Je ne sais pas quoi répondre à « :text »",
            "hint" => "Vous pouvez interagir avec ce bot via /menu ou consulter /ayuda pour obtenir de l'aide",
        ],
    ],
    "scanner" => [
        "prompt" => "Scanner l'étiquette",
        "localmode" => "HORS LIGNE - MODE LOCAL",
        "opencamera" => "Ouvrir la caméra",
        "online" => "En ligne",
        "offline" => "Hors ligne",
        "synchronizing" => "Synchronisation",
        "procesing" => "Traitement",
        "fetch" => [
            "title" => "Réussi !",
            "desc" => "codes traités",
        ],
        "localstoragedcodes" => "codes enregistrés localement",
        "localstorageaction" => "Les codes seront enregistrés sur le téléphone",
        "loadinggps" => "Obtention de la position GPS",
        "gpsdeniedtitle" => "Vous devez activer et accorder les permissions pour votre position GPS",
        "retrygps" => "Accorder les permissions GPS",
    ],
    "actors" => [
        "subscribers" => [
            "header" => "Utilisateurs inscrits",
            "body" => "Voici les :count utilisateurs qui se sont inscrits au bot.",
        ],
        "usernotfound" => [
            "header" => "Utilisateur introuvable",
            "before" => "L'utilisateur",
            "after" => "n'est pas inscrit a ce bot.",
        ],
        "role" => [
            "modified" => "Role de l'utilisateur modifie",
            "changed" => [
                "header" => "Votre role a ete modifie",
                "body" => "Nous vous recommandons de revenir au /menu pour verifier vos nouvelles options",
            ],
        ],
        "utc" => [
            "prompt" => [
                "header" => "Ajuster le fuseau horaire",
                "line1" => "Definir votre fuseau horaire permettra au bot de personnaliser les dates et heures.",
                "line2" => "Pour definir votre fuseau horaire au format UTC-4, ecrivez seulement -4.",
                "footer" => "Indiquez votre fuseau horaire:",
            ],
            "updated" => [
                "header" => "Fuseau horaire mis a jour",
                "body" => "Votre fuseau horaire a ete mis a jour avec succes.",
                "currenttime" => "Il est maintenant",
            ],
            "error" => [
                "header" => "Erreur de fuseau horaire",
                "before" => "Impossible de definir le fuseau horaire",
                "hint" => "Verifiez que vous avez entre un nombre valide pour ajuster l'heure.",
            ],
            "retry" => "Reessayer",
        ],
        "metadata" => [
            "add" => "Ajouter des metadonnees",
            "define" => [
                "header" => "Definir les metadonnees de l'abonne",
                "footer" => "Ecrivez ci-dessous:",
            ],
            "updated" => [
                "header" => "Metadonnees mises a jour",
                "body" => "Les metadonnees de l'abonne ont ete mises a jour avec succes.",
                "back" => "Afficher a nouveau l'abonne",
            ],
        ],
    ],
    "wizard" => [
        "cancelled" => "Wizard annule.",
    ],
];