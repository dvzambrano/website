<?php

return [
    "mainmenu" => [
        "salutation" => "Welcome to :bot_name",
        "referral" => "Referral link",
        "question" => "How can I help you today?",
    ],
    "adminmenu" => [
        "header" => "Admin Menu",
        "warning" => "Here you will find useful tools for the comprehensive management of the bot",
    ],
    "configmenu" => [
        "header" => "Settings Menu",
        "warning" => "Here you will find bot behavior settings",
    ],
    "role" => [
        "admin" => "Admin",
    ],
    "options" => [
        "config" => "Settings",
        "help" => "Help",
        "yes" => "Yes",
        "no" => "No",
        "cancel" => "Cancel",
        "delete" => "Delete",
        "sendannouncement" => "Announcement",
        "viewusers" => "Subscribed users",
        "backtomainmenu" => "Back to main menu",
        "backtoadminmenu" => "Back to admin menu",
        "deleteprevmessages" => "Delete previous messages",
        "keepprevmessages" => "Keep previous messages",
        "timezone" => "Timezone :timezone",
    ],
    "prompts" => [
        "whatsnext" => "What would you like to do now?",
        "chooseoneoption" => "Choose one of the following options",
        "areyousure" => [
            "header" => "Confirmation request",
            "warning" => "CAUTION: This action cannot be reversed",
            "text" => "Are you sure you want to continue?",
        ],
        "notimplemented" => [
            "header" => "Feature not implemented",
            "warning" => "This feature is not ready yet. We are working on it for release in the coming days.",
        ],
        "announcement" => [
            "prompt" => "Send announcement",
            "header" => "ATTENTION: System announcement",
            "whatsnext" => "Type the announcement you want to send",
            "preparing" => [
                "header" => "Preparing announcements",
                "warning" => "Announcements will be sent to :amount subscribers...",
            ],
            "sending" => [
                "header" => "Sending announcements...",
                "warning" => "Progress: :amount of :total announcements sent.",
            ],
            "sent" => [
                "header" => "Delivery completed!",
                'destroy' => [
                    "segs" => 'This message will be deleted in :count second|This message will be deleted in :count seconds',
                    'mins' => 'This message will be deleted in :count minute|This message will be deleted in :count minutes',
                ],
                'duration' => [
                    "header" => "Total time:",
                    "segs" => ':count second|:count seconds',
                    'mins' => ':count minute|:count minutes',
                ],
            ],
        ],
        "userwithnorole" => [
            "header" => "New user subscribed to the bot",
            "warning" => "Invited by",
        ],
        "usernamerequired" => [
            "line1" => "To use this bot, please set up a username (@username) in your Telegram account",
            "line2" => "How to set it up?",
            "line3" => "Go to Settings",
            "line4" => "Select your profile and look for the Username option",
            "line5" => "Choose a unique name starting with @",
            "line6" => "Once you have set up your username, click the button below",
            "done" => "Done, I've done it!",
        ],
    ],
    "errors" => [
        "header" => "Error",
        "unrecognizedcommand" => [
            "text" => "I don't know how to respond to “:text”",
            "hint" => "You can interact with this bot using /menu or check /help for assistance",
        ],
    ],
    "scanner" => [
        "prompt" => "Scan the tag",
        "localmode" => "OFFLINE - LOCAL MODE",
        "opencamera" => "Open Camera",
        "online" => "Online",
        "offline" => "Offline",
        "synchronizing" => "Synchronizing",
        "procesing" => "Processing",
        "fetch" => [
            "title" => "Success!",
            "desc" => "codes processed",
        ],
        "localstoragedcodes" => "codes saved locally",
        "localstorageaction" => "Codes will be saved on the phone",
        "loadinggps" => "Getting GPS location",
        "gpsdeniedtitle" => "You must activate and grant permissions for your GPS location",
        "retrygps" => "Grant GPS permissions",
    ],
];