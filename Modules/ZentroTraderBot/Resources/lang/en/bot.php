<?php

return [
    "mainmenu" => [
        "description" => "The fastest and safest bridge to move your money between Zelle, Bizum, Pago Móvil, and more, using the stability of digital USD.",
        "body" => "🔒 Secure: Your funds are protected by smart contracts. 💸 Gasless: You don't pay network fees; we take care of it. 🚀 Fast: P2P exchanges in minutes.",
    ],
    "actionmenu" => [
        "header" => "Action Menu",
        "line1" => "Using the “Action Button”, you can switch between 2 levels",
        "line2" => "Notifications: When a signal appears, the bot only notifies users",
        "line3" => "Execute orders: The bot notifies the community and executes the corresponding orders",
        "line4" => "The option “:bot_name” is currently selected",
    ],
    "subscribtionmenu" => [
        "header" => "Subscription Menu",
        "line1" => "Adjust your preferences here",
        "line2" => "Using the “Level” button, you can switch between 3 levels",
        "line3" => "you will only receive community signals",
        "line4" => "you will only receive your personal alerts",
        "line5" => "you will receive both community and personal alerts",
        "line6" => "You are a level :level subscriber",
        "therefore" => "therefore, you can use the “Client URL” button to get your TradingView alert link",
    ],
    "options" => [
        "subscribtion" => "Subscription",
        "subscribtionlevel" => ":icon Level :char",
        "clienturl" => "Client URL",
        "backtosuscribemenu" => "Back to subscription menu",
        "actionmenu" => "Action Level",
        "actionlevel1" => "Notifications",
        "actionlevel2" => "Execute Orders",
        "selloffer" => "Sell USD",
        "buyoffer" => "Buy USD",
        "balance" => "Check Balance",
        "topupcripto" => "Deposit Crypto",
        "topupramp" => "Deposit USD",
        "withdraw" => "Withdraw USD",
    ],
    "prompts" => [
        "clienturl" => [
            "header" => "Your client URL is as follows",
            "warning" => "This is the address you must use in TradingView to notify the bot that you want to work with a custom alert",
            "text" => "Are you sure you want to continue?",
        ],
        "txsuccess" => "TX Successful",
        "txfail" => "TX Failed",
        "buy" => [
            "exchangetitle" => "Deposit to :name",
            "update" => [
                "header" => "Deposit Update",
                "completed" => "Your funds are on their way to your account!",
                "failed" => "The transaction HAS FAILED!",
                "processing" => "We are processing your request.",
            ],
            "completed" => [
                "header" => "Balance Credited!",
                "warning" => "You have received a deposit of :amount USD.",
                "text" => "Your funds are now available in your account to be used.",
            ],
            "badcurrency" => [
                "header" => "Balance Received!",
                "warning" => "You have received a deposit of :amount :currency.",
                "text" => "These funds are in :currency; we will exchange them to USD to credit your account...",
            ],
        ],
        "sell" => [
            "exchangetitle" => "Withdraw from :name",
            "update" => [
                "header" => "Withdrawal Update",
                "completed" => "Your funds are on their way to your account!",
                "failed" => "The transaction HAS FAILED!",
                "processing" => "We are processing your request.",
            ],
            "completed" => [
                "header" => "Balance Credited!",
                "text" => "We have successfully received your withdrawal of :amount :currency",
            ],
        ],
        "fail" => [
            "suscriptor" => "Sorry, we could not find your configured wallet.",
            "widgeturl" => "Sorry, there was an error generating your payment session. Please try again later.",
        ],
        "topup" => [
            "cripto" => [
                "header" => "This is your deposit address",
                "line1" => "We operate with :token on the :network network",
                "line2" => "Funds in :token (:network) will be available automatically.",
                "line3" => "Any other currency must be exchanged to :token.",
                "options" => [
                    "debridge" => "Deposit using DeBridge",
                    "seedphrase" => "Export seed phrase",
                ],
            ],
        ],
        "seedphrase" => [
            "warning" => [
                "line1" => "You are about to display your *SEED PHRASE*",
                "line2" => "Anyone who sees it will have *TOTAL AND PERMANENT CONTROL over all your funds*",
                "line3" => "Ensure no one is watching your screen",
            ],
            "export" => [
                "line1" => "Your :count security words",
                "line2" => "Copy or scan this information quickly:",
                'destroy' => [
                    "segs" => 'This message will be deleted in :count second|This message will be deleted in :count seconds',
                    'mins' => 'This message will be deleted in :count minute|This message will be deleted in :count minutes',
                ],
            ],
        ],
        "balance" => [
            "available" => "Available balance",
            "lastoperations" => "Last operations",
        ],
    ],
];