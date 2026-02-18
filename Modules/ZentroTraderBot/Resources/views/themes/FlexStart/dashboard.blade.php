@extends('zentrotraderbot::themes.html')

@section('head')
    {{-- Mantén tus metas y links de fuentes/vendor aquí como ya los tienes --}}
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">


    <!-- Favicons -->
    <link href="assets/img/favicon.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

    <link href="assets/vendor/aos/aos.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/remixicon/remixicon.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">

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
                                <a href="{{ url('/') }}" class="logo d-flex align-items-center">
                                    <img src="assets/img/logo.png" alt="Kashio Logo">
                                </a>
                                <h4 class="text-muted">
                                    👋
                                    {{ __('zentrotraderbot::landing.menu.user.greeting', ['name' => session('telegram_user')['name']]) }}
                                </h4>
                                <hr>
                                <p class="text-secondary mb-1">
                                    {{ __('zentrotraderbot::landing.menu.user.balance') }}
                                </p>
                                <h4 class="display-4 fw-bold text-primary">
                                    {{ number_format($balance, 2) }} <small class="fs-4">USD</small>
                                </h4>
                                <br>

                                <h6 class="text-start fw-bold mb-3">
                                    @if (count($transactions) > 0)
                                        <i class="ri-history-line me-1"></i>
                                        {{ trans_choice("zentrotraderbot::landing.menu.user.lastoperations", count($transactions), ['count' => count($transactions)]) }}
                                    @endif
                                </h6>

                                <div class="list-group list-group-flush text-start">
                                    {{-- Aquí mapearás tus transacciones de la blockchain --}}
                                    @forelse($transactions ?? [] as $tx)
                                        <div class="list-group-item d-flex align-items-center transaction-item">
                                            <div class="icon-box {{ $tx['type'] == 'in' ? 'bg-light-success text-success' : 'bg-light-danger text-danger' }}"
                                                style="background-color: {{ $tx['type'] == 'in' ? '#e1f7ec' : '#fce8e8' }};">
                                                <i
                                                    class="{{ $tx['type'] == 'in' ? 'ri-arrow-left-down-line' : 'ri-arrow-right-up-line' }} fs-5"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0 fw-bold">{{ $tx['concept'] }}</h6>
                                                <small class="text-muted">{{ $tx['date'] }}</small>
                                            </div>
                                            <div class="text-end">
                                                <span
                                                    class="fw-bold {{ $tx['type'] == 'in' ? 'text-success' : 'text-danger' }}">
                                                    {{ $tx['type'] == 'in' ? '+' : '-' }}
                                                    ${{ number_format($tx['amount'], 2) }}
                                                </span>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-center py-3 text-muted">
                                            <small>
                                                {{ __('zentrotraderbot::landing.menu.user.nooperations') }}
                                            </small>
                                        </div>
                                    @endforelse
                                </div>

                                <div class="d-grid gap-2 mt-4">

                                    <div class="row g-2 mb-2"> {{-- g-2 es para un espacio pequeño entre columnas --}}
                                        <div class="col-6">
                                            {{-- Cambiamos el link por un botón de Alpine --}}
                                            <button @click="view = 'receive'"
                                                class="btn btn-light btn-lg w-100 shadow-sm border fw-bold">
                                                <i class="ri-qr-code-line me-1 text-primary"></i> RECIBIR
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
                                    <h5 class="mb-0 fw-bold">Recibir USDC</h5>
                                    <div style="width: 32px;"></div> {{-- Espaciador para centrar --}}
                                </div>

                                <p class="text-secondary small">Solo envía <strong>USDC (Polygon)</strong> a esta dirección
                                </p>

                                @php 
                                $user = session('telegram_user'); 
                                $wallet_address = url("/pay") . "/" . $user['username']; 
                                @endphp
                                <div class="bg-white p-3 d-inline-block rounded-3 shadow-sm mb-4">
                                    {{-- Aquí va el QR generado. Por ahora un placeholder
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ $wallet_address }}"
                                        alt="QR Address" class="img-fluid" style="width: 180px;">
                                    --}}
                                   <div class="bg-white p-3 d-inline-block mb-4 position-relative shadow-sm rounded-3">
                                        <div id="qr-container" style="padding: 10px; background: white;">
                                            {!! $qrService->generateSvg($wallet_address, 200) !!}
                                        </div>

                                        <div class="position-absolute top-50 start-50 translate-middle bg-white p-1 rounded-circle shadow"
                                            style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; overflow: hidden; z-index: 10;">
                                            
                                            <img src="{{ $user['photo_url'] }}" 
                                                class="rounded-circle" 
                                                style="width: 100%; height: 100%; object-fit: cover;">
                                                
                                        </div>
                                    </div>

                                    <div class="input-group mb-3">
                                        <input type="text" id="walletAddr"
                                            class="form-control bg-light border-0 text-center small"
                                            value="{{ $wallet_address }}" readonly>
                                        <button class="btn btn-primary" id="btnCopy" onclick="copyAddress()">
                                            <i id="copyIcon" class="ri-file-copy-line"></i>
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

    <script src="assets/vendor/aos/aos.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
        function copyAddress() {
            const copyText = document.getElementById("walletAddr");
            const btn = document.getElementById("btnCopy");
            const icon = document.getElementById("copyIcon");

            // Copiar al portapapeles
            navigator.clipboard.writeText(copyText.value).then(() => {
                // 1. Guardar clases originales
                const originalBtnClass = btn.className;
                const originalIconClass = icon.className;

                // 2. Aplicar estado "Éxito"
                btn.classList.replace('btn-primary', 'btn-success');
                icon.className = 'ri-check-line'; // El icono del "tick"

                // 3. Revertir después de 3 segundos
                setTimeout(() => {
                    btn.className = originalBtnClass;
                    icon.className = originalIconClass;
                }, 1000);
            });
        }
    </script>
@endsection