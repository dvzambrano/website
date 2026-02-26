<script src="https://cdnjs.cloudflare.com/ajax/libs/ethers/5.7.2/ethers.umd.min.js"></script>
<script type="module">
    import {
        createWeb3Modal,
        defaultConfig
    } from "https://esm.sh/@web3modal/ethers5@3.5.0";

    async function initWeb3Modal() {
        try {
            // 1. Fetch de tus rutas enriquecidas y normalizadas
            const response = await fetch("{{ $chains }}");
            const data = await response.json();

            // 2. Mapeo directo (el controlador garantiza que rpc y explorers sean arrays)
            window.supportedChains = Object.values(data).map(c => ({
                chainId: parseInt(c.chainId),
                name: c.name,
                currency: c.nativeCurrency.symbol,
                rpcUrl: c.rpc[0], // Tomamos el primer RPC (el controlador ya filtró los HTTPS)
                explorerUrl: c.explorers && c.explorers.length > 0 ? c.explorers[0].url : '',
                logo: c.logo
            }));

            // 3. Inicialización de Web3Modal (AppKit)
            window.web3Modal = createWeb3Modal({
                ethersConfig: defaultConfig({
                    name: '{{ $walletName }}',
                    description: '{{ $walletDescription }}',
                    url: window.location.origin,
                    icons: ['{{ $walletIcon }}']
                }),
                chains: window.supportedChains,
                projectId: '{{ $projectId }}',
                themeMode: '{{ $themeMode ?? 'light' }}'
            });

            // Variable para rastrear el estado previo y evitar recargas infinitas
            let wasConnected = false;
            // Callback personalizado para cuando se conecta la wallet
            window.web3Modal.subscribeState(state => {
                const address = window.web3Modal.getAddress();
                const isConnected = window.web3Modal.getIsConnected();
                const chainId = window.web3Modal.getChainId();

                console.log("🔄 Cambio de estado:", {
                    isConnected,
                    address,
                    chainId
                });

                if (isConnected && address) {
                    wasConnected = true;

                    // Ejecutar callback personalizado si existe
                    if (typeof window.onWalletConnected === 'function') {
                        window.onWalletConnected(address, chainId);
                    }
                } else if (!isConnected && wasConnected) {
                    console.warn("Wallet desconectada.");
                    // Ejecutar callback personalizado si existe
                    if (typeof window.onWalletDisconnected === 'function') {
                        window.onWalletDisconnected();
                    } else {
                        location.reload();
                    }
                }
            });

            console.log("✅ Web3Modal inicializado correctamente");

            // Ejecutar callback de inicialización si existe
            if (typeof window.onWeb3ModalInit === 'function') {
                window.onWeb3ModalInit(window.web3Modal);
            }

            return window.web3Modal;

        } catch (error) {
            console.error("❌ Error cargando Web3Modal:", error);

            // Ejecutar callback de error si existe
            if (typeof window.onWeb3ModalError === 'function') {
                window.onWeb3ModalError(error);
            }
        }
    }

    // Inicializar automáticamente cuando el DOM esté listo
    initWeb3Modal();
</script>
