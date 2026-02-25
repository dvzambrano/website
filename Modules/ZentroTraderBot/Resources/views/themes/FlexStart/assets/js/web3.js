/**
 * Kashio - ZentroTraderBot Web3 Handler
 * Sincronizado con Interfaz de Asistente (Alpine.js)
 */

let selectedData = null; // Almacena el activo seleccionado globalmente
const QUOTE_REFRESH_TIME = 30000; // 30 segundos es un estándar sano

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

/**
 * Calcula la cotización en deBridge basándose en el monto manual
 */
async function updateQuoteManual() {
    const card = document.getElementById("quote-card");
    const btn = document.getElementById("btn-pay");
    const receiveTxt = document.getElementById("txt-receive-amount");
    const sendTxt = document.getElementById("txt-send-amount");

    const alpine = Alpine.$data(document.getElementById("payment-section"));
    const amountInput = alpine.amount;

    if (!amountInput || amountInput <= 0 || !selectedData) {
        card.classList.add("hidden");
        btn.disabled = true;
        return;
    }

    card.classList.remove("hidden");
    btn.disabled = true;
    receiveTxt.innerText = "Calculando...";
    sendTxt.innerText = `${amountInput} ${selectedData.symbol}`;

    try {
        const rawAmount = ethers.utils.parseUnits(
            amountInput.toString(),
            selectedData.decimals,
        );

        const query = new URLSearchParams({
            srcChainId: selectedData.chainId,
            srcToken: selectedData.address,
            amount: rawAmount.toString(),
            dstChainId: KASHIO.destChain,
            dstToken: KASHIO.destToken,
        });

        // USAMOS LA URL SINCRONIZADA
        const response = await fetch(`${KASHIO.quoteUrl}?${query.toString()}`);

        if (!response.ok) throw new Error("Error en servidor");

        const data = await response.json();

        // Ajusta 'data.estimation' según lo que devuelva tu LandingController@getQuote
        if (data.estimation) {
            const recommendedAmount =
                data.estimation.dstChainTokenOut.recommendedAmount;
            // USDC en Polygon tiene 6 decimales
            const amt = recommendedAmount / 1_000_000;

            // Mostramos 6 decimales para coincidir con deBridge y evitar confusiones
            receiveTxt.innerText = `${amt.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 6 })} USD`;
            btn.disabled = false;
        } else {
            receiveTxt.innerText = "Ruta no disponible";
        }
    } catch (e) {
        console.error("Error en quote:", e);
        receiveTxt.innerText = "Error de conexión";
    }
}

function executeSwap() {
    toastr.info("Iniciando transacción...", "Kashio");
    // Próximo paso: Integrar con createOrder de deBridge
    console.log("Ejecutando swap para:", selectedData);
}
