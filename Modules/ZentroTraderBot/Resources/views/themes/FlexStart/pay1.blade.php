@extends('zentrotraderbot::themes.html')

@section('htmlhead')
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <link href="assets/css/notifications.css" rel="stylesheet">

    <style>
        body {
            background-color: #f8fafc;
            /* Fondo gris muy claro */
            color: #1e293b;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        /* Personalización del select para que no se vea nativo y feo */
        select option {
            background-color: #ffffff;
            color: #1e293b;
        }

        /* Input focus effects */
        .focus-ring:focus-within {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }
    </style>
@endsection

@section('htmlbodycss', 'min-h-screen flex items-center justify-center p-4')

@section('htmlbody')
    <div class="max-w-md w-full glass-card rounded-[2.5rem] p-10 shadow-xl shadow-slate-200/50">

        <div class="text-center mb-10">
            <div
                class="inline-flex items-center justify-center w-16 h-16 bg-blue-600 rounded-2xl mb-4 shadow-lg shadow-blue-500/30">
                <i class="fas fa-bolt text-2xl text-white"></i>
            </div>
            <h2 class="text-2xl font-extrabold text-slate-800">Checkout Kashio</h2>
            <p class="text-slate-500 text-sm mt-1">Suscripción inteligente vía ZentroTraderBot</p>
        </div>

        <div id="connect-section" class="space-y-4">
            <button onclick="connectAndScan()" id="btn-connect"
                class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-4 rounded-2xl transition-all flex items-center justify-center gap-3 shadow-lg shadow-slate-900/20">
                <i class="fas fa-wallet"></i>
                <span>Conectar Billetera</span>
            </button>
            <p class="text-center text-[11px] text-slate-400 px-4">Al conectar, escanearemos tus balances en múltiples redes
                para facilitar tu pago.</p>
        </div>

        <div id="scan-status" class="hidden py-10 text-center">
            <div class="relative inline-block">
                <div class="w-12 h-12 border-4 border-slate-200 border-t-blue-600 rounded-full animate-spin"></div>
            </div>
            <p class="mt-4 text-slate-600 font-semibold">Analizando tus activos...</p>
            <p id="current-network-scan" class="text-xs text-slate-400 mt-1 uppercase tracking-widest font-medium">
                Iniciando...</p>
        </div>

        <div id="payment-section" class="hidden space-y-8">
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-[0.15em] mb-3 ml-1">Origen del
                    Pago</label>
                <div
                    class="flex items-center gap-4 bg-white border border-slate-200 rounded-2xl p-4 transition-all focus-ring">
                    <img id="selected-asset-logo" src="" class="w-8 h-8 rounded-full hidden shadow-sm">
                    <div class="flex-1">
                        <select id="asset-selector" onchange="updateQuote()"
                            class="bg-transparent w-full text-slate-800 font-medium outline-none appearance-none cursor-pointer text-sm">
                        </select>
                    </div>
                    <i class="fas fa-chevron-down text-slate-300 text-xs"></i>
                </div>
            </div>

            <div id="quote-card" class="bg-blue-50 rounded-[1.5rem] p-6 border border-blue-100 hidden">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-tight">Vas a pagar</span>
                    <span id="txt-send-amount" class="text-sm font-bold text-slate-700">-</span>
                </div>
                <div class="flex justify-between items-center pb-4 border-b border-blue-200/50 mb-4">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-tight">Recibes en Polygon</span>
                    <span id="txt-receive-amount" class="text-xl font-black text-blue-600">Calculando...</span>
                </div>
                <div class="flex items-start gap-3 text-[10px] text-blue-500/80 leading-relaxed">
                    <i class="fas fa-shield-alt mt-0.5"></i>
                    <span>Optimizado por deBridge DLN. La tasa incluye el gas fee de la red de destino.</span>
                </div>
            </div>

            <button id="btn-pay" disabled onclick="executeSwap()"
                class="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-slate-200 disabled:text-slate-400 disabled:shadow-none text-white font-bold py-4 rounded-2xl transition-all shadow-xl shadow-blue-600/20">
                Confirmar y Pagar
            </button>

            <button onclick="location.reload()"
                class="w-full text-slate-400 text-[11px] font-medium hover:text-slate-600 transition-colors uppercase tracking-widest">
                Cambiar Billetera
            </button>
        </div>

    </div>

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
