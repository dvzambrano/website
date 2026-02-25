/**
 * Kashio - Web3 Logic & Bridge Integration
 * Powered by Gemini 2.0 Flash
 */

// --- Estado Global ---
window.quoteInterval = null;
window.QUOTE_REFRESH_TIME = 15000;
let selectedData = null;

// --- Inicialización y Eventos de Wallet ---
if (window.ethereum) {
    // Recargar si cambia la cuenta
    window.ethereum.on("accountsChanged", () => location.reload());

    // Escaneo automático si cambia la red
    window.ethereum.on("chainChanged", () => {
        console.log("🔄 Cambio de red detectado, re-escaneando...");
        connectAndScan();
    });
}

/**
 * Conecta la wallet y escanea SOLO la red activa en MetaMask
 */
async function connectAndScan() {
    if (!window.ethereum) {
        toastr.warning("Por favor, instala MetaMask para continuar.");
        return;
    }

    const btnConnect = document.getElementById("connect-section");
    const scanStatus = document.getElementById("scan-status");
    const paymentSection = document.getElementById("payment-section");
    const currentNetTxt = document.getElementById("current-network-scan");
    const listContainer = document.getElementById("assets-list-container");

    // UI Reset
    if (btnConnect) btnConnect.classList.add("hidden");
    if (scanStatus) scanStatus.classList.remove("hidden");
    if (paymentSection) paymentSection.classList.add("hidden");
    if (listContainer) listContainer.innerHTML = "";

    try {
        const provider = new ethers.providers.Web3Provider(window.ethereum);
        await provider.send("eth_requestAccounts", []);
        const { chainId } = await provider.getNetwork();
        const signer = provider.getSigner();
        const userAddress = await signer.getAddress();

        // Identificar la red en nuestra configuración KASHIO.web3
        const netKey = Object.keys(KASHIO.web3).find(
            (key) => KASHIO.web3[key].chain_id == chainId,
        );

        if (!netKey) {
            const netName =
                KASHIO.chains[chainId]?.name || `Chain ID: ${chainId}`;
            toastr.error(`La red ${netName} no está soportada actualmente.`);
            if (scanStatus) scanStatus.classList.add("hidden");
            if (btnConnect) btnConnect.classList.remove("hidden");
            return;
        }

        if (currentNetTxt)
            currentNetTxt.innerText = `📡 Escaneando tokens en ${netKey}...`;

        const netInfo = KASHIO.web3[netKey];

        // Escanear tokens definidos para esta red activa
        for (const [symbol, token] of Object.entries(netInfo.tokens)) {
            try {
                let balance;
                const isNative =
                    token.address.toLowerCase() ===
                        "0xeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee" ||
                    token.address.toLowerCase() ===
                        "0x0000000000000000000000000000000000000000";

                if (isNative) {
                    balance = await provider.getBalance(userAddress);
                } else {
                    const contract = new ethers.Contract(
                        token.address,
                        ["function balanceOf(address) view returns (uint256)"],
                        provider,
                    );
                    balance = await contract.balanceOf(userAddress);
                }

                if (balance.gt(0)) {
                    const formatted = ethers.utils.formatUnits(
                        balance,
                        token.decimals,
                    );

                    const assetData = {
                        symbol,
                        network: netKey,
                        balance: formatted,
                        rawBalance: balance.toString(),
                        logo: KASHIO.chains[chainId]?.logoURI || "",
                        address: token.address,
                        chainId: chainId,
                        decimals: token.decimals,
                    };

                    renderAssetItem(assetData, listContainer);
                }
            } catch (err) {
                console.error(`Error en token ${symbol}:`, err);
            }
        }

        if (scanStatus) scanStatus.classList.add("hidden");
        if (listContainer.children.length > 0) {
            if (paymentSection) paymentSection.classList.remove("hidden");
        } else {
            toastr.info(`No se detectó saldo en ${netKey}.`);
            if (btnConnect) btnConnect.classList.remove("hidden");
        }
    } catch (err) {
        console.error("🚨 Error crítico:", err);
        if (scanStatus) scanStatus.classList.add("hidden");
        if (btnConnect) btnConnect.classList.remove("hidden");
        toastr.error("Error al conectar con la wallet.");
    }
}

/**
 * Renderiza un item en la lista de activos
 */
