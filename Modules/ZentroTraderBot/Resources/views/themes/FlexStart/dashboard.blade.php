@extends('zentrotraderbot::themes.html')

@section('head')
    {{-- Mantén tus metas y links de fuentes/vendor aquí como ya los tienes --}}
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">


    {{--  Favicons --}}
    <link href="assets/img/favicon.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

    <link href="assets/vendor/aos/aos.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/remixicon/remixicon.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">

    <!-- Custom Toast Styles -->
    <style>
        .custom-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #28a745;
            color: white;
            padding: 16px 24px;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            font-size: 14px;
            animation: slideIn 0.3s ease-in-out;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }

            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

        .custom-toast.hide {
            animation: slideOut 0.3s ease-in-out forwards;
        }

        /* Share Menu Styles */
        .share-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }

        .share-overlay.active {
            display: flex;
        }

        .share-modal {
            background: white;
            border-radius: 12px;
            padding: 24px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            animation: slideUp 0.3s ease-in-out;
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .share-modal h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #333;
        }

        .share-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 16px;
        }

        .share-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 16px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background: #f9f9f9;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .share-btn:hover {
            background: #f0f0f0;
            border-color: #0088cc;
            color: #0088cc;
        }

        .share-btn i {
            font-size: 18px;
        }

        .share-close {
            display: flex;
            justify-content: flex-end;
        }

        .share-close button {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .share-close button:hover {
            color: #333;
        }
    </style>

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        /* Estilo general para escritorio */
        .full-height-hero {
            min-height: 100vh;
            padding: 10px 0 60px 0;
            /* Margen amplio para desktop */
            display: flex;
            align-items: center;
        }

        /* Ajuste específico para dispositivos móviles (pantallas menores a 768px) */
        @media (max-width: 768px) {
            .full-height-hero {
                padding: 0 0 20px 0;
                /* Reducimos drásticamente el margen superior */
                align-items: flex-start;
                /* Alineamos la card hacia arriba, no al centro */
            }

            .balance-card {
                margin-top: 10px;
                /* Un pequeño toque de separación del logo */
                padding: 1.5rem !important;
                /* Tarjeta un poco más compacta en móvil */
            }

            .display-4 {
                font-size: 2.5rem;
                /* Ajustamos el tamaño del número del balance */
            }
        }

        .balance-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 20px;
            box-shadow: 0px 0px 30px rgba(1, 41, 112, 0.08) !important;
        }

        .icon-box {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }
    </style>
@endsection

