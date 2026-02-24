<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kashio Pay - Smart Deposit</title>

    <script src="https://unpkg.com/ethers@5.7.2/dist/ethers.umd.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');

        body {
            background-color: #05070a;
            color: #e2e8f0;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .step-card {
            display: none;
        }

        .step-card.active {
            display: block;
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .glass {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .kashio-gradient {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
        }

        select option {
            background-color: #0f172a;
            color: white;
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full glass rounded-[2.5rem] overflow-hidden shadow-2xl relative">
        <div id="progress" class="h-1 kashio-gradient transition-all duration-500" style="width: 20%"></div>

        <div class="p-8">
            <div class="flex justify-between items-center mb-10">
                <h1 class="text-2xl font-black tracking-tighter italic text-white">KASHIO<span
                        class="text-blue-500">.</span></h1>
                <div id="status-pill"
                    class="hidden flex items-center gap-2 bg-blue-500/10 px-3 py-1 rounded-full border border-blue-500/20">
                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                    <span id="addr-display" class="text-[10px] font-mono text-blue-400">0x...</span>
                </div>
            </div>

            <div id="step-0" class="step-card active text-center">
                <div class="mb-8">
                    <div
                        class="w-24 h-24 kashio-gradient rounded-3xl rotate-12 flex items-center justify-center mx-auto shadow-2xl shadow-blue-500/20">
                        <i class="fa-solid fa-wallet text-4xl text-white -rotate-12"></i>
                    </div>
                </div>
                <h2 class="text-2xl font-bold text-white mb-2">Bienvenido, {{ $userUuid ?? 'Donel' }}</h2>
                <p class="text-slate-400 text-sm mb-10 px-6">Conecta tu wallet para detectar automáticamente tus activos
                    disponibles en cada red.</p>
                <button onclick="connectAndScan()" id="btn-main"
                    class="w-full py-4 kashio-gradient rounded-2xl font-bold text-white transition-all active:scale-95">
                    Conectar Wallet
                </button>
            </div>

            <div id="step-1" class="step-card">
                <div class="space-y-6">
                    <div>
                        <div class="flex justify-between items-end mb-2 px-1">
                            <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Activos
                                detectados</label>
                            <button onclick="connectAndScan()" class="text-[10px] text-blue-500 font-bold"><i
                                    class="fa-solid fa-sync mr-1"></i> Re-escanear</button>
                        </div>
                        <select id="select-asset"
                            class="w-full bg-slate-900/50 border border-slate-700 rounded-2xl p-4 outline-none focus:border-blue-500 text-white transition-all cursor-pointer">
                        </select>
                    </div>

                    <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-5">
                        <div class="flex justify-between mb-2 px-1">
                            <span class="text-[10px] font-bold text-slate-500 uppercase">Cantidad a depositar</span>
                            <span id="max-label"
                                class="text-[10px] text-blue-400 font-bold cursor-pointer hover:underline">MAX</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <input type="number" id="input-amount" placeholder="0.00"
                                class="bg-transparent text-3xl font-bold text-white outline-none w-full">
                            <span id="token-symbol" class="text-slate-500 font-bold text-sm">---</span>
                        </div>
                    </div>

                    <button onclick="handleEstimation()" id="btn-estimate"
                        class="w-full py-4 kashio-gradient rounded-2xl font-bold text-white shadow-lg active:scale-95">
                        Verificar Recepción <i class="fa-solid fa-chevron-right ml-2 text-[10px]"></i>
                    </button>
                </div>
            </div>

            <div id="step-2" class="step-card">
                <h2 class="text-xl font-bold text-white mb-6">Confirmar Depósito</h2>
                <div class="space-y-4">
                    <div class="bg-blue-600/5 border border-blue-500/20 rounded-3xl p-6 text-center">
                        <span class="text-xs text-slate-500 block mb-1">Recibirás en tu cuenta Kashio</span>
                        <span id="res-receive" class="text-4xl font-black text-green-400 italic">0.00 USDC</span>
                        <div class="flex items-center justify-center gap-2 mt-2 text-[10px] text-slate-500">
                            <i class="fa-brands fa-ethereum"></i> Red Polygon
                        </div>
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-900/50 space-y-3 text-xs">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Pagas desde:</span>
                            <span id="res-source" class="text-white font-medium">--</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Tarifa de deBridge:</span>
                            <span id="res-fee" class="text-white font-medium">--</span>
                        </div>
                    </div>

                    <button onclick="handleCreateOrder()"
                        class="w-full py-4 bg-white text-black rounded-2xl font-black text-sm uppercase tracking-tighter hover:bg-blue-50 transition-all">
                        Firmar y Enviar Fondos
                    </button>
                </div>
            </div>

            <div id="step-3" class="step-card text-center py-10">
                <div id="loader" class="mb-6">
                    <div
                        class="w-16 h-16 border-4 border-blue-500/20 border-t-blue-500 rounded-full animate-spin mx-auto">
                    </div>
                </div>
                <div id="success-icon" class="hidden mb-6">
                    <div
                        class="w-16 h-16 bg-green-500/20 text-green-500 rounded-full flex items-center justify-center mx-auto text-3xl">
                        <i class="fa-solid fa-check"></i>
                    </div>
                </div>
                <h2 id="status-title" class="text-xl font-bold text-white mb-2">Procesando...</h2>
                <p id="status-text" class="text-slate-400 text-sm px-6">Estamos preparando la orden cross-chain en la
                    blockchain.</p>
            </div>
        </div>
    </div>



    <script>
        const ERC20_ABI = ["function balanceOf(address owner) view returns (uint256)",
            "function decimals() view returns (uint8)"
        ];

        // Inyección de datos desde Laravel
        const KASHIO = {
            chains: (function() {
                const data = {!! json_encode($availableRoutes) !!};
                return data.supportedSourceChains || [];
            })(),
            quoteUrl: "{!! route('pay.api.quote') !!}",
            orderUrl: "{!! route('pay.api.order') !!}",
            csrfToken: "{{ csrf_token() }}",
            userWallet: "{{ $userWallet }}"
        };

        let userAddress, provider, signer;

        async function connectAndScan() {
            if (!window.ethereum) return alert("Instala MetaMask");

            try {
                provider = new ethers.providers.Web3Provider(window.ethereum);
                const accounts = await provider.send("eth_requestAccounts", []);
                userAddress = accounts[0];
                signer = provider.getSigner();

                // Actualizar interfaz
                document.getElementById('status-pill').classList.remove('hidden');
                document.getElementById('addr-display').innerText =
                    `${userAddress.substring(0,6)}...${userAddress.substring(38)}`;

                await scanBalances();
                goToStep(1);
            } catch (e) {
                console.error(e);
            }
        }

        async function scanBalances() {
            const select = document.getElementById('select-asset');
            select.innerHTML = '<option value="">Buscando tokens con saldo...</option>';

            const network = await provider.getNetwork();
            const currentChain = KASHIO.chains.find(c => c.chainId == network.chainId);

            if (!currentChain) {
                select.innerHTML = '<option value="">Red no soportada por deBridge. Cambia de red.</option>';
                return;
            }

            const tokens = Object.values(currentChain.tokens);
            let found = 0;

            for (const token of tokens) {
                // Solo escaneamos tokens principales para ahorrar tiempo
                if (!['USDC', 'USDT', 'ETH', 'MATIC', 'WETH', 'DAI'].includes(token.symbol)) continue;

                try {
                    let balance;
                    if (token.address === "0x0000000000000000000000000000000000000000") {
                        balance = await provider.getBalance(userAddress);
                    } else {
                        const contract = new ethers.Contract(token.address, ERC20_ABI, provider);
                        balance = await contract.balanceOf(userAddress);
                    }

                    if (balance.gt(0)) {
                        const decimals = token.decimals || 18;
                        const formatted = ethers.utils.formatUnits(balance, decimals);
                        const opt = document.createElement('option');
                        opt.value = `${currentChain.chainId}|${token.address}|${token.symbol}`;
                        opt.text =
                            `${token.symbol} - Saldo: ${parseFloat(formatted).toFixed(4)} (${currentChain.chainName})`;
                        opt.dataset.max = formatted;
                        select.appendChild(opt);
                        found++;
                    }
                } catch (err) {
                    console.warn(err);
                }
            }

            if (found === 0) {
                select.innerHTML =
                    '<option value="">No tienes saldo en esta red (Prueba cambiar de red en MetaMask)</option>';
            } else {
                const empty = select.querySelector('option[value=""]');
                if (empty) empty.text = "Selecciona un activo";
                updateSymbol();
            }
        }

        function updateSymbol() {
            const val = document.getElementById('select-asset').value;
            if (val) document.getElementById('token-symbol').innerText = val.split('|')[2];
        }

        document.getElementById('select-asset').addEventListener('change', updateSymbol);
        document.getElementById('max-label').addEventListener('click', () => {
            const sel = document.getElementById('select-asset');
            const max = sel.options[sel.selectedIndex]?.dataset.max;
            if (max) document.getElementById('input-amount').value = max;
        });

        async function handleEstimation() {
            const amount = document.getElementById('input-amount').value;
            const assetData = document.getElementById('select-asset').value;
            if (!amount || !assetData) return alert("Completa los datos");

            const [srcChainId, srcToken, symbol] = assetData.split('|');
            const btn = document.getElementById('btn-estimate');
            btn.innerHTML = '<i class="fa-solid fa-spinner animate-spin"></i>';

            try {
                const res = await fetch(
                    `${KASHIO.quoteUrl}?amount=${amount}&srcChainId=${srcChainId}&srcToken=${srcToken}`);
                const data = await res.json();

                document.getElementById('res-receive').innerText =
                    `${data.estimation.dstChainTokenOut.amountFormatted} USDC`;
                document.getElementById('res-source').innerText = `${amount} ${symbol} en ${srcChainId}`;
                document.getElementById('res-fee').innerText =
                    `$ ${data.estimation.costsDetails.totalFixedFee || '0.00'}`;
                goToStep(2);
            } catch (e) {
                alert("Error de cotización");
            } finally {
                btn.innerHTML = 'Verificar Recepción <i class="fa-solid fa-chevron-right ml-2 text-[10px]"></i>';
            }
        }

        async function handleCreateOrder() {
            const [srcChainId, srcToken] = document.getElementById('select-asset').value.split('|');
            const amount = document.getElementById('input-amount').value;

            goToStep(3);
            try {
                const res = await fetch(KASHIO.orderUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': KASHIO.csrfToken
                    },
                    body: JSON.stringify({
                        amount,
                        srcChainId,
                        srcToken,
                        userWallet: KASHIO.userWallet
                    })
                });
                const order = await res.json();

                const tx = await signer.sendTransaction({
                    to: order.tx.to,
                    data: order.tx.data,
                    value: order.tx.value
                });

                document.getElementById('loader').classList.add('hidden');
                document.getElementById('success-icon').classList.remove('hidden');
                document.getElementById('status-title').innerText = "¡Pago Enviado!";
                document.getElementById('status-text').innerText = "Tu depósito llegará a Kashio en pocos minutos.";

                setTimeout(() => window.location.href = "https://t.me/TuBot", 5000);
            } catch (e) {
                alert("Error en la firma");
                goToStep(2);
            }
        }

        function goToStep(s) {
            document.querySelectorAll('.step-card').forEach(c => c.classList.remove('active'));
            document.getElementById(`step-${s}`).classList.add('active');
            document.getElementById('progress').style.width = (s * 25 + 20) + '%';
        }
    </script>
</body>

</html>
