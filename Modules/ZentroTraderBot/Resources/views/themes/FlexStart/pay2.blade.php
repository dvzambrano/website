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
@endsection

@section('body')
    <!-- ======= Header ======= -->
    <header id="header" class="header fixed-top">
        <div class="container-fluid container-xl d-flex align-items-center justify-content-between">

            <nav id="navbar" class="navbar">
                <ul>
                </ul>
                <i class="bi bi-list mobile-nav-toggle"></i>
            </nav><!-- .navbar -->

        </div>
    </header><!-- End Header -->

    <main id="main">

        <!-- ======= Values Section ======= -->
        <section class="hero values" style="overflow:visible">

            <div class="container" data-aos="fade-up">

                <header class="section-header">
                    <p>Seleccione la red:</p>
                </header>

                <div class="row">

                    @foreach ($chains as $chain)
                        <div class="col-lg-2" data-aos="fade-up" data-aos-delay="200" style="padding:10px;">
                            <div class="box" style="background-color: white;">
                                <img src="{{ $chain['logoURI'] }}" class="img-fluid" alt="{{ $chain['chainName'] }}"
                                    style="padding:10px;">
                                <h3>{{ $chain['chainName'] }}</h3>
                            </div>
                        </div>
                    @endforeach

                </div>

            </div>

        </section><!-- End Values Section -->
    </main>

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



    <script>
        const ERC20_ABI = ["function balanceOf(address owner) view returns (uint256)",
            "function decimals() view returns (uint8)"
        ];

        async function connectAndScan() {
            if (!window.ethereum) return alert("Instala MetaMask");

            try {
                provider = new ethers.providers.Web3Provider(window.ethereum);
                const accounts = await provider.send("eth_requestAccounts", []);
                userAddress = accounts[0];
                signer = provider.getSigner();

                let network = await provider.getNetwork();

                // Verificamos si la red actual (ID: 1) está en nuestra lista de deBridge
                const isSupported = KASHIO.chains.some(c => String(c.chainId) === String(network.chainId));

                if (!isSupported && KASHIO.chains.length > 0) {
                    const firstChain = KASHIO.chains[0];
                    console.log(`Red ${network.chainId} no soportada. Intentando cambiar a ${firstChain.chainName}...`);

                    const success = await switchNetwork(firstChain.chainId);

                    if (success) {
                        // Re-instanciamos el provider tras el cambio de red
                        provider = new ethers.providers.Web3Provider(window.ethereum);
                        network = await provider.getNetwork();
                    } else {
                        return; // Si el usuario cancela el cambio, nos detenemos
                    }
                }

                // Si llegamos aquí, ya estamos en una red válida o el usuario cambió manualmente
                document.getElementById('status-pill').classList.remove('hidden');
                document.getElementById('addr-display').innerText =
                    `${userAddress.substring(0,6)}...${userAddress.substring(38)}`;

                await scanBalances();
                goToStep(1);
            } catch (e) {
                console.error("Error en conexión:", e);
            }
        }

        async function switchNetwork(chainId) {
            const hexChainId = '0x' + parseInt(chainId).toString(16);
            try {
                await window.ethereum.request({
                    method: 'wallet_switchEthereumChain',
                    params: [{
                        chainId: hexChainId
                    }],
                });
                return true;
            } catch (error) {
                // El error 4902 significa que la red no está agregada en MetaMask
                if (error.code === 4902) {
                    alert("Por favor, agrega esta red a tu MetaMask para continuar.");
                }
                console.error("Error al cambiar de red:", error);
                return false;
            }
        }

        async function scanBalances() {
            const select = document.getElementById('select-asset');
            select.innerHTML = '<option value="">Buscando tokens con saldo...</option>';

            const network = await provider.getNetwork();

            // DEBUG PARA DONEL: Mira estos dos valores en tu consola
            console.log("ID de tu Wallet:", network.chainId);
            console.log("IDs disponibles en KASHIO:", KASHIO.chains.map(c => c.chainId));

            // Forzamos que ambos sean String para una comparación segura
            const currentChain = KASHIO.chains.find(c => String(c.chainId) === String(network.chainId));

            if (!currentChain) {
                // Si entra aquí, es porque network.chainId no está en tu lista de arriba (56, 42161, 8453, 10, 43114)
                select.innerHTML =
                    `<option value="">Red ${network.chainId} no soportada. Cambia a BSC, Base o Arbitrum.</option>`;
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
    </script>
@endsection
