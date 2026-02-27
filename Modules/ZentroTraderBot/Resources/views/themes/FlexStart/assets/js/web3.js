/**
 * Kashio - Web3 Logic & Bridge Integration
 * Powered by Gemini 3.0 Flash
 */

// --- Estado Global ---
window.quoteInterval = null;
window.QUOTE_REFRESH_TIME = 30000;
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

    const paymentSection = document.getElementById("payment-section");
    if (paymentSection) {
        Alpine.$data(paymentSection).step = "scanning"; // Volver al spinner
    }

    startScanning(address);
};

/**
 * Limpia la interfaz de cotización y resetea el botón de pago
 */
window.clearQuoteUI = function () {
    const el = document.getElementById("payment-section");
    if (el && window.Alpine) {
        // Accedemos a los datos de Alpine y reseteamos la variable 'amount'
        Alpine.$data(el).amount = "";
    }

    const quoteCard = document.getElementById("quote-card");
    const receiveTxt = document.getElementById("txt-receive-amount");
    const btnPay = document.getElementById("btn-pay");

    if (quoteCard) quoteCard.classList.add("hidden");
    if (receiveTxt) receiveTxt.innerText = "Calculando...";
    if (btnPay) {
        btnPay.disabled = true;
        btnPay.innerText = "Confirmar y Pagar";
    }

    // Detenemos el polling para no gastar recursos
    stopQuotePolling();
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

        // 2. Si el backend responde con error (como el Error 25)
        if (data.error || data.errorCode) {
            throw new Error(
                data.errorMessage || data.error || "Error en cotización",
            );
        }

        if (data.estimation) {
            const amountOut =
                data.estimation.dstChainTokenOut.recommendedAmount;
            const decimalsOut = data.estimation.dstChainTokenOut.decimals || 6;
            const amt = amountOut / Math.pow(10, decimalsOut);
            if (receiveTxt) receiveTxt.innerText = `${amt} USD`;
            if (btnPay) btnPay.disabled = false;
        }
    } catch (e) {
        console.error("🚨 Error en estimación:", e.message);

        // 3. BLINDAJE: Si falla, dejamos de decir "Calculando..."
        receiveTxt.innerText = "No disponible";
        if (btnPay) btnPay.disabled = true;

        // Si no es un refresco automático, avisamos al usuario con un Toast
        if (!isAutoRefresh) {
            toastr.error("No se pudo obtener la tasa de cambio");
        }

        // Detenemos el polling porque si falló una vez por lógica (mismo chain), fallará siempre
        stopQuotePolling();
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

        if (data.error) throw new Error(data.errorMessage);

        toastr.info("Esperando confirmación...");

        const txResponse = await signer.sendTransaction({
            to: data.tx.to,
            data: data.tx.data,
            value: data.tx.value ? ethers.BigNumber.from(data.tx.value) : 0,
            gasLimit: 1500000,
        });

        await txResponse.wait();
        alpine.step = "success";
    } catch (error) {
        console.error("🚨 Error:", error);

        // Si el usuario rechazó la firma, ponemos un mensaje amigable
        let friendlyMessage = error.message;
        if (error.code === "ACTION_REJECTED") {
            friendlyMessage =
                "Cancelaste la firma de la transacción en tu wallet.";
        }

        alpine.errorMessage =
            "No pudimos procesar la orden. Por favor intente más tarde.";
        document.getElementById("error-detail-text").innerText =
            friendlyMessage;

        alpine.step = "error";
        clearQuoteUI();
    }
}

/**
 * Renderiza la lista de activos detectados y actualiza la info de la red.
 * @param {Array} assets - Lista de objetos de activos con balance.
 */
