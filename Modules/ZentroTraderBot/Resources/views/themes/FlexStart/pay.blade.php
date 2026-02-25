@extends('zentrotraderbot::themes.FlexStart.template')

@section('templatehead')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Estilos Core de Kashio (Sin Tailwind) */
        .custom-card {
            padding: 2.5rem;
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
    </style>
@endsection

@section('templatebody')
    <main id="main">
        <section id="values" class="values hero" style="overflow:visible; padding:10px;">
            <div class="container col-md-5 text-center" data-aos="zoom-in">

                <div x-data="{ view: 'balance' }">
                    <div x-show="view === 'balance'" x-transition>
                        <div class="custom-card text-center">

                            <div class="mb-10">
                                <div class="kashio-icon-container">
                                    <i class="fas fa-bolt text-2xl text-white"></i>
                                </div>
                                <h2 class="text-2xl font-extrabold text-dark">Recarga con criptomonedas</h2>
                                <p class="text-slate-500 small mt-1">Depósito inteligente gracias a deBridge</p>
                            </div>

                            <div id="connect-section" class="space-y-4">
                                <button onclick="connectAndScan()" id="btn-connect"
                                    class="btn btn-primary btn-lg w-100 shadow-sm">
                                    <i class="fas fa-wallet me-2"></i>
                                    <span>Conectar Billetera</span>
                                </button>
                                <p class="text-slate-400 small px-4" style="font-size: 11px;">
                                    Escanearemos tus balances en múltiples redes para facilitar el depósito.
                                </p>
                            </div>

                            <div id="scan-status" class="hidden py-5">
                                <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-4 text-dark fw-bold">Analizando tus activos...</p>

                                <p id="current-network-scan" class="text-slate-400 small px-4" style="font-size: 11px;">
                                    Iniciando...
                                </p>
                            </div>

                            <div id="payment-section" class="hidden space-y-8 text-start">
                                <div>
                                    <label class="text-slate-400 fw-bold text-uppercase small tracking-widest mb-2 d-block"
                                        style="font-size: 10px;">
                                        Origen del Pago
                                    </label>
                                    <div class="asset-selector-box">
                                        <img id="selected-asset-logo" src="" class="hidden"
                                            style="width: 32px; height: 32px; border-radius: 50%;">
                                        <div style="flex: 1;">
                                            <select id="asset-selector" onchange="updateQuote()"
                                                class="form-select border-0 bg-transparent shadow-none fw-bold"
                                                style="cursor:pointer;">
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div id="quote-card" class="quote-container hidden">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="small fw-bold text-slate-400 text-uppercase">Vas a pagar</span>
                                        <span id="txt-send-amount" class="small fw-bold text-dark">-</span>
                                    </div>
                                    <div class="d-flex justify-content-between pb-3 border-bottom mb-3">
                                        <span class="small fw-bold text-slate-400 text-uppercase">Recibes en Polygon</span>
                                        <span id="txt-receive-amount"
                                            class="h4 mb-0 fw-black text-primary">Calculando...</span>
                                    </div>
                                    <div class="d-flex gap-2 text-primary small" style="font-size: 10px; opacity: 0.8;">
                                        <i class="fas fa-shield-alt"></i>
                                        <span>Optimizado por deBridge DLN. La tasa incluye gas fee de destino.</span>
                                    </div>
                                </div>

                                <button id="btn-pay" disabled onclick="executeSwap()" class="btn-kashio-primary mt-4">
                                    Confirmar y Pagar
                                </button>

                                <button onclick="location.reload()"
                                    class="btn btn-link w-100 text-slate-400 text-uppercase fw-bold text-decoration-none"
                                    style="font-size: 11px;">
                                    Cambiar Billetera
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>


    <script src="https://cdnjs.cloudflare.com/ajax/libs/ethers/5.7.2/ethers.umd.min.js"></script>
    <script src="assets/js/web3.js"></script>

    <script>
        const KASHIO = {
            chains: @json($chains),
            web3: @json(config('web3.networks')),
            destChain: 137,
            destToken: "{{ config('web3.networks.POL.tokens.USDC.address') }}",
            userWallet: "{{ $userWallet }}"
        };

        let selectedData = null;
    </script>
@endsection
