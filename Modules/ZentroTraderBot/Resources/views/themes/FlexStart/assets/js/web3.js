/**
 * Kashio - Web3 Logic & Bridge Integration
 * Powered by Gemini 3.0 Flash
 */

// --- Estado Global ---
window.quoteInterval = null;
window.QUOTE_REFRESH_TIME = 15000;
let selectedData = null; // IMPORTANTE: Se llena al seleccionar un token

// --- Inicialización y Eventos de Wallet ---
if (window.ethereum) {
    window.ethereum.on("accountsChanged", () => location.reload());
    window.ethereum.on("chainChanged", () => location.reload());
}

/**
 * Conecta la wallet manualmente (si el subscribeState del modal no se dispara)
 */
async function connectAndScan() {
    if (!window.web3Modal) {
        toastr.warning("Inicializando conexión...");
        return;
    }

    try {
        await window.web3Modal.open();
        const address = window.web3Modal.getAddress();

        if (address) {
            document.getElementById("connect-section").classList.add("hidden");
            document.getElementById("scan-status").classList.remove("hidden");
            startScanning(address);
        }
    } catch (error) {
        console.error("Error al interactuar con Web3Modal:", error);
    }
}

async function disconnectAndExit() {
    try {
        if (window.web3Modal) {
            // 1. Desconectar la wallet de Web3Modal
            await window.web3Modal.disconnect();

            // 2. Limpiar variables globales si es necesario
            selectedData = null;
            stopQuotePolling();

            console.log("🔌 Kashio: Wallet desconectada manualmente.");
        }

        // 3. Recargar la página para volver al estado inicial limpio
        location.reload();
    } catch (error) {
        console.error("Error al desconectar:", error);
        // Fallback: si falla la desconexión, al menos recargamos
        location.reload();
    }
}

/**
 * Selecciona un activo y comunica con Alpine.js
 */
window.selectAsset = function (address, symbol, decimals, balance, logo) {
    const paymentSection = document.getElementById("payment-section");

    // Actualizamos la variable global para que updateQuoteManual tenga los datos
    selectedData = {
        address: address,
        symbol: symbol,
        decimals: decimals,
        balance: balance,
        logo: logo,
        chainId: window.web3Modal.getChainId(),
    };

    // Disparamos el evento para Alpine
    window.dispatchEvent(
        new CustomEvent("asset-selected", {
            detail: selectedData,
        }),
    );

    console.log("✅ Activo seleccionado:", symbol);
};

window.manualRescan = function () {
    const address = window.web3Modal.getAddress();
    if (!address) return;

    // 1. Mostrar de nuevo el spinner
    document.getElementById("payment-section").classList.add("hidden");
    document.getElementById("scan-status").classList.remove("hidden");

    // 2. Lanzar escaneo
    startScanning(address);
};

/**
 * Obtiene cotización desde el backend de Kashio (deBridge)
 */
async function updateQuoteManual(isAutoRefresh = false) {
    const quoteCard = document.getElementById("quote-card");
    const btnPay = document.getElementById("btn-pay");
    const receiveTxt = document.getElementById("txt-receive-amount");
    const sendTxt = document.getElementById("txt-send-amount");

    const el = document.getElementById("payment-section");
    if (!el || !window.Alpine) return;

    const alpine = Alpine.$data(el);
    const currentAmount = parseFloat(alpine.amount);
    const balanceAvailable = parseFloat(selectedData?.balance || 0);

    // Validación de entrada
    if (
        !currentAmount ||
        currentAmount <= 0 ||
        !selectedData ||
        alpine.step !== "amount"
    ) {
        if (!isAutoRefresh) {
            if (quoteCard) quoteCard.classList.add("hidden");
            if (btnPay) btnPay.disabled = true;
        }
        stopQuotePolling();
        return;
    }

    // Validación de saldo
    if (currentAmount > balanceAvailable) {
        if (!isAutoRefresh) toastr.error(`Saldo insuficiente`);
        if (btnPay) btnPay.disabled = true;
        return;
    }

    // UI Feedback
    if (quoteCard) quoteCard.classList.remove("hidden");
    if (sendTxt) sendTxt.innerText = `${alpine.amount} ${selectedData.symbol}`;
    if (!isAutoRefresh) {
        if (receiveTxt) receiveTxt.innerText = "Calculando...";
        if (btnPay) btnPay.disabled = true;
    }

    if (!isAutoRefresh) {
        stopQuotePolling();
        startQuotePolling();
    }

    try {
        const rawAmount = ethers.utils
            .parseUnits(alpine.amount.toString(), selectedData.decimals)
            .toString();

        let tokenAddress = selectedData.address.toLowerCase();
        if (tokenAddress === "0xeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee") {
            tokenAddress = "0x0000000000000000000000000000000000000000";
        }

        const query = new URLSearchParams({
            srcChainId: selectedData.chainId,
            srcToken: tokenAddress,
            amount: rawAmount,
            dstChainId: KASHIO.destChain,
            dstToken: KASHIO.destToken,
        });

        const response = await fetch(`${KASHIO.quoteUrl}?${query.toString()}`);
        const data = await response.json();

        if (data.estimation) {
            const amt =
                data.estimation.dstChainTokenOut.recommendedAmount / 1e6;
            if (receiveTxt) receiveTxt.innerText = `${amt.toFixed(2)} USDC`;
            if (btnPay) btnPay.disabled = false;
        }
    } catch (e) {
        console.error("Error en estimación:", e);
    }
}