function renderAssetItem(data, container) {
    const item = document.createElement("div");
    item.className =
        "list-group-item d-flex align-items-center py-3 border-0 mb-2 shadow-sm rounded-4 cursor-pointer hover-bg-light";
    item.style.cursor = "pointer";

    item.onclick = () => {
        selectedData = data;
        // Notificar a Alpine
        window.dispatchEvent(
            new CustomEvent("asset-selected", { detail: data }),
        );
        const quoteCard = document.getElementById("quote-card");
        if (quoteCard) quoteCard.classList.add("hidden");
    };

    item.innerHTML = `
        <div class="me-3" style="background: #f8fafc; padding: 10px; border-radius: 12px;">
            <img src="${data.logo}" style="width: 24px; height: 24px;">
        </div>
        <div class="flex-grow-1 text-start">
            <h6 class="mb-0 fw-bold">${data.symbol}</h6>
            <small class="text-muted">${data.network}</small>
        </div>
        <div class="text-end">
            <span class="fw-bold text-primary">${parseFloat(data.balance).toFixed(4)}</span>
        </div>
    `;
    container.appendChild(item);
}

/**
 * Obtiene cotización desde el backend de Kashio (deBridge)
 */
async function updateQuoteManual(isAutoRefresh = false) {
    const quoteCard = document.getElementById("quote-card");
    const btnPay = document.getElementById("btn-pay");
    const receiveTxt = document.getElementById("txt-receive-amount");
    const sendTxt = document.getElementById("txt-send-amount");
    const amountInputEl = document.querySelector('input[x-model="amount"]');

    const el = document.getElementById("payment-section");
    if (!el || !window.Alpine) return;

    const alpine = window.Alpine.$data(el);
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
        if (!isAutoRefresh)
            toastr.error(
                `Saldo insuficiente (${selectedData.balance} disponible)`,
            );
        if (btnPay) btnPay.disabled = true;
        stopQuotePolling();
        return;
    }

    // UI Feedback
    if (quoteCard) quoteCard.classList.remove("hidden");
    if (sendTxt) sendTxt.innerText = `${alpine.amount} ${selectedData.symbol}`;
    if (receiveTxt && !isAutoRefresh) receiveTxt.innerText = "Calculando...";
    if (btnPay) btnPay.disabled = true;
    if (amountInputEl) amountInputEl.disabled = true;

    if (!isAutoRefresh) {
        stopQuotePolling();
        startQuotePolling();
    }

    try {
        const rawAmount = ethers.utils
            .parseUnits(alpine.amount.toString(), selectedData.decimals)
            .toString();

        // Saneamiento de dirección nativa para la API (0xeee... -> 0x000...)
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
                data.estimation.dstChainTokenOut.recommendedAmount / 1e6; // USDC Polygon
            if (receiveTxt) {
                receiveTxt.innerText = `${amt.toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 6,
                })} USDC`;
            }
            if (btnPay) btnPay.disabled = false;
        }
    } catch (e) {
        console.error("Error en estimación:", e);
        if (!isAutoRefresh && receiveTxt)
            receiveTxt.innerText = "Error de conexión";
    } finally {
        if (amountInputEl) amountInputEl.disabled = false;
        if (!isAutoRefresh && amountInputEl) amountInputEl.focus();
    }
}

/**
 * Gestión del Latido (Polling)
 */
function startQuotePolling() {
    if (window.quoteInterval) return;
    console.log("⏱️ Latido Kashio iniciado.");
    window.quoteInterval = setInterval(
        () => updateQuoteManual(true),
        window.QUOTE_REFRESH_TIME,
    );
}

function stopQuotePolling() {
    if (window.quoteInterval) {
        console.log("🛑 Latido Kashio detenido.");
        clearInterval(window.quoteInterval);
        window.quoteInterval = null;
    }
}

/**
 * Ejecuta el flujo de pago:
 * 1. Envía datos al servidor (POST).
 * 2. Recibe el objeto de transacción.
 * 3. Dispara MetaMask para la firma.
 */
