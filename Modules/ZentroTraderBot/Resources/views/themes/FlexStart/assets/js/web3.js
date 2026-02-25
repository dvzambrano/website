async function connectAndScan() {
    if (!window.ethereum) {
        toastr.warning("Por favor, instala MetaMask para continuar.");
        return;
    }

    const btnConnect = document.getElementById("btn-connect");
    const scanStatus = document.getElementById("scan-status");
    const paymentSection = document.getElementById("payment-section");
    const currentNetTxt = document.getElementById("current-network-scan");
    const selector = document.getElementById("asset-selector");

    // Reset de UI
    btnConnect.classList.add("hidden");
    scanStatus.classList.remove("hidden");
    paymentSection.classList.add("hidden");
    selector.innerHTML =
        '<option value="">Selecciona un activo con saldo</option>';

    try {
        const provider = new ethers.providers.Web3Provider(window.ethereum);
        await provider.send("eth_requestAccounts", []);
        const signer = provider.getSigner();
        const userAddress = await signer.getAddress();

        for (const [netKey, netInfo] of Object.entries(KASHIO.web3)) {
            const chainId = netInfo.chain_id.toString();

            if (!KASHIO.chains[chainId]) continue;

            const rpcUrl = netInfo.rpc_url;
            if (!rpcUrl || rpcUrl.includes("localhost")) {
                console.warn(`⚠️ Saltando ${netKey}: RPC_URL no configurado.`);
                continue;
            }

            currentNetTxt.innerText = `📡 Escaneando ${netKey}...`;
            console.log(`📡 Conectando a ${netKey} vía ${rpcUrl}`);

            try {
                const rpcProvider = new ethers.providers.JsonRpcProvider(
                    rpcUrl,
                );

                // Timeout de 4s para no bloquear Kashio si un RPC falla
                await Promise.race([
                    rpcProvider.detectNetwork(),
                    new Promise((_, reject) =>
                        setTimeout(() => reject(new Error("Timeout")), 4000),
                    ),
                ]);

                for (const [symbol, token] of Object.entries(netInfo.tokens)) {
                    try {
                        let balance;
                        // Direcciones nativas comunes (0x0 o 0xeee...)
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
                            toastr.success(
                                `${formatted} ${symbol} en ${netKey}`,
                                "💰 Activo Encontrado:",
                                { timeOut: 10000 }, // Dejamos los hallazgos más tiempo visibles
                            );

                            const option = document.createElement("option");
                            const assetData = {
                                chainId: chainId,
                                chainName: KASHIO.chains[chainId].chainName,
                                address: token.address,
                                symbol: symbol,
                                decimals: token.decimals,
                                rawBalance: balance.toString(),
                                balance: formatted,
                                logo: KASHIO.chains[chainId].logoURI,
                            };

                            option.value = JSON.stringify(assetData);
                            option.text = `${symbol} (${netKey}) - Saldo: ${parseFloat(formatted).toFixed(4)}`;
                            selector.appendChild(option);
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

        scanStatus.classList.add("hidden");
        if (selector.options.length > 1) {
            paymentSection.classList.remove("hidden");
        } else {
            toastr.warning("No se detectaron activos con saldo.");
            btnConnect.classList.remove("hidden");
        }
    } catch (err) {
        console.error("🚨 Error crítico:", err);
        scanStatus.classList.add("hidden");
        btnConnect.classList.remove("hidden");
    }
}

async function updateQuote() {
    const selector = document.getElementById("asset-selector");
    const logo = document.getElementById("selected-asset-logo");
    const card = document.getElementById("quote-card");
    const btn = document.getElementById("btn-pay");
    const receiveTxt = document.getElementById("txt-receive-amount");

    if (!selector.value) {
        logo.classList.add("hidden");
        card.classList.add("hidden");
        btn.disabled = true;
        return;
    }

    selectedData = JSON.parse(selector.value);
    logo.src = selectedData.logo;
    logo.classList.remove("hidden");
    card.classList.remove("hidden");
    btn.disabled = true;
    receiveTxt.innerText = "Calculando...";

    document.getElementById("txt-send-amount").innerText =
        `${selectedData.balance} ${selectedData.symbol}`;

    // FETCH A TU API DE LARAVEL (DeBridgeController@getEstimation)
    try {
        const response = await fetch(
            `/zentro/bridge/estimate?srcChainId=${selectedData.chainId}&srcToken=${selectedData.address}&amount=${selectedData.rawBalance}&dstChainId=${KASHIO.destChain}&dstToken=${KASHIO.destToken}`,
        );
        const data = await response.json();

        if (data.estimation) {
            const amt =
                data.estimation.dstChainTokenOut.recommendedAmount / 1e6;
            receiveTxt.innerText = `${amt.toFixed(2)} USDC`;
            btn.disabled = false;
        } else {
            receiveTxt.innerText = "Ruta no disponible";
        }
    } catch (e) {
        receiveTxt.innerText = "Error de conexión";
    }
}

function executeSwap() {
    // Aquí llamarías a createOrder y dispararías la transacción con ethers
    console.log("Iniciando swap para", selectedData);
    toastr.info("Redireccionando a firma de contrato...");
}
