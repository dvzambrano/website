@extends('zentrotraderbot::themes.html')

@section('head')
@endsection

@section('body')
    <section id="hero" class="hero d-flex align-items-center full-height-hero">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-6 text-center">

                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-body">
                            <div id="debridge-widget-container"></div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>

    {{-- Script oficial de deBridge --}}
    <script src="https://app.debridge.com/assets/scripts/widget.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', async function() {
            const container = document.getElementById('debridge-widget-container');

            try {

                const widget = await deBridge.widget({
                    element: 'debridge-widget-container',

                    title: 'Deposito en Kashio con criptomonedas',
                    //description: 'Cross-chain swaps powered by deBridge',

                    theme: 'light', //dark
                    //lang: 'es',

                    // Configuración de redes y tokens (Ajustado a la lógica de Kashio)
                    inputChain: 137, // Polygon (Matic) por defecto para Kashio
                    inputCurrency: {{ config('web3.networks.POL.tokens.USDC.address') }}, // USDC en Polygon
                    outputChain: 56, // BNB por defecto
                    //outputCurrency: {{ config('web3.tokens.USDT.address') }}, // USDT en BSC

                    address: 0xFAcD960564531bd336ed94fBBd0911408288FCF2,

                    styles: {
                        border: 'none',
                        borderRadius: '12px',
                        backgroundColor: '#ffffff',
                    },

                    // Tu código de referido para generar comisiones (Opcional)
                    // referrerCode: 'TU_CODIGO_AQUI', 

                    "disabledElements": [
                        //"Points",
                        "Routing",
                        "Latest trades",
                        "Exchange rate",
                        "ETA",
                        "Switch chains",
                        "Receiver balance"
                    ]
                });
                /*

                const widget = await deBridge.widget({
                    //"v": "1",
                    "element": "debridge-widget-container",
                    "title": "Deposito en Kashio con criptomonedas",
                    "description": "",
                    //"width": "600",
                    //"height": "800",
                    //"r": null,
                    "supportedChains": "{\"inputChains\":{\"1\":\"all\",\"10\":\"all\",\"25\":\"all\",\"56\":\"all\",\"100\":\"all\",\"137\":\"all\",\"143\":\"all\",\"146\":\"all\",\"747\":\"all\",\"999\":\"all\",\"1329\":\"all\",\"1514\":\"all\",\"1776\":\"all\",\"4326\":\"all\",\"5000\":\"all\",\"8453\":\"all\",\"32769\":\"all\",\"42161\":\"all\",\"43114\":\"all\",\"59144\":\"all\",\"60808\":\"all\",\"999999\":\"all\",\"7565164\":\"all\",\"728126428\":\"all\"},\"outputChains\":{\"137\":[\"0x3c499c542cef5e3811e1192ce70d8cc03d5c3359\"]}}",
                    "inputChain": 56,
                    "inputCurrency": "0x55d398326f99059ff775485246999027b3197955",
                    "outputChain": 137,
                    "outputCurrency": "0x3c499c542cef5e3811e1192ce70d8cc03d5c3359",
                    "address": "0xFAcD960564531bd336ed94fBBd0911408288FCF2",
                    "showSwapTransfer": true,
                    //"amount": "",
                    //"outputAmount": "",
                    //"isAmountFromNotModifiable": true,
                    "isAmountToNotModifiable": true,
                    "lang": "es",
                    "mode": "deswap",
                    "isEnableCalldata": false,
                    "styles": "e30=",
                    "theme": "light",
                    "isHideLogo": false,
                    "logo": "https://kashio.micalme.com/laravel/ZentroTraderBot/FlexStart/assets/img/logo.png",
                    //"disabledWallets": [],
                    "disabledElements": [
                        "Points",
                        "Routing",
                        "Latest trades",
                        "Exchange rate",
                        "ETA",
                        "Switch chains",
                        "Receiver balance"
                    ]
                });
                */

                console.log('deBridge Widget initialized successfully');

                // Ejemplo de escucha de eventos (opcional)
                widget.on('bridge', (params) => {
                    console.log('Transacción iniciada:', params);
                });

            } catch (error) {
                console.error('Error al cargar el widget de deBridge:', error);
                container.innerHTML = `
                <div class="alert alert-warning">
                    No se pudo cargar el módulo de intercambio. Por favor, intenta más tarde.
                </div>`;
            }
        });
    </script>
@endsection