async function executeSwap() {
    const el = document.getElementById("payment-section");
    if (!el || !window.Alpine) return;

    const alpine = window.Alpine.$data(el);
    const btnPay = document.getElementById("btn-pay");

    // 1. Validaciones de interfaz
    if (!selectedData || !alpine.amount || parseFloat(alpine.amount) <= 0) {
        toastr.error("Por favor, ingresa un monto válido.");
        return;
    }

    try {
        // Bloqueo de UI para evitar doble clic
        btnPay.disabled = true;
        btnPay.innerText = "Preparando transacción...";

        const provider = new ethers.providers.Web3Provider(window.ethereum);
        const signer = provider.getSigner();

        // Obtenemos la dirección activa del usuario que firma
        const userAddress = await signer.getAddress();

        // 2. Preparar el monto en unidades mínimas (BigInt/Wei)
        const rawAmount = ethers.utils
            .parseUnits(alpine.amount.toString(), selectedData.decimals)
            .toString();

        // 3. Normalizar dirección nativa para la API (0xeee... -> 0x000...)
        let tokenAddress = selectedData.address.toLowerCase();
        if (tokenAddress === "0xeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee") {
            tokenAddress = "0x0000000000000000000000000000000000000000";
        }

        // 4. Preparar Payload y Token CSRF de Laravel
        const csrfToken = document.querySelector(
            'meta[name="csrf-token"]',
        )?.content;

        const payload = {
            srcChainId: selectedData.chainId,
            srcToken: tokenAddress,
            amount: rawAmount,
            dstChainId: KASHIO.destChain, // Generalmente 137 (Polygon)
            dstToken: KASHIO.destToken, // USDC Address en Polygon
            userWallet: userAddress, // Quien firma
        };

        toastr.info("Solicitando orden de pago...");

        // 5. Petición POST a tu controlador de Laravel
        const response = await fetch(KASHIO.createOrderUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": csrfToken,
            },
            body: JSON.stringify(payload),
        });

        // Manejo de errores del servidor (HTML vs JSON)
        if (!response.ok) {
            const errorData = await response.json().catch(() => null);
            throw new Error(
                errorData?.message ||
                    "Error en el servidor de Kashio (Verifica la ruta POST).",
            );
        }

        const data = await response.json();

        // DEBUG: Si aún estás devolviendo $params para probar, veremos esto en consola:
        if (!data.tx) {
            console.log(
                "🐞 Modo Debug - Parámetros generados en Laravel:",
                data,
            );
            toastr.success(
                "Parámetros validados correctamente en el servidor.",
            );
            btnPay.disabled = false;
            btnPay.innerText = "Confirmar y Pagar";
            return;
        }

        // Busca el punto 6 en tu código y cámbialo por esto:
        // 6. Firma de la Transacción real
        toastr.warning("Confirma la transacción en tu MetaMask...");
        // Preparamos el objeto de transacción con BigNumber para ethers v5
        const txParams = {
            to: data.tx.to,
            data: data.tx.data,
            value: data.tx.value
                ? ethers.BigNumber.from(data.tx.value)
                : ethers.BigNumber.from(0),
            // Forzamos un límite de gas para evitar que falle la estimación inicial
            // 1,500,000 es un estándar seguro para swaps cross-chain de deBridge
            gasLimit: ethers.BigNumber.from(1500000),
        };

        console.log("🚀 Enviando a MetaMask:", txParams);

        const txResponse = await signer.sendTransaction(txParams);

        // 7. Espera de confirmación en la Blockchain
        btnPay.innerText = "Confirmando en Red...";
        toastr.info("Transacción enviada. Esperando validación...");

        const receipt = await txResponse.wait();

        if (receipt.status === 1) {
            toastr.success("¡Depósito realizado con éxito!", "Kashio");
            // Cambiar a paso final en el asistente de Alpine
            if (alpine.step) alpine.step = "success";
        } else {
            throw new Error("La transacción fue revertida por la red.");
        }
    } catch (error) {
        console.error("🚨 Error en executeSwap:", error);

        btnPay.disabled = false;
        btnPay.innerText = "Confirmar y Pagar";

        // Personalizamos el mensaje según el error
        if (error.code === 4001) {
            alpine.errorMessage =
                "Transacción cancelada: Has rechazado la firma en MetaMask.";
        } else if (error.message.includes("insufficient funds")) {
            alpine.errorMessage =
                "Saldo insuficiente: No tienes suficiente BNB/GAS para pagar la comisión.";
        } else {
            alpine.errorMessage =
                "No pudimos procesar la orden. Por favor, intenta más tarde.";
        }

        // Cambiamos el detalle técnico en el cuadro pequeño
        document.getElementById("error-detail-text").innerText =
            error.message.substring(0, 100) + "...";

        // Saltamos a la vista de error
        alpine.step = "error";

        toastr.error("La operación fue cancelada o falló.");
    }
}