@section('body')
    <section id="hero" class="hero d-flex align-items-center full-height-hero">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-6 text-center">



                    <div x-data="{ view: 'balance' }">
                        <div x-show="view === 'balance'" x-transition>
                            <div class="card balance-card shadow-sm p-4">

                                {{-- 
                                <a href="{{ url('/') }}" class="logo d-flex align-items-center">
                                    <img src="assets/img/logo.png" alt="Kashio Logo">
                                </a>
                                <h4 class="text-muted">
                                    👋
                                    {{ __('zentrotraderbot::landing.menu.user.greeting', ['name' => session('telegram_user')['name']]) }}
                                </h4>
                                <hr>
                                --}}
                                <p class="text-secondary mb-1 text-end">
                                    <a href="{{ route('telegram.logout') }}" class="ms-3 text-danger"
                                        title="{{ __('zentrotraderbot::landing.menu.user.logout') }}">
                                        <i class="bi bi-x-circle"></i>
                                    </a>
                                </p>
                                <p class="text-secondary mb-1">
                                    {{ __('zentrotraderbot::landing.menu.user.balance') }}

                                </p>
                                <h4 class="display-4 fw-bold text-primary">
                                    {{ number_format($balance, 2) }} <small class="fs-4">USD</small>
                                </h4>
                                <br>

                                <h6 class="text-start fw-bold mb-3">
                                    @if (count($transactions) > 0)
                                        <a href="javascript:void(0);location.reload();" class="ms-3"
                                            title="{{ __('zentrotraderbot::landing.menu.user.refresh') }}">
                                            <i class="ri-restart-line"></i>
                                        </a>
                                        {{ trans_choice('zentrotraderbot::landing.menu.user.lastoperations', count($transactions), ['count' => count($transactions)]) }}
                                    @endif
                                </h6>

                                <div class="list-group list-group-flush text-start">
                                    {{-- Aquí mapearás tus transacciones de la blockchain --}}
                                    @forelse($transactions ?? [] as $tx)
                                        <div class="list-group-item d-flex align-items-center transaction-item">
                                            <div class="icon-box {{ $tx['human']['type'] == 'in' ? 'bg-light-success text-success' : 'bg-light-danger text-danger' }}"
                                                style="background-color: {{ $tx['human']['type'] == 'in' ? '#e1f7ec' : '#fce8e8' }};">
                                                <i
                                                    class="{{ $tx['human']['type'] == 'in' ? 'ri-arrow-left-down-line' : 'ri-arrow-right-up-line' }} fs-5"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0 fw-bold">{{ $tx['human']['date'] }}</h6>
                                                <small
                                                    class="text-muted">{{ $tx['human']['type'] == 'in' ? $tx['from'] : $tx['to'] }}</small>
                                            </div>
                                            <div class="text-end">
                                                <span
                                                    class="fw-bold {{ $tx['human']['type'] == 'in' ? 'text-success' : 'text-danger' }}">
                                                    {{ $tx['human']['type'] == 'in' ? '+' : '-' }}
                                                    ${{ number_format($tx['human']['value'], 2) }}
                                                </span>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-center py-3 text-muted">
                                            <small>
                                                {{ __('zentrotraderbot::landing.menu.user.nooperations') }}
                                            </small>
                                            <br><br><br><br><br><br><br><br><br><br><br><br>
                                        </div>
                                    @endforelse
                                </div>

                                <div class="d-grid gap-2 mt-4">
                                    <div class="row g-2 mb-2">
                                        <div class="col-6">
                                            {{-- Cambiamos el link por un botón de Alpine --}}
                                            <button @click="view = 'receive'"
                                                class="btn btn-light btn-lg w-100 shadow-sm border fw-bold">
                                                <i class="ri-qr-scan-2-line me-2"></i>
                                                {{ __('zentrotraderbot::landing.menu.user.wallet.receive.button') }}
                                            </button>
                                        </div>
                                        <div class="col-6">
                                            {{-- Botón para ir al Bot --}}
                                            <a href="https://t.me/{{ $bot->code }}?start=send"
                                                class="btn btn-primary btn-lg w-100 shadow-sm" style="font-weight: 600;">
                                                <i class="bi bi-send-fill me-2"></i>
                                                {{ __('zentrotraderbot::landing.menu.user.openbot', ['name' => $bot->code]) }}
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div x-show="view === 'receive'" x-transition style="display: none;">
                            <div class="card balance-card p-4 text-center">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <button @click="view = 'balance'" class="btn btn-sm btn-light rounded-circle">
                                        <i class="ri-arrow-left-line"></i>
                                    </button>
                                    <h4 class="mb-0 fw-bold">
                                        {{ __('zentrotraderbot::landing.menu.user.wallet.receive.header', ['name' => __('zentrotraderbot::landing.title')]) }}
                                    </h4>
                                    <div style="width: 32px;"></div> {{-- Espaciador para centrar --}}
                                </div>

                                <p class="text-secondary small">
                                    {{ __('zentrotraderbot::landing.menu.user.wallet.receive.scaninfo') }}
                                </p>

                                @php
                                    $user = session('telegram_user');
                                    $wallet_address = url('/pay') . '/' . $user['username'];
                                @endphp
                                <div class="bg-white d-inline-block">
                                    <div class="bg-white d-inline-block mb-4 position-relative shadow-sm rounded-3">
                                        <div id="qr-container">
                                            {!! $qrService->generateSvg($wallet_address, 220) !!} {{-- Si el contenido es
                                            seguro, mantener {!! !!} --}}
                                        </div>

                                        <div class="position-absolute top-50 start-50 translate-middle bg-white p-1 rounded-circle"
                                            style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; overflow: hidden;">

                                            <img src="{{ route('avatar.proxy', ['bot_token' => $bot->token, 'file_path' => session('telegram_user.photo_url')]) }}"
                                                referrerpolicy="no-referrer" class="rounded-circle"
                                                style="width: 100%; height: 100%; object-fit: cover; display: block;"
                                                alt="{{ __('zentrotraderbot::landing.menu.user.photo_alt') }}">

                                        </div>
                                    </div>


                                    <p class="text-secondary small">
                                        {{ __('zentrotraderbot::landing.menu.user.wallet.receive.shareinfo') }}
                                    </p>

                                    <div class="input-group mb-3 p-3 ">
                                        <button class="btn" onclick="copyAddress()">
                                            <i class="ri-global-line"></i>
                                        </button>
                                        <input type="text" id="walletAddr" class="form-control bg-light border-0 small"
                                            value="{{ $wallet_address }}" readonly>
                                        <button class="btn btn-primary" id="btnCopy" onclick="copyAddress()">
                                            <i id="copyIcon" class="ri-file-copy-line"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="d-grid gap-2 mt-4">
                                    <div class="row g-2 mb-2">
                                        <button onclick="openShareMenu()"
                                            class="btn btn-light btn-lg w-100 shadow-sm border fw-bold">
                                            {{ __('zentrotraderbot::landing.menu.user.wallet.receive.share') }}
                                        </button>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Share Modal -->
    <div id="shareOverlay" class="share-overlay">
        <div class="share-modal">
            <div class="share-close">
                <button onclick="closeShareMenu()">&times;</button>
            </div>
            <h3>{{ __('zentrotraderbot::landing.menu.user.wallet.receive.share_menu.title') }}</h3>
            <div class="share-options">
                <button class="share-btn" onclick="shareVia('telegram')">
                    <i class="bi bi-telegram"></i>
                    <span>{{ __('zentrotraderbot::landing.menu.user.wallet.receive.share_menu.telegram') }}</span>
                </button>
                <button class="share-btn" onclick="shareVia('whatsapp')">
                    <i class="bi bi-whatsapp"></i>
                    <span>{{ __('zentrotraderbot::landing.menu.user.wallet.receive.share_menu.whatsapp') }}</span>
                </button>
                <button class="share-btn" onclick="shareVia('facebook')">
                    <i class="bi bi-facebook"></i>
                    <span>{{ __('zentrotraderbot::landing.menu.user.wallet.receive.share_menu.facebook') }}</span>
                </button>
                <button class="share-btn" onclick="shareVia('twitter')">
                    <i class="bi bi-twitter"></i>
                    <span>{{ __('zentrotraderbot::landing.menu.user.wallet.receive.share_menu.twitter') }}</span>
                </button>
                <button class="share-btn" onclick="shareVia('email')">
                    <i class="bi bi-envelope"></i>
                    <span>{{ __('zentrotraderbot::landing.menu.user.wallet.receive.share_menu.email') }}</span>
                </button>
                <button class="share-btn" onclick="shareVia('sms')">
                    <i class="bi bi-chat-dots"></i>
                    <span>{{ __('zentrotraderbot::landing.menu.user.wallet.receive.share_menu.sms') }}</span>
                </button>
            </div>
        </div>
    </div>

    <script src="assets/vendor/aos/aos.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script>
        // Simple toast notification system (no external dependencies)
        function showToast(message, duration = 3000) {
            const toast = document.createElement('div');
            toast.className = 'custom-toast';
            toast.textContent = message;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('hide');
                setTimeout(() => {
                    document.body.removeChild(toast);
                }, 300);
            }, duration);
        }

        function openShareMenu() {
            const overlay = document.getElementById('shareOverlay');
            overlay.classList.add('active');
        }

        function closeShareMenu() {
            const overlay = document.getElementById('shareOverlay');
            overlay.classList.remove('active');
        }

        function shareVia(platform) {
            const walletAddr = document.getElementById('walletAddr').value;
            const appName = '{{ __('zentrotraderbot::landing.title') }}';
            const message =
                '{{ __('zentrotraderbot::landing.menu.user.wallet.receive.share_menu.message', ['name' => ':name']) }}'
                .replace(':name', appName);
            const fullText = `${message} ${walletAddr}`;

            let shareUrl = '';

            switch (platform) {
                case 'whatsapp':
                    shareUrl = `https://wa.me/?text=${encodeURIComponent(fullText)}`;
                    break;
                case 'facebook':
                    shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(walletAddr)}`;
                    break;
                case 'twitter':
                    shareUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent(fullText)}`;
                    break;
                case 'email':
                    shareUrl = `mailto:?subject=${encodeURIComponent(appName)}&body=${encodeURIComponent(fullText)}`;
                    break;
                case 'sms':
                    shareUrl = `sms:?body=${encodeURIComponent(fullText)}`;
                    break;
                case 'telegram':
                    shareUrl =
                        `https://t.me/share/url?url=${encodeURIComponent(walletAddr)}&text=${encodeURIComponent(message)}`;
                    break;
                case 'copy':
                    navigator.clipboard.writeText(walletAddr).then(() => {
                        showToast('{{ __('zentrotraderbot::landing.menu.user.wallet.receive.copied') }}', 2000);
                        closeShareMenu();
                    }).catch(() => {
                        showToast('Error al copiar', 2000);
                    });
                    return;
            }

            if (shareUrl) {
                window.open(shareUrl, '_blank');
                closeShareMenu();
            }
        }

        // Close modal when clicking overlay
        document.addEventListener('DOMContentLoaded', function() {
            const overlay = document.getElementById('shareOverlay');
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    closeShareMenu();
                }
            });

            window.copyAddress = function() {
                const copyText = document.getElementById("walletAddr");
                const btn = document.getElementById("btnCopy");
                const icon = document.getElementById("copyIcon");

                if (!copyText || !btn || !icon) {
                    console.error('Required elements not found');
                    return;
                }

                // Copiar al portapapeles
                navigator.clipboard.writeText(copyText.value).then(() => {
                    // 1. Guardar clases originales
                    const originalBtnClass = btn.className;
                    const originalIconClass = icon.className;

                    // 2. Aplicar estado "Éxito"
                    btn.classList.replace('btn-primary', 'btn-success');
                    icon.className = 'ri-check-line'; // El icono del "tick"

                    // 3. Mostrar notificación personalizada
                    showToast('{{ __('zentrotraderbot::landing.menu.user.wallet.receive.copied') }}',
                        3000);

                    // 4. Revertir después de 1 segundo
                    setTimeout(() => {
                        btn.className = originalBtnClass;
                        icon.className = originalIconClass;
                    }, 2000);
                }).catch(err => {
                    console.error('Clipboard copy failed:', err);
                    showToast('Error al copiar', 2000);
                });
            };
        });
    </script>
@endsection
