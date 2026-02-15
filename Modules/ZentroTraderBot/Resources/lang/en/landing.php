<?php

return [
    'title' => 'Kashio',

    'meta' => [
        'title' => 'Your personal account in USD',
        'description' => 'Manage your capital in digital dollars securely, stably, and without complications.',
    ],

    'menu' => [
        'home' => 'Home',
        'about' => 'About Us',
        'features' => 'Features',
        'steps' => 'Services',
        'wallet' => 'Plans',
        'contact' => 'Contact',
        'get_started' => 'Get Started',
    ],
    'hero' => [
        'title' => 'Your personal account in digital dollars',
        'subtitle' => 'Protect your capital from devaluation in a secure, fast, and hassle-free way.',
        'cta' => 'Create an account',
    ],
    'about' => [
        'title' => 'Who are we?',
        'description' => 'Kashio is the evolution of your personal wallet, designed to give you financial stability in a digital environment.',
        'card_1' => [
            'title' => 'USD Balance',
            'text' => 'Keep your savings in stable assets like USDT and USDC, protected from local market volatility.',
        ],
        'card_2' => [
            'title' => '24/7 Availability',
            'text' => 'Access your funds, check your balance, and manage your dollars anytime, anywhere.',
        ],
    ],
    'features' => [
        'title' => 'Everything you need to manage your capital',
        'list' => [
            'feature_1' => 'Zero volatility',
            'feature_2' => 'Intuitive interface',
            'feature_3' => 'Detailed history',
            'feature_4' => 'Bank-grade security',
            'feature_5' => 'Priority support',
            'feature_6' => 'Multi-device access',
        ],
    ],
    'payments' => [
        'tab1' => 'Deposit',
        'tab2' => 'Security',
        'tab3' => 'Transak',
        'subtitle' => 'How to fund your account?',
        'transak_notice' => 'Kashio uses the Transak gateway to ensure secure and direct deposits.',
        'step_1' => 'Select the amount to deposit',
        'step_2' => 'Pay with your local currency via Transak',
        'step_3' => 'Receive your dollars in your Kashio balance',
    ],
    'faq' => [
        'title' => 'Frequently Asked Questions',
        'questions' => [
            [
                'id' => '1',
                'question' => 'What exactly is a Kashio account?',
                'answer' => 'It is a secure space where your capital is held in digital dollars to avoid devaluation. It is the evolution of your personal wallet backed by ZentroTrader.'
            ],
            [
                'id' => '2',
                'question' => 'How do I load balance into my account?',
                'answer' => 'The process is simple: you use our Transak integration to buy USD with your local currency. The balance is automatically updated in your profile once the operation is confirmed.'
            ],
            [
                'id' => '3',
                'question' => 'Is it safe to operate with Transak?',
                'answer' => 'Yes, Transak is a leading regulated platform. Kashio never stores your bank details; we only receive the final deposit to credit it to your account.'
            ],
            [
                'id' => '4',
                'question' => 'Do I have immediate availability of my funds?',
                'answer' => 'Absolutely! Your funds are available 24/7. You can manage and check your balance at any time from your personal dashboard transparently.'
            ],
            [
                'id' => '5',
                'question' => 'Does Kashio charge maintenance fees?',
                'answer' => 'We have a free basic plan so you can protect your money without fixed costs. We only apply minimal fees on advanced capital management operations.'
            ],
            [
                'id' => '6',
                'question' => 'What backing does my money have?',
                'answer' => 'Your money is backed by blockchain technology in stable assets (Stablecoins) and features the bank-grade security infrastructure of ZentroTrader.'
            ],
        ]
    ],
    'footer' => [
        'title' => 'Subscribe to our newsletter',
        'contact' => 'Kashio Support',
        'description' => 'The leading platform for managing your digital assets backed by ZentroTrader.',
    ],
    'pricing' => [
        'title' => 'Plans and Benefits',
        'currency' => '$',
        'plans' => [
            [
                'name' => 'Basic',
                'price' => '0',
                'color' => '#07d5c0',
                'img' => 'assets/img/pricing-free.png',
                'button' => 'Start for Free',
                'featured' => false,
                'features' => ['Personal USD Account', 'Deposits via Transak', 'Transaction History'],
                'na' => ['Priority Support', 'Advanced Analytics']
            ],
            [
                'name' => 'Starter',
                'price' => '19',
                'color' => '#65c600',
                'img' => 'assets/img/pricing-starter.png',
                'button' => 'Select Plan',
                'featured' => true,
                'features' => ['Personal USD Account', 'Deposits via Transak', 'Transaction History', '24/7 Support'],
                'na' => ['Advanced Analytics']
            ],
            [
                'name' => 'Business',
                'price' => '29',
                'color' => '#ff901c',
                'img' => 'assets/img/pricing-business.png',
                'button' => 'Select Plan',
                'featured' => false,
                'features' => ['Personal USD Account', 'Deposits via Transak', 'Transaction History', 'Priority Support', 'Market Analysis'],
                'na' => []
            ],
            [
                'name' => 'Ultimate',
                'price' => '49',
                'color' => '#ff0071',
                'img' => 'assets/img/pricing-ultimate.png',
                'button' => 'Select Plan',
                'featured' => false,
                'features' => ['Everything above', 'Extended limits', 'VIP ZentroTrader Access', 'Asset Management', 'Personalized Advisory'],
                'na' => []
            ],
        ]
    ],
    'blog' => [
        'title' => 'Learn to protect your capital',
        'read_more' => 'Read more',
        'posts' => [
            [
                'date' => 'February 15, 2026',
                'title' => 'What are Digital Dollars and why are they the ideal haven?',
                'img' => 'assets/img/blog/blog-1.jpg',
                'link' => '#',
            ],
            [
                'date' => 'February 10, 2026',
                'title' => 'Step-by-step guide: How to fund your Kashio account using Transak.',
                'img' => 'assets/img/blog/blog-2.jpg',
                'link' => '#',
            ],
            [
                'date' => 'February 05, 2026',
                'title' => 'Blockchain Security: How we protect your assets at Kashio.',
                'img' => 'assets/img/blog/blog-3.jpg',
                'link' => '#',
            ],
        ]
    ],
    'team' => [
        'title' => 'The team behind Kashio',
        'members' => [
            [
                'name' => 'Donel Zambrano',
                'role' => 'Founder & Lead Developer',
                'desc' => 'Lead Architect of Kashio and ZentroTrader. Expert in democratizing access to stable accounts in digital USD.',
                'img' => 'assets/img/team/team-1.jpg',
                'twitter' => '#',
                'linkedin' => '#',
                'facebook' => '#',
                'instagram' => '#'
            ],
            [
                'name' => 'Kashio Support',
                'role' => 'Customer Care',
                'desc' => 'Dedicated team to assist you in your loading processes via Transak and real-time balance management.',
                'img' => 'assets/img/team/team-2.jpg',
                'twitter' => '#',
                'linkedin' => '#',
                'facebook' => '#',
                'instagram' => '#'
            ],
            [
                'name' => 'ZentroTrader Tech',
                'role' => 'Infrastructure & Security',
                'desc' => 'Blockchain technology specialists in charge of the custody and security of your digital assets.',
                'img' => 'assets/img/team/team-3.jpg',
                'twitter' => '#',
                'linkedin' => '#',
                'facebook' => '#',
                'instagram' => '#'
            ],
        ]
    ],
    'testimonials' => [
        'title' => 'What our users are saying',
        'items' => [
            [
                'quote' => 'Since using Kashio, I don’t worry about devaluation. Having my savings in digital USD gives me a peace of mind I didn’t have before.',
                'name' => 'Ricardo M.',
                'role' => 'Private User',
                'img' => 'assets/img/testimonials/testimonials-1.jpg',
                'stars' => 5
            ],
            [
                'quote' => 'Unbelievable how easy it is to top up with Transak. In a few minutes, I moved my local currency to dollars in my personal Kashio account.',
                'name' => 'Sara W.',
                'role' => 'Freelancer',
                'img' => 'assets/img/testimonials/testimonials-2.jpg',
                'stars' => 5
            ],
            [
                'quote' => 'The interface is very clean. I can see my USD balance instantly and I know my capital is backed by ZentroTrader technology.',
                'name' => 'Juan K.',
                'role' => 'Store Owner',
                'img' => 'assets/img/testimonials/testimonials-3.jpg',
                'stars' => 5
            ],
            [
                'quote' => 'As an entrepreneur, I needed a USD account that was fast. The Transak integration works like a charm.',
                'name' => 'Matt B.',
                'role' => 'Entrepreneur',
                'img' => 'assets/img/testimonials/testimonials-4.jpg',
                'stars' => 4
            ],
        ]
    ],
    'portfolio' => [
        'title' => 'Discover the Kashio account interface',
        'filters' => [
            'all' => 'All',
            'app' => 'Mobile App',
            'card' => 'USD Dashboard',
            'web' => 'Deposits',
        ],
        'items' => [
            [
                'title' => 'Mobile Management',
                'category' => 'filter-app',
                'category_label' => 'App',
                'img' => 'assets/img/portfolio/portfolio-1.jpg',
                'desc' => 'Your balance always with you'
            ],
            [
                'title' => 'Secure Top-ups',
                'category' => 'filter-web',
                'category_label' => 'Transak',
                'img' => 'assets/img/portfolio/portfolio-2.jpg',
                'desc' => 'Buy USD with your local currency'
            ],
            [
                'title' => 'Real-time Balance',
                'category' => 'filter-card',
                'category_label' => 'Digital USD',
                'img' => 'assets/img/portfolio/portfolio-4.jpg',
                'desc' => 'Total control of your assets'
            ],
        ]
    ],
    'contact' => [
        'title' => 'Have questions about your USD account?',
        'info' => [
            'address_title' => 'Headquarters',
            'address_text' => 'ZentroTrader Tech, Global Kashio Support',
            'phone_title' => 'Call Us',
            'phone_text' => '+1 234 567 890',
            'email_title' => 'Email Us',
            'email_text' => 'support@kashio.com',
            'hours_title' => 'Opening Hours',
            'hours_text' => 'Monday - Friday: 9:00AM - 6:00PM',
        ],
        'form' => [
            'name' => 'Your Name',
            'email' => 'Your Email',
            'subject' => 'Subject',
            'message' => 'Tell us how we can help with your account',
            'button' => 'Send Message',
            'loading' => 'Loading...',
            'sent' => 'Your message has been sent. Thank you!',
        ]
    ],
];