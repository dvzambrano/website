@extends('zentrotraderbot::themes.FlexStart.template')

@section('templatehead')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Estilos Core de Kashio (Sin Tailwind) */
        .custom-card {
            padding: 2rem;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 20px;
            box-shadow: 0px 0px 30px rgba(1, 41, 112, 0.08) !important;
        }

        .kashio-icon-container {
            width: 64px;
            height: 64px;
            background-color: #2563eb;
            border-radius: 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3);
            margin-bottom: 1rem;
        }

        .btn-kashio-primary {
            background-color: #2563eb;
            color: white;
            font-weight: 700;
            padding: 1rem;
            border-radius: 1rem;
            border: none;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.2);
        }

        .btn-kashio-primary:hover {
            background-color: #1d4ed8;
            transform: translateY(-1px);
        }

        .btn-kashio-primary:disabled {
            background-color: #e2e8f0;
            color: #94a3b8;
            box-shadow: none;
            cursor: not-allowed;
        }

        .btn-kashio-dark {
            background-color: #0f172a;
            color: white;
            font-weight: 700;
            padding: 1rem;
            border-radius: 1rem;
            border: none;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.2);
        }

        .asset-selector-box {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            padding: 1rem;
            transition: all 0.2s;
        }

        .asset-selector-box:focus-within {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .quote-container {
            background-color: #eff6ff;
            border-radius: 1.5rem;
            padding: 1.5rem;
            border: 1px solid #dbeafe;
            margin-top: 1.5rem;
        }

        .text-slate-500 {
            color: #64748b;
        }

        .text-slate-400 {
            color: #94a3b8;
        }

        .font-extrabold {
            font-weight: 800;
        }

        .tracking-widest {
            letter-spacing: 0.1em;
        }

        /* Utility Helpers */
        .hidden {
            display: none !important;
        }

        .mt-10 {
            margin-top: 2.5rem;
        }

        .mb-10 {
            margin-bottom: 2.5rem;
        }

        .space-y-4>*+* {
            margin-top: 1rem;
        }

        .space-y-8>*+* {
            margin-top: 2rem;
        }

        .hover-bg-light:hover {
            background-color: #f1f5f9 !important;
            transform: scale(1.02);
            transition: all 0.2s ease;
        }

        .kashio-list-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.05);
        }

        .fw-black {
            font-weight: 900;
        }

        #error-detail-text {
            font-family: monospace;
            font-size: 10px;
            /* ESTO ES LO IMPORTANTE */
            word-break: break-all;
            /* Rompe el texto en cualquier carácter */
            white-space: pre-wrap;
            /* Mantiene saltos de línea si los hay */
            overflow-wrap: break-word;
            /* Refuerzo para navegadores modernos */
        }
    </style>
@endsection

