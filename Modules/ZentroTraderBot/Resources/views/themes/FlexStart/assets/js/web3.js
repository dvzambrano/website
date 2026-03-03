/**
 * Kashio - Web3 Logic & Bridge Integration
 * Powered by Gemini 3.0 Flash
 */

// --- Estado Global ---
window.quoteInterval = null;
window.QUOTE_REFRESH_TIME = 30000;
let selectedData = null;

// --- Inicialización y Eventos de Wallet ---
if (window.ethereum) {
    window.ethereum.on("accountsChanged", () => location.reload());
    window.ethereum.on("chainChanged", () => location.reload());
}

/**
 * Conecta la wallet manualmente (si el subscribeState del modal no se dispara)
 */
async function connectAndScan() {
    if (!window.appKit) {
        toastr.warning("Inicializando conexión...");
        return;
    }

    try {
        await window.appKit.open();
        const address = window.appKit.getAddress();

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
        if (window.appKit) {
            // 1. Desconectar la wallet de Web3Modal
            await window.appKit.disconnect();

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
        chainId: window.appKit.getChainId(),
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
    const address = window.appKit.getAddress();
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
        const walletProvider = window.appKit.getWalletProvider();
        // RE-SINCRONIZACIÓN: Si estamos en móvil/Chrome, forzamos un ping al provider
        if (!walletProvider) {
            toastr.warning("Reconectando con SafePal...");
            await window.appKit.open(); // Abre el modal para refrescar la sesión si se perdió
            return;
        }

        const provider = new ethers.providers.Web3Provider(walletProvider);
        const signer = provider.getSigner();
        const userAddress = await signer.getAddress();

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
            userWallet: userAddress,
            dstWallet: KASHIO.userWallet,
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
/**
 * Ejecuta el flujo de pago completo: Cotización -> Permisos -> Envío
 */
async function executeSwap() {
    const el = document.getElementById("payment-section");
    if (!el || !window.Alpine) return;

    const alpine = Alpine.$data(el);
    const btnPay = document.getElementById("btn-pay");

    try {
        // 1. PREPARACIÓN INICIAL
        btnPay.innerText = "Preparando transacción...";
        btnPay.disabled = true;

        const walletProvider = window.appKit.getWalletProvider();
        // RE-SINCRONIZACIÓN: Si estamos en móvil/Chrome, forzamos un ping al provider
        if (!walletProvider) {
            toastr.warning("Reconectando con SafePal...");
            await window.appKit.open(); // Abre el modal para refrescar la sesión si se perdió
            return;
        }

        const provider = new ethers.providers.Web3Provider(walletProvider);
        const signer = provider.getSigner();
        const userAddress = await signer.getAddress();

        const rawAmount = ethers.utils
            .parseUnits(alpine.amount.toString(), selectedData.decimals)
            .toString();

        // 2. OBTENER ORDEN DEL BACKEND
        const payload = {
            srcChainId: selectedData.chainId,
            srcToken: selectedData.address,
            amount: rawAmount,
            dstChainId: KASHIO.destChain,
            dstToken: KASHIO.destToken,
            userWallet: userAddress,
            dstWallet: KASHIO.userWallet,
        };

        // En web3.js, dentro de executeSwap
        const response = await fetch(KASHIO.createOrderUrl, {
            method: "POST",
            mode: "cors", // Forzamos modo CORS
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                // Quitamos el X-CSRF-TOKEN aquí si ya lo pusiste en el middleware 'except'
            },
            body: JSON.stringify(payload),
        }).catch((err) => {
            //alert("Error de Red Local: " + err.message);
            throw err;
        });

        if (!response.ok) {
            const errBody = await response.json().catch(() => ({}));
            throw new Error(
                errBody.message || `Error del servidor: ${response.status}`,
            );
        }

        const data = await response.json();
        if (data.error || !data.tx)
            throw new Error(data.message || "Error al crear la orden");

        // 3. GESTIÓN DE ALLOWANCE (CORREGIDO PARA MÓVILES)
        // Evitamos el CALL_EXCEPTION si es token nativo (MATIC/BNB/ETH)
        const isNative =
            selectedData.address.toLowerCase() ===
                "0xeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee" ||
            selectedData.address.toLowerCase() ===
                "0x0000000000000000000000000000000000000000";

        if (!isNative && data.tx.allowanceTarget) {
            btnPay.innerText = "Verificando permisos...";
            btnPay.disabled = true;

            const tokenContract = new ethers.Contract(
                selectedData.address,
                [
                    "function allowance(address owner, address spender) view returns (uint256)",
                    "function approve(address spender, uint256 amount) returns (bool)",
                ],
                signer,
            );

            const currentAllowance = await tokenContract.allowance(
                userAddress,
                data.tx.allowanceTarget,
            );

            if (currentAllowance.lt(rawAmount)) {
                toastr.info("Por favor, aprueba el uso de tokens en tu wallet");
                btnPay.innerText = "Esperando aprobación...";
                btnPay.disabled = true;

                // Aprobamos el máximo para ahorrar gas en futuras operaciones
                const approveTx = await tokenContract.approve(
                    data.tx.allowanceTarget,
                    ethers.constants.MaxUint256,
                );
                await approveTx.wait();
                toastr.success("Permiso concedido");
            }
        }

        // 4. CONFIGURACIÓN DE TRANSACCIÓN Y GAS
        btnPay.innerText = "Confirmando pago...";
        btnPay.disabled = true;

        const txParams = {
            to: data.tx.to,
            data: data.tx.data,
            value: data.tx.value ? ethers.BigNumber.from(data.tx.value) : 0,
        };

        try {
            // Intentamos estimar el gas para esa transacción específica
            const estimatedGas = await provider.estimateGas({
                ...txParams,
                from: userAddress,
            });
            // Añadimos un 20% de buffer para evitar fallos por volatilidad de gas
            txParams.gasLimit = estimatedGas.mul(120).div(100);
            console.log(
                "⚓ Gas estimado con buffer:",
                txParams.gasLimit.toString(),
            );
        } catch (gasError) {
            console.warn(
                "⚠️ No se pudo estimar el gas, usando fallback:",
                gasError,
            );
            // Si falla la estimación (a veces pasa en redes congestionadas), usamos un límite seguro
            txParams.gasLimit = 1000000;
        }

        // 5. EJECUCIÓN DEL PAGO
        toastr.info("Esperando firma de la transacción...");

        // 1. Creamos el aviso de firma pendiente
        const timeoutAlert = setTimeout(() => {
            toastr.warning(
                "¿Aún no ves la firma? Asegúrate de que tu billetera esté abierta y desbloqueada.",
                "Firma pendiente",
                { timeOut: 10000 },
            );
        }, 15000);

        // 2. Esperamos la FIRMA (aquí es donde el usuario interactúa con el móvil)
        const txResponse = await signer.sendTransaction(txParams);

        // 3. ¡IMPORTANTE! Cancelamos el aviso justo aquí, porque ya firmó.
        clearTimeout(timeoutAlert);

        // 4. Actualizamos UI y esperamos la CONFIRMACIÓN en la blockchain
        btnPay.innerText = "Procesando en blockchain...";
        btnPay.disabled = true;

        await txResponse.wait();

        // Notificación final con link al explorador
        const explorerUrl = window.networkConfig
            ? `${window.networkConfig.explorers[0].url}/tx/${txResponse.hash}`
            : "#";

        toastr.success(
            `Tu depósito ha sido confirmado.<br>
            <a href="${explorerUrl}" target="_blank" style="color: #fff; text-decoration: underline; font-weight: bold;">
                <i class="fas fa-external-link-alt me-1"></i> Ver en el explorador
            </a>`,
            "¡Depósito Exitoso!",
            {
                timeOut: 0, // No se cierra automáticamente
                extendedTimeOut: 0, // No se cierra al pasar el mouse
                closeButton: true, // Permite al usuario cerrarlo manualmente
                tapToDismiss: false, // Evita que se cierre al hacer clic accidentalmente fuera
                progressBar: false, // No hace falta barra de tiempo si es permanente
            },
        );

        alpine.step = "success";
    } catch (error) {
        console.error("🚨 Error Crítico:", error);

        // --- LOGGER PARA MÓVIL ---
        let errorDiag = "--- DIAGNÓSTICO DE ERROR ---\n";
        errorDiag += `Mensaje: ${error.message}\n`;

        if (error.response) {
            // El servidor respondió con un código de error (4xx, 5xx)
            errorDiag += `Status: ${error.response.status}\n`;
            const body = await error.response.text();
            errorDiag += `Respuesta: ${body.substring(0, 100)}...\n`;
        } else if (error.request) {
            // La petición se hizo pero no hubo respuesta (CORS o Red)
            errorDiag += `Tipo: Error de Red / CORS / Timeout\n`;
            errorDiag += `URL intentada: ${KASHIO.createOrderUrl}\n`;
        } else {
            // Error al configurar la petición o error de Ethers
            errorDiag += `Código: ${error.code || "N/A"}\n`;
            errorDiag += `Stack: ${error.stack ? error.stack.substring(0, 150) : "N/A"}\n`;
        }

        // Mostrar el alert solo si estamos en desarrollo/prueba
        // alert(errorDiag);
        // -------------------------

        let friendly = error.message;
        if (error.code === "ACTION_REJECTED" || error.code === 4001)
            friendly = "Cancelaste la operación.";
        else if (friendly.includes("insufficient funds"))
            friendly = "No tienes suficiente para el gas.";

        alpine.errorMessage = friendly;
        const detailEl = document.getElementById("error-detail-text");
        if (detailEl) detailEl.innerText = error.stack || friendly;

        alpine.step = "error";
        btnPay.disabled = false;
        btnPay.innerText = "Confirmar y Pagar";
    }
}

/**
 * Renderiza la lista de activos detectados y actualiza la info de la red.
 * @param {Array} assets - Lista de objetos de activos con balance.
 */
function renderAssetsList(assets) {
    const container = document.getElementById("assets-list-container");
    const networkDisplay = document.getElementById("display-network-name");
    const paymentSection = document.getElementById("payment-section");

    if (!container) return;

    container.innerHTML = "";

    // Actualizar nombre de red en la UI usando window.supportedChains
    if (networkDisplay && window.appKit) {
        const currentChainId = window.appKit.getChainId();
        // Acceso directo por ID en el objeto
        networkDisplay.innerText = window.networkConfig
            ? window.networkConfig.name
            : `Red ID: ${currentChainId}`;
    }

    if (!assets || assets.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5">
                <i class="fas fa-coins text-slate-200 mb-3" style="font-size: 40px;"></i>
                <p class="text-slate-500 small">No se encontraron activos con saldo suficiente.</p>
            </div>`;
    } else {
        assets.sort((a, b) => parseFloat(b.balance) - parseFloat(a.balance));

        assets.forEach((item) => {
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
                    </div>

                    <div class="flex-grow-1 text-start ms-3">
                        <div class="fw-bold text-dark" style="font-size: 14px;">${item.symbol}</div>
                        <div class="text-slate-400" style="font-size: 10px; text-transform: uppercase;">
                            ${item.networkName || "Token"}
                        </div>
                    </div>

                    <div class="text-end">
                        <div class="fw-black text-primary" style="font-size: 15px;">${item.balance}</div>
                        <div class="text-slate-400 fw-bold" style="font-size: 9px;">DISPONIBLE</div>
                    </div>
                </button>`;
            container.insertAdjacentHTML("beforeend", html);
        });
    }

    if (paymentSection && window.Alpine) {
        Alpine.$data(paymentSection).step = "list";
    }
}

