@extends('zentrotraderbot::themes.html')

@section('head')
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    <meta content="" name="description">
    <meta content="" name="keywords">

    <!-- Favicons -->
    <link href="assets/img/favicon.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

    <!-- Google Fonts -->
    <link
        href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i"
        rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="assets/vendor/aos/aos.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
    <link href="assets/vendor/remixicon/remixicon.css" rel="stylesheet">
    <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">

    <!-- Template Main CSS File -->
    <link href="assets/css/style.css" rel="stylesheet">
@endsection

@section('body')
    <!-- ======= Header ======= -->
    <header id="header" class="header fixed-top">
        <div class="container-fluid container-xl d-flex align-items-center justify-content-between">

            <a href="/" class="logo d-flex align-items-center">
                <img src="assets/img/logo.png" alt="Kashio Logo">
                <span>ashio</span>
            </a>

            <nav id="navbar" class="navbar">
                <ul>
                   @include('laravel::partials.language-selector', ['module' => 'ZentroTraderBot'])

                    <li><a class="nav-link scrollto active" href="#hero">
                            {{ __('zentrotraderbot::landing.menu.home') }}
                        </a></li>

                    <li><a class="nav-link scrollto" href="#about">
                            {{ __('zentrotraderbot::landing.menu.about') }}
                        </a></li>

                    <li><a class="nav-link scrollto" href="#features">
                            {{ __('zentrotraderbot::landing.menu.features') }}
                        </a></li>

                    <li><a class="nav-link scrollto" href="#services">
                            {{ __('zentrotraderbot::landing.menu.steps') }}
                        </a></li>

                    <li><a class="nav-link scrollto" href="#pricing">
                            {{ __('zentrotraderbot::landing.menu.wallet') }}
                        </a></li>

                    <li><a class="nav-link scrollto" href="#contact">
                            {{ __('zentrotraderbot::landing.footer.contact') }}
                        </a></li>                         

                        @if(session('telegram_user'))
                                            @php $user = session('telegram_user'); @endphp
                                            <li class="dropdown">
                                                <a href="javascript:void(0);" class="nav-link">
                                                    <img src="{{ $user['photo_url'] }}" class="rounded-circle me-2" referrerpolicy="no-referrer"
                            style="width: 25px; height: 25px; object-fit: cover; border: 2px solid #0088cc;"> {{ $user['username'] }}&nbsp;&nbsp;
                                                    <i class="bi bi-chevron-down"></i>
                                                </a>
                                                <ul>
                                                    <li>
                                                        <a href="{{ route('zentrotraderbot.dashboard') }}">
                                                            <span>
                                                                <i class="ri-shield-user-line"></i>
                                                                {{ __('zentrotraderbot::landing.menu.user.myaccount') }}
                                                            </span>
                                                        </a>
                                                    </li>

                                                </ul>
                                            </li>
                        @endif

                </ul>
                <i class="bi bi-list mobile-nav-toggle"></i>
            </nav>
        </div>
    </header>
    <!-- End Header -->

    <!-- ======= Hero Section ======= -->
    <section id="hero" class="hero d-flex align-items-center">

        <div class="container">
            <div class="row">
                <div class="col-lg-6 d-flex flex-column justify-content-center">
                    <h1 data-aos="fade-up">
                        {{ __('zentrotraderbot::landing.hero.title') }}
                    </h1>
                    <h2 data-aos="fade-up" data-aos-delay="400">
                        {{ __('zentrotraderbot::landing.hero.subtitle') }}
                    </h2>
                    <br>
                    <div data-aos="fade-up" data-aos-delay="600">
                        <div class="text-center text-lg-start">
                            @include('telegrambot::partials.telegram-login', [
                                'bot' => $bot, // Pasamos el objeto que vino del controlador
                                'size' => 'large'
                            ])
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 hero-img" data-aos="zoom-out" data-aos-delay="200">
                    <img src="assets/img/hero-img.png" class="img-fluid" alt="Kashio USD">
                </div>
            </div>
        </div>

    </section>
    <!-- End Hero -->

    <main id="main">
        <!-- ======= About Section ======= -->
        <section id="about" class="about">

            <div class="container" data-aos="fade-up">
                <div class="row gx-0">

                    <div class="col-lg-6 d-flex flex-column justify-content-center" data-aos="fade-up" data-aos-delay="200">
                        <div class="content">
                            <h3>{{ __('zentrotraderbot::landing.about.title', ['name' => __('zentrotraderbot::landing.title')]) }}</h3>
                            <h2>{{ __('zentrotraderbot::landing.about.description', ['name' => __('zentrotraderbot::landing.title')]) }}</h2>
                            <p>
                                {{ __('zentrotraderbot::landing.about.card_1.text2') }}
                            </p>
                            </div>
                        </div>

                        <div class="col-lg-6 d-flex align-items-center" data-aos="zoom-out" data-aos-delay="200">
                            <img src="assets/img/about.jpg" class="img-fluid" alt="Kashio Wallet">
                        </div>

                    </div>
                </div>

            </section>

            <!-- ======= Values Section ======= -->
            <section id="values" class="values">

                <div class="container" data-aos="fade-up">

                    <header class="section-header">
                        <p>{{ __('zentrotraderbot::landing.features.title') }}</p>
                    </header>

                    <div class="row">

                        <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
                            <div class="box">
                                <img src="assets/img/values-1.png" class="img-fluid" alt="">
                                <h3>{{ __('zentrotraderbot::landing.about.card_1.title') }}</h3>
                                <p>{{ __('zentrotraderbot::landing.about.card_1.text1') }}</p>
                            </div>
                        </div>

                        <div class="col-lg-4 mt-4 mt-lg-0" data-aos="fade-up" data-aos-delay="400">
                            <div class="box">
                                <img src="assets/img/values-2.png" class="img-fluid" alt="">
                                <h3>{{ __('zentrotraderbot::landing.about.card_2.title') }}</h3>
                                <p>{{ __('zentrotraderbot::landing.about.card_2.text') }}</p>
                            </div>
                        </div>

                        <div class="col-lg-4 mt-4 mt-lg-0" data-aos="fade-up" data-aos-delay="600">
                            <div class="box">
                                <img src="assets/img/values-3.png" class="img-fluid" alt="">
                                <h3>{{ __('zentrotraderbot::landing.features.list.feature_4') }}</h3>
                                <p>{{ __('zentrotraderbot::landing.features.list.feature_3') }}</p>
                            </div>
                        </div>

                    </div>

                </div>

            </section>
            <!-- End Values Section -->

            <!-- ======= Counts Section ======= -->
            <section id="counts" class="counts">
                <div class="container" data-aos="fade-up">

                    <div class="row gy-4">

                        <div class="col-lg-3 col-md-6">
                            <div class="count-box">
                                <i class="bi bi-emoji-smile"></i>
                                <div>
                                    <span data-purecounter-start="0" data-purecounter-end="232" data-purecounter-duration="1"
                                        class="purecounter"></span>
                                    <p>{{ __('zentrotraderbot::landing.counts.users') }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="count-box">
                                <i class="bi bi-journal-richtext" style="color: #ee6c20;"></i>
                                <div>
                                    <span data-purecounter-start="0" data-purecounter-end="521" data-purecounter-duration="1"
                                        class="purecounter"></span>
                                    <p>{{ __('zentrotraderbot::landing.counts.trans') }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="count-box">
                                <i class="bi bi-headset" style="color: #15be56;"></i>
                                <div>
                                    <span data-purecounter-start="0" data-purecounter-end="1463" data-purecounter-duration="1"
                                        class="purecounter"></span>
                                    <p>{{ __('zentrotraderbot::landing.footer.contact') }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="count-box">
                                <i class="bi bi-shield-check" style="color: #bb0852;"></i>
                                <div>
                                    <span data-purecounter-start="0" data-purecounter-end="100" data-purecounter-duration="1"
                                        class="purecounter"></span>

                                    <p>{{ __('zentrotraderbot::landing.counts.security') }}</p>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>
            </section>

            <!-- ======= Features Section ======= -->
            <section id="features" class="features">

                <div class="container" data-aos="fade-up">

                    <header class="section-header">
                        <h2>{{ __('zentrotraderbot::landing.menu.features') }}</h2>
                        <p>{{ __('zentrotraderbot::landing.features.title') }}</p>
                    </header>

                    <div class="row">

                        <div class="col-lg-6">
                            <img src="assets/img/features.png" class="img-fluid" alt="Kashio Features">
                        </div>

                        <div class="col-lg-6 mt-5 mt-lg-0 d-flex">
                            <div class="row align-self-center gy-4">
                                @foreach(__('zentrotraderbot::landing.features.list') as $feature)
                                    <div class="col-md-6" data-aos="zoom-out">
                                        <div class="feature-box d-flex align-items-center">
                                            <i class="bi bi-check"></i>
                                            <h3>{{ $feature }}</h3>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                    </div>
                    <div class="row feture-tabs" data-aos="fade-up">
                        <div class="col-lg-6">
                            <h3>{{ __('zentrotraderbot::landing.payments.subtitle') }}</h3>

                            <ul class="nav nav-pills mb-3">
                                <li><a class="nav-link active" data-bs-toggle="pill" href="#tab1">{{ __('zentrotraderbot::landing.payments.tab1') }}</a></li>
                                <li><a class="nav-link" data-bs-toggle="pill" href="#tab2">{{ __('zentrotraderbot::landing.payments.tab2') }}</a></li>
                                <li><a class="nav-link" data-bs-toggle="pill" href="#tab3">{{ __('zentrotraderbot::landing.payments.tab3') }}</a></li>
                            </ul>

                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="tab1">
                                    <p>{{ __('zentrotraderbot::landing.payments.transak_notice', ['name' => __('zentrotraderbot::landing.title')]) }}</p>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="bi bi-check2"></i>
                                        <h4>{{ __('zentrotraderbot::landing.payments.step_1') }}</h4>
                                    </div>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="bi bi-check2"></i>
                                        <h4>{{ __('zentrotraderbot::landing.payments.step_2') }}</h4>
                                    </div>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="bi bi-check2"></i>
                                        <h4>{{ __('zentrotraderbot::landing.payments.step_3', ['name' => __('zentrotraderbot::landing.title')]) }}</h4>
                                    </div>
                                </div>
                                <div class="tab-pane fade show" id="tab2">
                                    <p>{{ __('zentrotraderbot::landing.about.card_2.text') }}</p>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="bi bi-check2"></i>
                                        <h4>{{ __('zentrotraderbot::landing.features.list.feature_4') }}</h4>
                                    </div>
                                </div>

                                <div class="tab-pane fade show" id="tab3">
                                    <p>{{ __('zentrotraderbot::landing.about.description', ['name' => __('zentrotraderbot::landing.title')]) }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <img src="assets/img/features-2.png" class="img-fluid" alt="">
                        </div>

                    </div>
                    <div class="row feature-icons" data-aos="fade-up">
                        <h3>{{ __('zentrotraderbot::landing.features.title') }}</h3>

                        <div class="row">
                            <div class="col-xl-4 text-center" data-aos="fade-right" data-aos-delay="100">
                                <img src="assets/img/features-3.png" class="img-fluid p-4" alt="">
                            </div>

                            <div class="col-xl-8 d-flex content">
                                <div class="row align-self-center gy-4">
                                    <div class="col-md-6 icon-box" data-aos="fade-up">
                                        <i class="ri-bank-card-line"></i>
                                        <div>
                                            <h4>{{ __('zentrotraderbot::landing.payments.transak_h4') }}</h4>
                                            <p>{{ __('zentrotraderbot::landing.payments.transak_notice', ['name' => __('zentrotraderbot::landing.title')]) }}</p>
                                        </div>
                                    </div>

                                    <div class="col-md-6 icon-box" data-aos="fade-up" data-aos-delay="100">
                                        <i class="ri-stack-line"></i>
                                        <div>
                                            <h4>{{ __('zentrotraderbot::landing.about.card_1.title') }}</h4>
                                            <p>{{ __('zentrotraderbot::landing.about.card_1.text1') }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </section>
            <!-- End Features Section -->

            <!-- ======= Services Section ======= -->
            <section id="services" class="services">

                <div class="container" data-aos="fade-up">

                    <header class="section-header">
                        <h2>{{ __('zentrotraderbot::landing.menu.steps') }}</h2>
                        <p>{{ __('zentrotraderbot::landing.about.description') }}</p>
                    </header>

                    <div class="row gy-4">

                        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                            <div class="service-box blue">
                                <i class="ri-bank-card-line icon"></i>
                                <h3>{{ __('zentrotraderbot::landing.payments.step_2_header') }}</h3>
                                <p>{{ __('zentrotraderbot::landing.payments.step_2') }}</p>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
                            <div class="service-box orange">
                                <i class="ri-money-dollar-circle-line icon"></i>
                                <h3>{{ __('zentrotraderbot::landing.about.card_1.title') }}</h3>
                                <p>{{ __('zentrotraderbot::landing.about.card_1.text1') }}</p>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="400">
                            <div class="service-box green">
                                <i class="ri-shield-user-line icon"></i>
                                <h3>{{ __('zentrotraderbot::landing.features.list.feature_4header') }}</h3>
                                <p>{{ __('zentrotraderbot::landing.features.list.feature_4') }}</p>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="500">
                            <div class="service-box red">
                                <i class="ri-customer-service-2-line icon"></i>
                                <h3>Soporte Técnico</h3>
                                <p>{{ __('zentrotraderbot::landing.features.list.feature_31') }}</p>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="600">
                            <div class="service-box purple">
                                <i class="ri-file-list-3-line icon"></i>
                                <h3>Historial Detallado</h3>
                                <p>{{ __('zentrotraderbot::landing.features.list.feature_32') }}</p>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="700">
                            <div class="service-box pink">
                                <i class="ri-line-chart-line icon"></i>
                                <h3>Cero Volatilidad</h3>
                                <p>{{ __('zentrotraderbot::landing.features.list.feature_1') }}</p>
                            </div>
                        </div>

                    </div>

                </div>

            </section>
            <!-- End Services Section -->

            <!-- ======= Pricing Section ======= -->
            <section id="pricing" class="pricing">

                <div class="container" data-aos="fade-up">

                    <header class="section-header">
                        <h2>{{ __('zentrotraderbot::landing.menu.wallet') }}</h2>
                        <p>{{ __('zentrotraderbot::landing.pricing.title') }}</p>
                    </header>

                    <div class="row gy-4" data-aos="fade-left">

                        @foreach(__('zentrotraderbot::landing.pricing.plans') as $plan)
                            <div class="col-lg-3 col-md-6" data-aos="zoom-in" data-aos-delay="100">
                                <div class="box">
                                    @if($plan['featured'])
                                        <span class="featured">{{ __('zentrotraderbot::landing.pricing.recommended') }}</span>
                                    @endif

                                    <h3 style="color: {{ $plan['color'] }};">{{ $plan['name'] }}</h3>
                                    <div class="price">
                                        <sup>{{ __('zentrotraderbot::landing.pricing.currency') }}</sup>{{ $plan['price'] }}<span> /
                                            mes</span>
                                    </div>

                                    <img src="{{ $plan['img'] }}" class="img-fluid" alt="{{ $plan['name'] }}">

                                    <ul>
                                        {{-- Beneficios Activos --}}
                                        @foreach($plan['features'] as $feature)
                                            <li>{{ $feature }}</li>
                                        @endforeach

                                        {{-- Beneficios No Incluidos --}}
                                        @foreach($plan['na'] as $feature_na)
                                            <li class="na">{{ $feature_na }}</li>
                                        @endforeach
                                    </ul>

                                    <a href="#" class="btn-buy">{{ $plan['button'] }}</a>
                                </div>
                            </div>
                        @endforeach

                    </div>

                </div>

            </section>
            <!-- End Pricing Section -->

            <!-- ======= F.A.Q Section ======= -->
            <section id="faq" class="faq">

        <div class="container" data-aos="fade-up">

            <header class="section-header">
                <h2>{{ __('zentrotraderbot::landing.menu.steps') }}</h2>
                <p>{{ __('zentrotraderbot::landing.faq.title') }}</p>
            </header>

            <div class="row">
                @php 
                                                                                                                                                                                // Dividimos las preguntas en dos grupos para las dos columnas
                    $faqGroups = array_chunk(__('zentrotraderbot::landing.faq.questions'), 3); 
                @endphp

                @foreach($faqGroups as $index => $group)
                    <div class="col-lg-6">
                        <div class="accordion accordion-flush" id="faqlist{{ $index + 1 }}">

                            @foreach($group as $item)
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                            data-bs-target="#faq-content-{{ $item['id'] }}">
                                            {{ $item['question'] }}
                                        </button>
                                    </h2>
                                    <div id="faq-content-{{ $item['id'] }}" class="accordion-collapse collapse" data-bs-parent="#faqlist{{ $index + 1 }}">
                                        <div class="accordion-body">
                                            {{ $item['answer'] }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                        </div>
                    </div>
                @endforeach

            </div>

        </div>

    </section>
            <!-- End F.A.Q Section -->

            <!-- ======= Portfolio Section ======= -->
            <section id="portfolio" class="portfolio">

        <div class="container" data-aos="fade-up">

            <header class="section-header">
                <h2>{{ __('zentrotraderbot::landing.menu.features') }}</h2>
                <p>
                {{ __('zentrotraderbot::landing.portfolio.title', ['name' => __('zentrotraderbot::landing.title')]) }}
                </p>
            </header>

            <div class="row" data-aos="fade-up" data-aos-delay="100">
                <div class="col-lg-12 d-flex justify-content-center">
                    <ul id="portfolio-flters">
                        <li data-filter="*" class="filter-active">{{ __('zentrotraderbot::landing.portfolio.filters.all') }}</li>
                        <li data-filter=".filter-app">{{ __('zentrotraderbot::landing.portfolio.filters.app') }}</li>
                        <li data-filter=".filter-card">{{ __('zentrotraderbot::landing.portfolio.filters.card') }}</li>
                        <li data-filter=".filter-web">{{ __('zentrotraderbot::landing.portfolio.filters.web') }}</li>
                    </ul>
                </div>
            </div>

            <div class="row gy-4 portfolio-container" data-aos="fade-up" data-aos-delay="200">

                @foreach(__('zentrotraderbot::landing.portfolio.items') as $item)
                    <div class="col-lg-4 col-md-6 portfolio-item {{ $item['category'] }}">
                        <div class="portfolio-wrap">
                            <img src="{{ $item['img'] }}" class="img-fluid" alt="{{ $item['title'] }}">
                            <div class="portfolio-info">
                                <h4>{{ $item['title'] }}</h4>
                                <p>{{ $item['category_label'] }}</p>
                                <div class="portfolio-links">
                                    <a href="{{ $item['img'] }}" data-gallery="portfolioGallery"
                                        class="portfokio-lightbox" title="{{ $item['desc'] }}"><i class="bi bi-plus"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

            </div>

        </div>

    </section>
            <!-- End Portfolio Section -->

            <!-- ======= Testimonials Section ======= -->
            <section id="testimonials" class="testimonials">

        <div class="container" data-aos="fade-up">

            <header class="section-header">
                <h2>{{ __('zentrotraderbot::landing.menu.about') }}</h2>
                <p>{{ __('zentrotraderbot::landing.testimonials.title') }}</p>
            </header>

            <div class="testimonials-slider swiper" data-aos="fade-up" data-aos-delay="200">
                <div class="swiper-wrapper">

                    @foreach(__('zentrotraderbot::landing.testimonials.items') as $item)
                        <div class="swiper-slide">
                            <div class="testimonial-item">
                                <div class="stars">
                                    @for($i = 0; $i < $item['stars']; $i++)
                                        <i class="bi bi-star-fill"></i>
                                    @endfor
                                </div>
                                <p>
                                    "{{ $item['quote'] }}"
                                </p>
                                <div class="profile mt-auto">
                                    <img src="{{ $item['img'] }}" class="testimonial-img" alt="{{ $item['name'] }}">
                                    <h3>{{ $item['name'] }}</h3>
                                    <h4>{{ $item['role'] }}</h4>
                                </div>
                            </div>
                    </div>@endforeach

                </div>
                <div class="swiper-pagination"></div>
            </div>

        </div>

    </section>
            <!-- End Testimonials Section -->

            <!-- ======= Team Section ======= -->
            <section id="team" class="team">

        <div class="container" data-aos="fade-up">

            <header class="section-header">
                <h2>{{ __('zentrotraderbot::landing.footer.contact') }}</h2>
                <p>
                 {{ __('zentrotraderbot::landing.team.title', ['name' => __('zentrotraderbot::landing.title')]) }}
                </p>

            </header>

            <div class="row gy-4 justify-content-center">

                @foreach(__('zentrotraderbot::landing.team.members') as $member)
                    <div class="col-lg-4 col-md-6 d-flex align-items-stretch" data-aos="fade-up" data-aos-delay="100">
                        <div class="member">
                            <div class="member-img">
                                <img src="{{ $member['img'] }}" class="img-fluid" alt="{{ $member['name'] }}">
                                <div class="social">
                                    @if($member['twitter'] != '#') <a href="{{ $member['twitter'] }}"><i class="bi bi-twitter"></i></a> @endif
                                    @if($member['facebook'] != '#') <a href="{{ $member['facebook'] }}"><i class="bi bi-facebook"></i></a> @endif
                                    @if($member['instagram'] != '#') <a href="{{ $member['instagram'] }}"><i class="bi bi-instagram"></i></a> @endif
                                    @if($member['linkedin'] != '#') <a href="{{ $member['linkedin'] }}"><i class="bi bi-linkedin"></i></a> @endif
                                </div>
                            </div>
                            <div class="member-info">
                                <h4>{{ $member['name'] }}</h4>
                                <span>{{ $member['role'] }}</span>
                                <p>{{ $member['desc'] }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach

            </div>

        </div>

    </section>
            <!-- End Team Section -->

            <!-- ======= Clients Section ======= -->
            <section id="clients" class="clients">

                <div class="container" data-aos="fade-up">

                    <header class="section-header">
                        <h2>{{ __('zentrotraderbot::landing.about.title') }}</h2>
                        <p>{{ __('zentrotraderbot::landing.about.subtitle') }}</p>
                    </header>

                    <div class="clients-slider swiper">
                        <div class="swiper-wrapper align-items-center">
                            <div class="swiper-slide"><img src="assets/img/clients/client-1.png" class="img-fluid"
                                    alt="Transak Payments">
                            </div>
                            <div class="swiper-slide"><img src="assets/img/clients/client-2.png" class="img-fluid"
                                    alt="Stablecoins">
                            </div>
                            <div class="swiper-slide"><img src="assets/img/clients/client-3.png" class="img-fluid"
                                    alt="ZentroTrader">
                            </div>
                            <div class="swiper-slide"><img src="assets/img/clients/client-4.png" class="img-fluid"
                                    alt="Blockchain Network">
                            </div>
                            <div class="swiper-slide"><img src="assets/img/clients/client-5.png" class="img-fluid"
                                    alt="Secure Assets">
                            </div>
                            <div class="swiper-slide"><img src="assets/img/clients/client-6.png" class="img-fluid"
                                    alt="Kashio Partners">
                            </div>
                        </div>
                        <div class="swiper-pagination"></div>
                    </div>
                </div>

            </section>
            <!-- End Clients Section -->

            <!-- ======= Recent Blog Posts Section ======= -->
            <section id="recent-blog-posts" class="recent-blog-posts">

        <div class="container" data-aos="fade-up">

            <header class="section-header">
                <h2>{{ __('zentrotraderbot::landing.menu.about') }}</h2>
                <p>{{ __('zentrotraderbot::landing.blog.title') }}</p>
            </header>

            <div class="row">

                @foreach(__('zentrotraderbot::landing.blog.posts') as $post)
                    <div class="col-lg-4">
                        <div class="post-box">
                            <div class="post-img">
                                <img src="{{ $post['img'] }}" class="img-fluid" alt="{{ $post['title'] }}">
                            </div>
                            <span class="post-date">{{ $post['date'] }}</span>
                            <h3 class="post-title">{{ $post['title'] }}</h3>
                            <a href="{{ $post['link'] }}" class="readmore stretched-link mt-auto">
                                <span>{{ __('zentrotraderbot::landing.blog.read_more') }}</span>
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                @endforeach

            </div>

        </div>

    </section>
            <!-- End Recent Blog Posts Section -->

            <!-- ======= Contact Section ======= -->
            <section id="contact" class="contact">

        <div class="container" data-aos="fade-up">

            <header class="section-header">
                <h2>{{ __('zentrotraderbot::landing.footer.contact') }}</h2>
                <p>{{ __('zentrotraderbot::landing.contact.title') }}</p>
            </header>

            <div class="row gy-4">

                <div class="col-lg-6">

                    <div class="row gy-4">
                        <div class="col-md-6">
                            <div class="info-box">
                                <i class="bi bi-geo-alt"></i>
                                <h3>{{ __('zentrotraderbot::landing.contact.info.address_title') }}</h3>
                                <p>{{ __('zentrotraderbot::landing.contact.info.address_text') }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-box">
                                <i class="bi bi-telephone"></i>
                                <h3>{{ __('zentrotraderbot::landing.contact.info.phone_title') }}</h3>
                                <p>{{ __('zentrotraderbot::landing.contact.info.phone_text') }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-box">
                                <i class="bi bi-envelope"></i>
                                <h3>{{ __('zentrotraderbot::landing.contact.info.email_title') }}</h3>
                                <p>{{ __('zentrotraderbot::landing.contact.info.email_text') }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-box">
                                <i class="bi bi-clock"></i>
                                <h3>{{ __('zentrotraderbot::landing.contact.info.hours_title') }}</h3>
                                <p>{{ __('zentrotraderbot::landing.contact.info.hours_text') }}</p>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="col-lg-6">
                    <form action="#" method="post" class="php-email-form">
                        @csrf
                        <div class="row gy-4">

                            <div class="col-md-6">
                                <input type="text" name="name" class="form-control" 
                                    placeholder="{{ __('zentrotraderbot::landing.contact.form.name') }}" required>
                            </div>

                            <div class="col-md-6 ">
                                <input type="email" class="form-control" name="email" 
                                    placeholder="{{ __('zentrotraderbot::landing.contact.form.email') }}" required>
                            </div>

                            <div class="col-md-12">
                                <input type="text" class="form-control" name="subject" 
                                    placeholder="{{ __('zentrotraderbot::landing.contact.form.subject') }}" required>
                            </div>

                            <div class="col-md-12">
                                <textarea class="form-control" name="message" rows="6" 
                                    placeholder="{{ __('zentrotraderbot::landing.contact.form.message') }}" required></textarea>
                            </div>

                            <div class="col-md-12 text-center">
                                <div class="loading">{{ __('zentrotraderbot::landing.contact.form.loading') }}</div>
                                <div class="error-message"></div>
                                <div class="sent-message">{{ __('zentrotraderbot::landing.contact.form.sent') }}</div>

                                <button type="submit">{{ __('zentrotraderbot::landing.contact.form.button') }}</button>
                            </div>

                        </div>
                    </form>

                </div>

            </div>

        </div>

    </section>
            <!-- End Contact Section -->

        </main><!-- End #main -->

        <!-- ======= Footer ======= -->
        <footer id="footer" class="footer">

            <div class="footer-newsletter">
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-lg-12 text-center">
                            <h4>{{ __('zentrotraderbot::landing.footer.title') }}</h4>
                            <p>{{ __('zentrotraderbot::landing.footer.subtitle', ['name' => __('zentrotraderbot::landing.title')]) }}</p>

                        </div>
                        <div class="col-lg-6">
                            <form action="" method="post">
                                @csrf
                                <input type="email" name="email" placeholder="{{ __('zentrotraderbot::landing.footer.email') }}"><input type="submit"
                                    value="{{ __('zentrotraderbot::landing.footer.suscribeme') }}">
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="footer-top">
                <div class="container">
                    <div class="row gy-4">
                        <div class="col-lg-5 col-md-12 footer-info">
                            <a href="/" class="logo d-flex align-items-center">
                                <img src="assets/img/logo.png" alt="Kashio Logo">
                                <span>ashio</span>
                            </a>
                            <p>{{ __('zentrotraderbot::landing.hero.subtitle') }}</p>
                            <div class="social-links mt-3">
                                <a href="#" class="twitter"><i class="bi bi-twitter"></i></a>
                                <a href="#" class="facebook"><i class="bi bi-facebook"></i></a>
                                <a href="#" class="instagram"><i class="bi bi-instagram"></i></a>
                                <a href="#" class="linkedin"><i class="bi bi-linkedin"></i></a>
                            </div>
                        </div>

                        <div class="col-lg-2 col-12 footer-links">
                            <h4>{{ __('zentrotraderbot::landing.menu.about') }}</h4>
                            <ul>
                                <li><i class="bi bi-chevron-right"></i> <a
                                        href="#hero">{{ __('zentrotraderbot::landing.menu.home') }}</a></li>
                                <li><i class="bi bi-chevron-right"></i> <a
                                        href="#about">{{ __('zentrotraderbot::landing.menu.about') }}</a></li>
                                <li><i class="bi bi-chevron-right"></i> <a
                                        href="#services">{{ __('zentrotraderbot::landing.menu.steps') }}</a></li>
                                <li><i class="bi bi-chevron-right"></i> <a href="#">{{ __('zentrotraderbot::landing.footer.terms') }}</a></li>
                                <li><i class="bi bi-chevron-right"></i> <a href="#">{{ __('zentrotraderbot::landing.footer.policy') }}</a></li>
                                <li><i class="bi bi-chevron-right"></i> <a href="#faq">{{ __('zentrotraderbot::landing.footer.faq') }}</a></li>
                            </ul>
                        </div>

                        <div class="col-lg-3 col-md-12 footer-contact text-center text-md-start">
                            <h4>{{ __('zentrotraderbot::landing.footer.contact', ['name' => __('zentrotraderbot::landing.title')]) }}</h4>
                            <p>{{ __('zentrotraderbot::landing.footer.support.title', ['name' => __('zentrotraderbot::landing.title')]) }} <br>
                                {{ __('zentrotraderbot::landing.footer.support.subtitle') }}<br><br>
                                <strong>{{ __('zentrotraderbot::landing.footer.support.email') }}</strong> {{ __('zentrotraderbot::landing.footer.support.contact') }}<br>
                            </p>

                        </div>

                    </div>
                </div>
            </div>

            <div class="container">
                <div class="copyright">
                    &copy; <?php echo date("Y") ?><strong><span> {{ __('zentrotraderbot::landing.title') }}</span></strong>. {{ __('zentrotraderbot::landing.footer.rights') }}
                </div>
            </div>
        </footer>
        <!-- End Footer -->

        <a href="javascript:void(0);" onclick="window.scrollTo({top: 0, behavior: 'smooth'});" class="back-to-top d-flex align-items-center justify-content-center"><i
                class="bi bi-arrow-up-short"></i></a>

        <!-- Vendor JS Files -->
        <script src="assets/vendor/purecounter/purecounter_vanilla.js"></script>
        <script src="assets/vendor/aos/aos.js"></script>
        <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
        <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
        <script src="assets/vendor/isotope-layout/isotope.pkgd.min.js"></script>
        <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
        <script src="assets/vendor/php-email-form/validate.js"></script>

        <!-- Template Main JS File -->
        <script src="assets/js/main.js"></script>
@endsection