@section('templatebody')
    <main id="main">
        <section id="values" class="values hero" style="overflow:visible; padding:10px;">
            <div class="container col-md-5 text-center" data-aos="zoom-in">

                <div x-data="{ view: 'balance' }">
                    <div x-show="view === 'balance'" x-transition>
                        <div class="custom-card text-center">

                            <div id="connect-section" class="space-y-4">
                                <div class="mb-10">
                                    <div class="kashio-icon-container">
                                        <i class="fas fa-bolt text-2xl text-white"></i>
                                    </div>
                                    <h2 class="text-2xl font-extrabold text-dark">Recarga con criptomonedas</h2>
                                    <p class="text-slate-500 small mt-1">Depósito inteligente gracias a deBridge</p>
                                </div>

                                <button onclick="connectAndScan()" id="btn-connect"
                                    class="btn btn-primary btn-lg w-100 shadow-sm">
                                    <i class="fas fa-wallet me-2"></i>
                                    <span>Conectar Billetera</span>
                                </button>
                                <p class="text-slate-400 small px-4" style="font-size: 11px;">
                                    Escanearemos tus balances en la red conectada para facilitar el depósito.
                                </p>
                            </div>

                            <div id="scan-status" class="hidden py-5">

                                <div class="mb-10">
                                    <div class="kashio-icon-container" style="background-color: #6366f1;"> <i
                                            class="fas fa-search-dollar text-2xl text-white"></i>
                                    </div>
                                    <h2 class="text-2xl font-extrabold text-dark">Rastreando activos</h2>
                                    <p class="text-slate-500 small mt-1">Buscando balances en tus redes conectadas</p>
                                </div>

                                <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-4 text-dark fw-bold">Analizando tus activos...</p>
                                <p id="current-network-scan" class="text-slate-400 small px-4" style="font-size: 11px;">
                                    Iniciando...
                                </p>
                            </div>

                            <div id="payment-section" class="hidden" x-data="{
                                step: 'list',
                                selectedAsset: null,
                                amount: '',
                                errorMessage: ''
                            }"
                                @asset-selected.window="selectedAsset = $event.detail; step = 'amount'; amount = '';"
                                x-effect="if(step === 'list') { typeof window.stopQuotePolling === 'function' ? window.stopQuotePolling() : null; }">

                                <div x-show="step === 'list'" x-transition>

                                    <div class="">
                                        <div class="kashio-icon-container" style="background-color: #f59e0b;"> <i
                                                class="fas fa-list-ul text-2xl text-white"></i>
                                        </div>
                                        <h2 class="text-2xl font-extrabold text-dark">Selecciona tu moneda</h2>
                                    </div>

                                    <div class="mb-4 pt-3">
                                        <label
                                            class="text-slate-400 fw-bold text-uppercase small tracking-widest mb-3 d-block"
                                            style="font-size: 10px;">
                                            Red Conectada
                                        </label>
                                        <button type="button" onclick="window.web3Modal.open({ view: 'Networks' })"
                                            class="asset-selector-box w-100 hover-bg-light border-dashed"
                                            style="border-style: dashed;">
                                            <div class="kashio-list-icon rounded-circle bg-light me-2"
                                                style="width: 24px; height: 24px;">
                                                <i class="fas fa-network-wired text-primary" style="font-size: 12px;"></i>
                                            </div>
                                            <div class="flex-grow-1 text-start">
                                                <span class="fw-bold small text-dark" id="display-network-name">Cargando
                                                    red...</span>
                                            </div>
                                            <i class="fas fa-chevron-right text-slate-400 small"></i>
                                        </button>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="text-slate-400 fw-bold text-uppercase small tracking-widest mb-0"
                                            style="font-size: 10px;">
                                            Activos detectados
                                        </h6>
                                        <button type="button" onclick="manualRescan()"
                                            class="btn btn-sm btn-outline-primary border-0" style="font-size: 11px;">
                                            <i class="fas fa-sync-alt me-1"></i> Actualizar
                                        </button>
                                    </div>

                                    <div id="assets-list-container" class="list-group list-group-flush text-start mb-4">
                                    </div>

                                    <button onclick="disconnectAndExit()"
                                        class="btn btn-link w-100 text-slate-400 text-uppercase fw-bold text-decoration-none"
                                        style="font-size: 11px;">
                                        Cancelar y salir
                                    </button>
                                </div>

                                <div x-show="step === 'amount'" x-transition style="display: none;">

                                    <div class="mb-10">
                                        <div class="kashio-icon-container" style="background-color: #2563eb;"> <i
                                                class="fas fa-file-invoice-dollar text-2xl text-white"></i>
                                        </div>
                                        <h2 class="text-2xl font-extrabold text-dark">Monto de recarga</h2>
                                        <p class="text-slate-500 small mt-1">Define cuánto quieres depositar en tu cuenta
                                        </p>
                                    </div>

                                    <div class="d-flex align-items-center mb-4">
                                        <button @click="step = 'list'; clearQuoteUI();"
                                            class="btn btn-sm btn-light rounded-circle me-3">
                                            <i class="fas fa-arrow-left"></i>
                                        </button>
                                        <h6 class="mb-0 fw-bold">Configurar Depósito</h6>
                                    </div>
                                    <div class="asset-selector-box mb-4">
                                        <img :src="selectedAsset?.logo"
                                            style="width: 32px; height: 32px; border-radius: 50%;">
                                        <div class="flex-grow-1">
                                            <span class="d-block fw-bold" x-text="selectedAsset?.symbol"></span>
                                            <span class="text-slate-400 small"
                                                x-text="'Saldo: ' + selectedAsset?.balance"></span>
                                        </div>
                                        <button type="button"
                                            @click="amount = selectedAsset.balance; updateQuoteManual();"
                                            class="btn btn-sm btn-outline-primary ms-2">MAX</button>
                                    </div>

                                    <div class="mb-4">
                                        <label
                                            class="text-slate-400 fw-bold text-uppercase small tracking-widest mb-2 d-block"
                                            style="font-size: 10px;">Monto a enviar</label>
                                        <input type="number" x-model="amount"
                                            @input.debounce.500ms="typeof updateQuoteManual === 'function' ? updateQuoteManual() : null"
                                            class="form-control form-control-lg border-2 fw-bold text-center"
                                            placeholder="0.00">
                                    </div>

                                    <div id="quote-card" class="quote-container mb-4 hidden">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="small fw-bold text-slate-400 text-uppercase">Tú envías</span>
                                            <span id="txt-send-amount" class="small fw-bold text-dark">-</span>
                                        </div>
                                        <div class="d-flex justify-content-between pb-3 border-bottom mb-3">
                                            <span class="small fw-bold text-slate-400 text-uppercase">Recibes en
                                                tu cuenta</span>
                                            <span id="txt-receive-amount"
                                                class="h4 mb-0 fw-black text-primary">Calculando...</span>
                                        </div>
                                    </div>

                                    <button id="btn-pay" disabled onclick="executeSwap()" class="btn-kashio-primary">
                                        Confirmar y Pagar
                                    </button>
                                </div>

                                <div x-show="step === 'success'" x-transition style="display: none;">
                                    <div class="text-center py-4">
                                        <div class="mb-4">
                                            <div class="kashio-icon-container" style="background-color: #10b981;">
                                                <i class="fas fa-check text-2xl text-white"></i>
                                            </div>
                                        </div>
                                        <h3 class="fw-black text-dark">¡Transacción Enviada!</h3>
                                        <p class="text-slate-500 small">Tu depósito está siendo procesado.</p>
                                        <p class="text-slate-500 small">Los fondos
                                            llegarán a tu cuenta pronto.</p>

                                        <div class="quote-container text-start mb-4">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span class="small text-slate-400">Estado:</span>
                                                <span class="small fw-bold text-success">En camino</span>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span class="small text-slate-400">Destino:</span>
                                                <span class="small fw-bold">USD</span>
                                            </div>
                                        </div>

                                        <button onclick="location.reload()" class="btn-kashio-dark">Volver al
                                            inicio</button>
                                    </div>
                                </div>

                                <div x-show="step === 'error'" x-transition style="display: none;">
                                    <div class="text-center py-4">
                                        <div class="mb-4">
                                            <div class="kashio-icon-container" style="background-color: #ef4444;"> <i
                                                    class="fas fa-exclamation-triangle text-2xl text-white"></i>
                                            </div>
                                        </div>

                                        <h3 class="fw-black text-dark">Hubo un problema</h3>
                                        <p class="text-slate-500 small px-4">
                                            La transacción no pudo completarse.
                                        </p>

                                        <div class="quote-container text-start mb-4"
                                            style="background-color: #fef2f2; border-color: #fee2e2;">
                                            <div class="small text-danger fw-bold mb-1">Detalle del error:</div>
                                            <div class="small text-slate-600" id="error-detail-text"
                                                style="font-family: monospace; font-size: 10px; word-break: break-all;"
                                                x-text="errorMessage">
                                                {{-- Aquí cae el error --}}
                                            </div>
                                        </div>

                                        <div class="space-y-4">
                                            <button @click="step = 'amount'" class="btn-kashio-primary">
                                                Intentar de nuevo
                                            </button>

                                            <button onclick="location.reload()"
                                                class="btn btn-link w-100 text-slate-400 text-uppercase fw-bold text-decoration-none"
                                                style="font-size: 11px;">
                                                Volver al inicio
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
    </main>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <script src="assets/js/web3.js"></script>

    <script>
        window.onWalletConnected = function(address, chainId) {
            // Referencias a tus secciones de la página
            const connectSection = document.getElementById('connect-section');
            const scanStatus = document.getElementById('scan-status');
            const paymentSection = document.getElementById('payment-section');

            // 1. Ocultar el botón de inicio
            if (connectSection) connectSection.classList.add('hidden');

            // 2. Mostrar el estado de escaneo (Spinner)
            if (scanStatus) scanStatus.classList.remove('hidden');

            // 3. Ejecutar el escaneo de tokens
            if (typeof window.startScanning === 'function') {
                console.log("🚀 Lanzando escaneo para:", address);
                window.startScanning(address, chainId);
            }
        };
        window.onWalletDisconnected = function(address, chainId) {
            location.reload();
        };

        const KASHIO = {
            web3: @json(config('web3.networks')),
            destChain: 137,
            destToken: "{{ config('web3.networks.POL.tokens.USDC.address') }}",
            userWallet: "{{ $userWallet }}",
            quoteUrl: "{{ route('pay.api.quote') }}",
            tokensUrl: "{{ route('pay.api.tokens') }}",
            createOrderUrl: "{{ route('pay.api.order') }}"
        };
    </script>



    {{-- Incluir el partial del Web3Modal --}}
    @include('zentrotraderbot::partials.wallet-connect', [
        'projectId' => config('zentrotraderbot.wallet_connect_api_key'),
        'walletName' => config('zentrotraderbot.bot'),
        'walletDescription' => 'Wallet inteligente',
        'walletIcon' => 'https://avatars.githubusercontent.com/u/37784886',
        'themeMode' => 'light',
        'chains' => route('pay.api.routes'),
    ])
@endsection
