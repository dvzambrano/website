/**
 * Kashio - Global State
 */
// Aseguramos que las variables existan en el objeto window
window.quoteInterval = window.quoteInterval || null;
window.QUOTE_REFRESH_TIME = 15000;

/**
 * Detiene el refresco automático
 */
function stopQuotePolling() {
    if (window.quoteInterval) {
        console.log("🛑 Deteniendo auto-refresco.");
        clearInterval(window.quoteInterval);
        window.quoteInterval = null;
    }
}

/**
 * Inicia el refresco automático
 */
function startQuotePolling() {
    if (window.quoteInterval) return;

    console.log("⏱️ Iniciando auto-refresco...");
    window.quoteInterval = setInterval(() => {
        updateQuoteManual(true);
    }, window.QUOTE_REFRESH_TIME);
}

/**
 * Función principal de cotización
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

    // VALIDACIÓN: ¿Es un número válido? ¿Es mayor a 0? ¿Es menor o igual al saldo?
    const balanceAvailable = parseFloat(selectedData.balance);
    const isValidAmount =
        currentAmount > 0 && currentAmount <= balanceAvailable;

    if (!isValidAmount || alpine.step !== "amount") {
        if (!isAutoRefresh) {
            if (quoteCard) quoteCard.classList.add("hidden");
            if (btnPay) btnPay.disabled = true;

            // Mostrar error visual si se pasa del balance
            if (currentAmount > balanceAvailable) {
                toastr.error(
                    `Saldo insuficiente. Tu máximo es ${selectedData.balance}`,
                );
            }
        }
        stopQuotePolling();
        return;
    }

    // Si el monto es válido, procedemos
    if (quoteCard) quoteCard.classList.remove("hidden");
    if (sendTxt) sendTxt.innerText = `${alpine.amount} ${selectedData.symbol}`;

    if (receiveTxt) receiveTxt.innerText = "Calculando...";
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

        let tokenAddress = selectedData.address.toLowerCase();
        const NATIVE_ADDR = "0xeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee";
        const ZERO_ADDR = "0x0000000000000000000000000000000000000000";

        if (tokenAddress === NATIVE_ADDR) {
            tokenAddress = ZERO_ADDR;
        }

        // 2. Construir la query con la dirección saneada
        const query = new URLSearchParams({
            srcChainId: selectedData.chainId,
            srcToken: tokenAddress, // <--- Usamos la variable saneada
            amount: rawAmount,
            dstChainId: KASHIO.destChain,
            dstToken: KASHIO.destToken,
        });

        console.log(
            `📡 Solicitando cotización para ${selectedData.symbol} (${tokenAddress})...`,
        );

        const response = await fetch(`${KASHIO.quoteUrl}?${query.toString()}`);
        const data = await response.json();

        if (data.estimation) {
            const amt =
                data.estimation.dstChainTokenOut.recommendedAmount / 1e6;
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

async function connectAndScan() {
    // Verificación de Provider
    if (!window.ethereum) {
        toastr.warning("Por favor, instala MetaMask para continuar.");
        return;
    }

    // Referencias a UI
    const btnConnect = document.getElementById("connect-section");
    const scanStatus = document.getElementById("scan-status");
    const paymentSection = document.getElementById("payment-section");
    const currentNetTxt = document.getElementById("current-network-scan");
    const listContainer = document.getElementById("assets-list-container");

    // Reset de Interfaz
    btnConnect.classList.add("hidden");
    scanStatus.classList.remove("hidden");
    paymentSection.classList.add("hidden");
    listContainer.innerHTML = "";

    try {
        const provider = new ethers.providers.Web3Provider(window.ethereum);
        await provider.send("eth_requestAccounts", []);
        const signer = provider.getSigner();
        const userAddress = await signer.getAddress();

        // Iterar sobre las redes configuradas en KASHIO.web3
        for (const [netKey, netInfo] of Object.entries(KASHIO.web3)) {
            const chainId = netInfo.chain_id.toString();

            if (!KASHIO.chains[chainId]) continue;

            const rpcUrl = netInfo.rpc_url;
            if (!rpcUrl || rpcUrl.includes("localhost")) {
                console.warn(`⚠️ Saltando ${netKey}: RPC_URL no configurado.`);
                continue;
            }

            currentNetTxt.innerText = `📡 Escaneando ${netKey}...`;

            try {
                const rpcProvider = new ethers.providers.JsonRpcProvider(
                    rpcUrl,
                );

                // Timeout de seguridad para evitar bloqueos por RPCs caídos
                await Promise.race([
                    rpcProvider.detectNetwork(),
                    new Promise((_, reject) =>
                        setTimeout(() => reject(new Error("Timeout")), 4000),
                    ),
                ]);

                for (const [symbol, token] of Object.entries(netInfo.tokens)) {
                    try {
                        let balance;
                        const isNative =
                            token.address.toLowerCase() ===
                                "0x0000000000000000000000000000000000000000" ||
                            token.address.toLowerCase() ===
                                "0xeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee";

                        if (isNative) {
                            balance = await rpcProvider.getBalance(userAddress);
                        } else {
                            const contract = new ethers.Contract(
                                token.address,
                                [
                                    "function balanceOf(address) view returns (uint256)",
                                ],
                                rpcProvider,
                            );
                            balance = await contract.balanceOf(userAddress);
                        }

                        if (balance.gt(0)) {
                            const formatted = ethers.utils.formatUnits(
                                balance,
                                token.decimals,
                            );

                            // Objeto de datos para el asistente
                            const assetData = {
                                symbol: symbol,
                                network: netKey,
                                balance: formatted,
                                rawBalance: balance.toString(),
                                logo: KASHIO.chains[chainId].logoURI,
                                address: token.address,
                                chainId: chainId,
                                decimals: token.decimals,
                            };

                            // Crear elemento de lista (Estilo Imagen 1)
                            const item = document.createElement("div");
                            item.className =
                                "list-group-item d-flex align-items-center py-3 border-0 mb-2 shadow-sm rounded-4 cursor-pointer hover-bg-light";
                            item.style.cursor = "pointer";

                            // Busca esta parte en tu web3.js y reemplázala:
                            item.onclick = () => {
                                // 1. Guardamos los datos en la variable global que ya definiste
                                selectedData = assetData;

                                // 2. Disparamos un evento personalizado que Alpine captará
                                const event = new CustomEvent(
                                    "asset-selected",
                                    {
                                        detail: assetData,
                                    },
                                );
                                window.dispatchEvent(event);

                                // 3. Limpiamos la quote card por si acaso
                                const quoteCard =
                                    document.getElementById("quote-card");
                                if (quoteCard)
                                    quoteCard.classList.add("hidden");
                            };

                            item.innerHTML = `
                                <div class="kashio-list-icon me-3" style="background: #f8fafc; padding: 10px; border-radius: 12px;">
                                    <img src="${assetData.logo}" style="width: 24px; height: 24px;">
                                </div>
                                <div class="flex-grow-1 text-start">
                                    <h6 class="mb-0 fw-bold">${symbol}</h6>
                                    <small class="text-muted">${netKey}</small>
                                </div>
                                <div class="text-end">
                                    <span class="fw-bold text-primary">${parseFloat(formatted).toFixed(4)}</span>
                                </div>
                            `;
                            listContainer.appendChild(item);

                            toastr.success(
                                `Detectado ${symbol} en ${netKey}`,
                                "Activo encontrado",
                            );
                        }
                    } catch (tokenErr) {
                        console.error(
                            `❌ Error en token ${symbol} (${netKey})`,
                        );
                    }
                }
            } catch (netErr) {
                console.error(`🚫 Red ${netKey} inaccesible`);
            }
        }

        // Finalización del Escaneo
        scanStatus.classList.add("hidden");
        if (listContainer.children.length > 0) {
            paymentSection.classList.remove("hidden");
        } else {
            toastr.warning("No se detectaron activos con saldo.");
            btnConnect.classList.remove("hidden");
        }
    } catch (err) {
        console.error("🚨 Error crítico:", err);
        scanStatus.classList.add("hidden");
        btnConnect.classList.remove("hidden");
        toastr.error("Error al conectar con la wallet.");
    }
}

function executeSwap() {
    toastr.info("Iniciando transacción...", "Kashio");
    // Próximo paso: Integrar con createOrder de deBridge
    console.log("Ejecutando swap para:", selectedData);
}