/**
 * Escanea activos en la red activa de la wallet.
 * @param {string} userAddress - Dirección de la wallet del usuario.
 * @param {number|string} forcedChainId - (Opcional) ID de red pasado desde el evento de conexión.
 */
async function startScanning(userAddress, forcedChainId = null) {
    if (typeof ethers === "undefined") {
        console.error("🚨 Ethers no detectado");
        return;
    }

    const currentChainId =
        forcedChainId || (window.appKit ? window.appKit.getChainId() : null);
    console.log(`🔍 Escaneando Red ${currentChainId} para: ${userAddress}`);

    window.networkConfig = Object.values(window.supportedChains).find(
        (n) => Number(n.chainId) === Number(currentChainId),
    );
    console.log(
        `🔍 Datos de la red: `,
        window.networkConfig,
        window.supportedChains,
    );
    if (!window.networkConfig) {
        console.error(
            "❌ Red no soportada en supportedChains:",
            currentChainId,
        );
        if (typeof toastr !== "undefined")
            toastr.error("Red no configurada en DeBridge.");
        const el = document.getElementById("payment-section");
        if (el && window.Alpine) Alpine.$data(el).step = "connect";
        return;
    }

    const statusEl = document.getElementById("current-network-scan");
    const container = document.getElementById("assets-list-container");
    if (container) container.innerHTML = "";
    if (statusEl)
        statusEl.innerText = `Consultando ${window.networkConfig.name}...`;

    const foundAssets = [];

    try {
        // 1. Llamamos a tu nuevo endpoint (Asegúrate de que la ruta coincida con tu API)
        // Pasamos la dirección del usuario, el chainId y el networkKey (ej: 'POL')
        const response = await fetch(
            `${KASHIO.tokensUrl}/${userAddress}/${window.networkConfig.chainId}/${window.networkConfig.chain}`,
        );

        if (!response.ok)
            throw new Error("Error consultando balances en Kashio");

        const portfolio = await response.json();

        // 2. Mapeamos los resultados al formato que espera tu UI
        const results = portfolio.map((token) => {
            return {
                ...token,
                // Mantener consistencia con el resto de tu appKit
                balance: parseFloat(token.balance).toFixed(4),
                chainId: currentChainId,
                networkName: window.networkConfig.name,
                isNative: token.is_native || false,
            };
        });

        // 3. Agregamos todo a la lista de activos encontrados
        foundAssets.push(...results);

        renderAssetsList(foundAssets);
    } catch (error) {
        console.error("🚨 Error startScanning:", error);
    }
}

/**
 * Intenta obtener un balance probando varios RPCs si el principal falla.
 */
async function getBalanceWithFallback(address) {
    // 1. Intentar primero con el proveedor de la wallet (lo más rápido)
    try {
        const walletProvider = new ethers.providers.Web3Provider(
            window.appKit.getWalletProvider(),
        );
        return await walletProvider.getBalance(address);
    } catch (e) {
        console.warn(
            "⚠️ Proveedor de wallet falló, intentando RPCs alternativos...",
        );
    }

    // 2. Filtrar los RPCs para NO reintentar el principal que ya falló
    // window.networkConfig.rpcUrl es el que Web3Modal intentó usar primero
    const fallbackRpcs = (window.networkConfig.allRpcs || []).filter(
        (url) => url !== window.networkConfig.rpcUrl,
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