function renderAssetsList(assets) {
    const container = document.getElementById("assets-list-container");
    const scanStatus = document.getElementById("scan-status");
    const paymentSection = document.getElementById("payment-section");
    const networkDisplay = document.getElementById("display-network-name");

    if (!container) return;

    // 1. Limpiar el contenedor de activos
    container.innerHTML = "";

    // 2. Actualizar el nombre de la red conectada en el selector inferior
    if (networkDisplay && window.web3Modal) {
        const currentChainId = window.web3Modal.getChainId();
        const networkConfig = window.supportedChains.find(
            (n) => Number(n.chainId) === Number(currentChainId),
        );
        networkDisplay.innerText = networkConfig
            ? networkConfig.name
            : "Red no detectada";
    }

    // 3. Renderizar la lista de activos o mensaje de vacío
    if (assets.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5">
                <i class="fas fa-coins text-slate-200 mb-3" style="font-size: 40px;"></i>
                <p class="text-slate-500 small">No se encontraron activos en esta red.</p>
            </div>`;
    } else {
        assets.forEach((item) => {
            // Asegurar un logo por defecto si falla el de deBridge
            const imgUrl =
                item.logoURI ||
                "https://app.debridge.com/assets/images/chain/generic.svg";

            const html = `
                <button type="button" 
                    class="list-group-item list-group-item-action d-flex align-items-center p-3 mb-2 border rounded-3 hover-bg-light"
                    onclick="selectAsset('${item.address}', '${item.symbol}', ${item.decimals}, '${item.balance}', '${imgUrl}')">
                    
                    <div class="position-relative">
                        <img src="${imgUrl}" class="rounded-circle border" width="40" height="40" 
                             onerror="this.src='https://app.debridge.com/assets/images/chain/generic.svg'">
                        <div class="position-absolute bottom-0 end-0" style="padding: 2px;">
                            <i class="fas fa-check-circle text-success" style="font-size: 10px;"></i>
                        </div>
                    </div>

                    <div class="flex-grow-1 text-start ms-3">
                        <div class="fw-bold text-dark" style="font-size: 14px;">${item.symbol}</div>
                        <div class="text-slate-400" style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px;">
                            ${item.networkName}
                        </div>
                    </div>

                    <div class="text-end">
                        <div class="fw-black text-primary" style="font-size: 15px;">${item.balance}</div>
                        <div class="text-slate-400 fw-bold" style="font-size: 9px; letter-spacing: 1px;">DISPONIBLE</div>
                    </div>
                </button>`;
            container.insertAdjacentHTML("beforeend", html);
        });
    }

    // 4. Gestión de visibilidad con transiciones
    if (paymentSection && window.Alpine) {
        // CAMBIAMOS DE 'SCANNING' A 'LIST' AUTOMÁTICAMENTE
        Alpine.$data(paymentSection).step = "list";
    }

    console.log(`✅ Renderizado: ${assets.length} activos mostrados.`);
}

/**
 * Escaneo de activos
 */
/**
 * Escanea activos en la red activa de la wallet.
 * Optimizado para detectar balances nativos en la primera conexión.
 * @param {string} userAddress - Dirección de la wallet del usuario.
 */
/**
 * Escanea activos en la red activa de la wallet.
 * @param {string} userAddress - Dirección de la wallet del usuario.
 * @param {number|string} forcedChainId - (Opcional) ID de red pasado desde el evento de conexión.
 */
async function startScanning(userAddress, forcedChainId = null) {
    const container = document.getElementById("assets-list-container");
    const statusEl = document.getElementById("current-network-scan");

    // 1. Preparación de la interfaz
    if (container) container.innerHTML = "";
    if (statusEl) statusEl.innerText = "Sincronizando con tu billetera...";

    // Determinamos el ChainID real (priorizando el que viene del evento)
    const currentChainId =
        forcedChainId ||
        (window.web3Modal ? window.web3Modal.getChainId() : null);

    console.log(
        `🔍 Kashio: Iniciando escaneo en red ${currentChainId} para:`,
        userAddress,
    );
    const foundAssets = [];

    try {
        // 2. Delay de seguridad (500ms) - Clave para evitar errores de handshake
        await new Promise((resolve) => setTimeout(resolve, 500));

        // 4. Validar configuración de red
        const networkConfig = Object.values(window.supportedChains).find(
            (n) => Number(n.chainId) === Number(currentChainId),
        );

        if (!networkConfig) {
            console.error("❌ Red no soportada:", currentChainId);
            toastr.error("La red conectada no está configurada en Kashio.");
            document.getElementById("scan-status").classList.add("hidden");
            document
                .getElementById("connect-section")
                .classList.remove("hidden");
            return;
        }

        // 3. Identificación de Proveedor
        const walletProvider = window.web3Modal.getWalletProvider();
        if (!walletProvider)
            throw new Error("No se encontró proveedor de wallet.");
        const provider = new ethers.providers.Web3Provider(walletProvider);
        // VALIDACIÓN CRUCIAL:
        const network = await provider.getNetwork();
        if (Number(network.chainId) !== Number(currentChainId)) {
            console.error(
                "Mismatched Networks!",
                network.chainId,
                currentChainId,
            );
            // Si no coinciden, forzamos el uso del RPC oficial de nuestra configuración
            // para evitar que el provider use el nodo equivocado
            const rpcUrl = networkConfig.rpc[0];
            provider = new ethers.providers.JsonRpcProvider(rpcUrl);
        }

        // 5. Obtener Lista de Tokens desde tu Backend (que consulta deBridge)
        if (statusEl)
            statusEl.innerText = `Consultando activos en ${networkConfig.name}...`;

        const tokenResponse = await fetch(
            `${KASHIO.tokensUrl}/${currentChainId}`,
        );
        const tokenData = await tokenResponse.json();
        const allTokens = tokenData.tokens
            ? Object.values(tokenData.tokens)
            : [];

        // 6. Escaneo de Balance Nativo (BNB, MATIC, ETH...)
        try {
            // USAMOS LA FUNCIÓN DE FALLBACK
            const nativeBalanceWei = await getBalanceWithFallback(
                userAddress,
                networkConfig,
            );
            const nativeBalance = ethers.utils.formatEther(nativeBalanceWei);

            if (parseFloat(nativeBalance) > 0) {
                // Buscamos info del nativo en la lista de tokens para el logo
                const deBridgeNative = allTokens.find(
                    (t) =>
                        t.isNative ||
                        t.address ===
                            "0x0000000000000000000000000000000000000000",
                );

                foundAssets.push({
                    symbol: deBridgeNative
                        ? deBridgeNative.symbol
                        : networkConfig.currency,
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
        } catch (nativeErr) {
            console.error("🚨 Agotados todos los RPCs:", nativeErr);
        }

        // 7. Escaneo de Tokens ERC20 (Filtrados con saldo)
        if (allTokens.length > 0) {
            if (statusEl)
                statusEl.innerText = `Buscando saldos en ${networkConfig.name}...`;

            // DETERMINAR QUÉ PROVIDER USAR:
            // Si el principal ya falló (lo sabemos porque tuvimos que usar el fallback para el nativo),
            // creamos un provider usando el nodo que sí funcionó.
            let activeProvider;
            try {
                activeProvider = new ethers.providers.Web3Provider(
                    window.web3Modal.getWalletProvider(),
                );
                await activeProvider.getNetwork(); // Test de vida
            } catch (e) {
                console.log(
                    "🛠️ Usando nodo de respaldo para escaneo de tokens...",
                );
                // Usamos el rpcUrl que ya sabemos que funciona (podemos guardarlo en una variable global si quieres)
                // O simplemente usamos el primero de la lista de respaldo que no sea el fallido
                const fallbackUrl = networkConfig.allRpcs.find(
                    (url) => url !== networkConfig.rpcUrl,
                );
                activeProvider = new ethers.providers.JsonRpcProvider(
                    fallbackUrl,
                );
            }

            const filteredTokens = allTokens.slice(0, 40);
            const tokenPromises = filteredTokens.map(async (token) => {
                if (
                    token.isNative ||
                    token.address ===
                        "0x0000000000000000000000000000000000000000"
                )
                    return null;

                try {
                    // USAR EL PROVIDER ACTIVO (Sea el de la wallet o el de respaldo)
                    const contract = new ethers.Contract(
                        token.address,
                        ["function balanceOf(address) view returns (uint256)"],
                        activeProvider,
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
                } catch (e) {}
                return null;
            });

            const tokensFound = await Promise.all(tokenPromises);
            foundAssets.push(...tokensFound.filter((t) => t !== null));
        }

        // 8. Renderizado Final
        console.log(
            `✅ Kashio: Escaneo finalizado. Activos encontrados: ${foundAssets.length}`,
        );
        renderAssetsList(foundAssets);
    } catch (error) {
        console.error("🚨 Error crítico en startScanning:", error);
        toastr.error("No se pudieron cargar los balances.");

        // Volver al estado inicial si falla
        document.getElementById("scan-status").classList.add("hidden");
        document.getElementById("connect-section").classList.remove("hidden");
    }
}

/**
 * Intenta obtener un balance probando varios RPCs si el principal falla.
 */
async function getBalanceWithFallback(address, networkConfig) {
    // 1. Intentar primero con el proveedor de la wallet (lo más rápido)
    try {
        const walletProvider = new ethers.providers.Web3Provider(
            window.web3Modal.getWalletProvider(),
        );
        return await walletProvider.getBalance(address);
    } catch (e) {
        console.warn(
            "⚠️ Proveedor de wallet falló, intentando RPCs alternativos...",
        );
    }

    // 2. Filtrar los RPCs para NO reintentar el principal que ya falló
    // networkConfig.rpcUrl es el que Web3Modal intentó usar primero
    const fallbackRpcs = (networkConfig.allRpcs || []).filter(
        (url) => url !== networkConfig.rpcUrl,
    );

    console.log(`🔍 Probando ${fallbackRpcs.length} nodos de respaldo...`);

    // 3. Iteramos por los RPCs secundarios
    for (const rpcUrl of fallbackRpcs) {
        try {
            const fallbackProvider = new ethers.providers.JsonRpcProvider(
                rpcUrl,
            );

            // Timeout de 3.5s para no penalizar la UX
            const balance = await Promise.race([
                fallbackProvider.getBalance(address),
                new Promise((_, reject) =>
                    setTimeout(() => reject(new Error("Timeout")), 3500),
                ),
            ]);

            console.log(`✅ Nodo recuperado con éxito: ${rpcUrl}`);
            return balance;
        } catch (err) {
            console.warn(`❌ Nodo fallido: ${rpcUrl}`, err.message);
            // Seguimos al siguiente en el loop
        }
    }

    throw new Error("No se pudo conectar con ningún nodo de respaldo.");
}