/**
 * Gestión del Latido (Polling)
 */
window.startQuotePolling = function () {
    if (window.quoteInterval) return;
    window.quoteInterval = setInterval(
        () => updateQuoteManual(true),
        window.QUOTE_REFRESH_TIME,
    );
};

window.stopQuotePolling = function () {
    if (window.quoteInterval) {
        clearInterval(window.quoteInterval);
        window.quoteInterval = null;
    }
};

/**
 * Ejecuta el flujo de pago
 */
async function executeSwap() {
    const el = document.getElementById("payment-section");
    if (!el || !window.Alpine) return;
    const alpine = Alpine.$data(el);
    const btnPay = document.getElementById("btn-pay");

    try {
        btnPay.disabled = true;
        btnPay.innerText = "Preparando transacción...";

        const provider = new ethers.providers.Web3Provider(
            window.web3Modal.getWalletProvider(),
        );
        const signer = provider.getSigner();
        const userAddress = await signer.getAddress();

        const rawAmount = ethers.utils
            .parseUnits(alpine.amount.toString(), selectedData.decimals)
            .toString();

        const payload = {
            srcChainId: selectedData.chainId,
            srcToken:
                selectedData.address ===
                "0xeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee"
                    ? "0x0000000000000000000000000000000000000000"
                    : selectedData.address,
            amount: rawAmount,
            dstChainId: KASHIO.destChain,
            dstToken: KASHIO.destToken,
            userWallet: userAddress,
        };

        const response = await fetch(KASHIO.createOrderUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document.querySelector(
                    'meta[name="csrf-token"]',
                )?.content,
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (!data.tx) {
            toastr.info("Orden validada (Modo Debug)");
            btnPay.disabled = false;
            return;
        }

        const txResponse = await signer.sendTransaction({
            to: data.tx.to,
            data: data.tx.data,
            value: data.tx.value ? ethers.BigNumber.from(data.tx.value) : 0,
            gasLimit: 1500000,
        });

        toastr.info("Esperando confirmación...");
        await txResponse.wait();
        alpine.step = "success";
    } catch (error) {
        console.error("🚨 Error:", error);
        alpine.errorMessage = error.message;
        alpine.step = "error";
    }
}

/**
 * Renderiza la lista de activos
 */
function renderAssetsList(assets) {
    const container = document.getElementById("assets-list-container");
    const scanStatus = document.getElementById("scan-status");
    const paymentSection = document.getElementById("payment-section");

    if (!container) return;
    container.innerHTML = "";

    if (assets.length === 0) {
        container.innerHTML = `<p class="text-center p-4">Sin activos con saldo.</p>`;
    } else {
        assets.forEach((item) => {
            const imgUrl =
                item.logoURI ||
                "https://app.debridge.com/assets/images/chain/generic.svg";
            const html = `
                <button type="button" 
                    class="list-group-item list-group-item-action d-flex align-items-center p-3 mb-2 border rounded-3 hover-bg-light"
                    onclick="selectAsset('${item.address}', '${item.symbol}', ${item.decimals}, '${item.balance}', '${imgUrl}')">
                    <img src="${imgUrl}" class="rounded-circle me-3" width="35" height="35" onerror="this.src='https://app.debridge.com/assets/images/chain/generic.svg'">
                    <div class="flex-grow-1 text-start">
                        <div class="fw-bold text-dark">${item.symbol}</div>
                        <div class="text-slate-400" style="font-size: 10px;">${item.networkName}</div>
                    </div>
                    <div class="text-end">
                        <div class="fw-black text-primary">${item.balance}</div>
                        <div class="text-slate-400" style="font-size: 9px;">DISPONIBLE</div>
                    </div>
                </button>`;
            container.insertAdjacentHTML("beforeend", html);
        });
    }

    if (scanStatus) scanStatus.classList.add("hidden");
    if (paymentSection) {
        paymentSection.classList.remove("hidden");
        if (window.Alpine) Alpine.$data(paymentSection).step = "list";
    }
}

/**
 * Escaneo de activos
 */
/**
 * Escanea activos en la red activa de la wallet.
 * Optimizado para detectar balances nativos en la primera conexión.
 * @param {string} userAddress - Dirección de la wallet del usuario.
 */
async function startScanning(userAddress) {
    const container = document.getElementById("assets-list-container");
    const statusEl = document.getElementById("current-network-scan");

    // 1. Preparación de la interfaz
    if (container) container.innerHTML = "";
    if (statusEl) statusEl.innerText = "Sincronizando con tu billetera...";

    console.log("🔍 Kashio: Iniciando escaneo para:", userAddress);
    const foundAssets = [];

    try {
        // 2. Delay de seguridad (500ms)
        // Vital para que el provider reconozca el balance nativo tras el handshake inicial
        await new Promise((resolve) => setTimeout(resolve, 500));

        // 3. Identificación de Red y Proveedor
        const walletProvider = window.web3Modal.getWalletProvider();
        if (!walletProvider)
            throw new Error("No se encontró proveedor de wallet.");

        const provider = new ethers.providers.Web3Provider(walletProvider);
        const currentChainId = window.web3Modal.getChainId();

        const networkConfig = Object.values(window.supportedChains).find(
            (n) => {
                return Number(n.chainId) === Number(currentChainId);
            },
        );

        if (!networkConfig) {
            toastr.error("Red no soportada (ID: " + currentChainId + ")");
            document.getElementById("scan-status").classList.add("hidden");
            document
                .getElementById("connect-section")
                .classList.remove("hidden");
            return;
        }

        // 4. Obtener Lista de Tokens desde la API de Kashio (deBridge)
        if (statusEl)
            statusEl.innerText = `Consultando activos en ${networkConfig.name}...`;
        const tokenResponse = await fetch(
            `${KASHIO.tokensUrl}/${currentChainId}`,
        );
        const tokenData = await tokenResponse.json();

        // Convertimos el objeto de tokens en un array para procesarlo
        const allTokens = tokenData.tokens
            ? Object.values(tokenData.tokens)
            : [];

        // 5. Escaneo de Balance Nativo (BNB, MATIC, etc.)
        // Forzamos la consulta al provider recién inicializado
        const nativeBalanceWei = await provider.getBalance(userAddress);
        const nativeBalance = ethers.utils.formatEther(nativeBalanceWei);

        if (parseFloat(nativeBalance) > 0) {
            // Buscamos el logo del nativo en la lista de deBridge para consistencia visual
            const deBridgeNative = allTokens.find(
                (t) =>
                    t.isNative ||
                    t.address === "0x0000000000000000000000000000000000000000",
            );

            foundAssets.push({
                symbol: deBridgeNative.symbol,
                balance: parseFloat(nativeBalance).toFixed(4),
                address: "0x0000000000000000000000000000000000000000",
                chainId: currentChainId,
                decimals: 18,
                logoURI: deBridgeNative
                    ? deBridgeNative.logoURI
                    : networkConfig.logo,
                networkName: networkConfig.name,
                isNative: true,
            });
        }

        // 6. Escaneo de Tokens ERC20 con Saldo
        if (allTokens.length > 0) {
            if (statusEl)
                statusEl.innerText = `Buscando saldos en ${networkConfig.name}...`;

            // Limitamos a los primeros 40 para evitar saturar el RPC en el escaneo inicial
            const filteredTokens = allTokens.slice(0, 40);

            const tokenPromises = filteredTokens.map(async (token) => {
                try {
                    // Saltamos si es el nativo (ya procesado arriba)
                    if (
                        token.isNative ||
                        token.address ===
                            "0x0000000000000000000000000000000000000000"
                    )
                        return null;

                    const contract = new ethers.Contract(
                        token.address,
                        ["function balanceOf(address) view returns (uint256)"],
                        provider,
                    );

                    const balanceWei = await contract.balanceOf(userAddress);
                    const balance = ethers.utils.formatUnits(
                        balanceWei,
                        token.decimals,
                    );

                    if (parseFloat(balance) > 0.01) {
                        return {
                            symbol: token.symbol,
                            balance: parseFloat(balance).toFixed(2),
                            address: token.address,
                            chainId: currentChainId,
                            decimals: token.decimals,
                            logoURI: token.logoURI,
                            networkName: networkConfig.name,
                            isNative: false,
                        };
                    }
                } catch (e) {
                    return null;
                }
                return null;
            });

            const tokensFound = await Promise.all(tokenPromises);
            foundAssets.push(...tokensFound.filter((t) => t !== null));
        }

        // 7. Renderizado Final
        console.log("✅ Kashio: Escaneo finalizado.", foundAssets);
        renderAssetsList(foundAssets);
    } catch (error) {
        console.error("🚨 Error crítico en startScanning:", error);
        toastr.error("No se pudieron cargar los balances.");

        // En caso de error, devolvemos al usuario a la sección de conexión
        document.getElementById("scan-status").classList.add("hidden");
        document.getElementById("connect-section").classList.remove("hidden");
    }
}
