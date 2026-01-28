<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Escaner Pro</title>

    <script src="https://telegram.org/js/telegram-web-app.js"></script>

    <style>
        :root {
            --tg-theme-bg-color: #ffffff;
            --tg-theme-text-color: #222222;
            --tg-theme-button-color: #3390ec;
            --tg-theme-hint-color: #707579;
        }

        body {
            margin: 0;
            padding: 0;
            background-color: var(--tg-theme-bg-color);
            color: var(--tg-theme-text-color);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            text-align: center;
            overflow: hidden;
        }

        .container {
            padding: 20px;
            width: 100%;
            box-sizing: border-box;
        }

        .loader {
            border: 4px solid rgba(0, 0, 0, 0.1);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border-left-color: var(--tg-theme-button-color);
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-bottom: 20px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        h2 {
            font-size: 1.4rem;
            margin: 10px 0;
        }

        p {
            font-size: 1rem;
            opacity: 0.8;
            line-height: 1.4;
            min-height: 1.4em;
        }

        .btn-retry {
            margin-top: 25px;
            padding: 12px 24px;
            background-color: var(--tg-theme-button-color);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
            display: none;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        /* Barra superior de estado (solo visible offline) */
        #connection-bar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            padding: 8px;
            font-size: 0.9rem;
            font-weight: bold;
            color: white;
            background-color: #d32f2f;
            display: none;
            z-index: 100;
        }
    </style>
</head>

<body>

    <div id="connection-bar">⚠️ SIN CONEXIÓN - MODO OFFLINE</div>

    <div class="container" id="main-content">
        <div id="main-loader" class="loader"></div>
        <h2 id="status-title">Iniciando...</h2>
        <p id="status-desc">Cargando sistema.</p>

        <button class="btn-retry" id="retry-btn" onclick="openScanner()">Reabrir Cámara</button>
    </div>

    <script>
        const tg = window.Telegram.WebApp;
        tg.ready();
        tg.expand();

        document.body.style.backgroundColor = tg.backgroundColor;
        document.body.style.color = tg.textColor;

        const STORAGE_KEY = "{{ $bot }}_pending_scans";
        let lastKnownState = navigator.onLine; // Para comparar cambios

        // =========================================================
        // 1. EL LATIDO (HEARTBEAT) - Aquí está la magia
        // =========================================================
        // =========================================================
        // 1. EL LATIDO CON PING REAL
        // =========================================================
        setInterval(async () => {
            let isOnline;

            try {
                // Intentamos una petición ultra rápida a un recurso externo o local
                // Usamos 'no-cache' para obligar a que salga a la red
                const response = await fetch("https://www.google.com/favicon.ico", {
                    mode: 'no-cors',
                    cache: 'no-store',
                    signal: AbortSignal.timeout(1500) // Si en 1.5s no responde, está muerto
                });
                isOnline = true;
            } catch (e) {
                isOnline = false;
            }

            // Solo actuamos si el estado REAL cambió
            if (isOnline !== lastKnownState) {
                lastKnownState = isOnline;
                console.log("Estado de red REAL cambiado a: " + (isOnline ? "ONLINE" : "OFFLINE"));
                updateUIState(isOnline);

                if (isOnline) {
                    // Intentar sincronizar apenas detecte internet real
                    fetchCodes();
                }
            }
        }, 3000); // Lo hacemos cada 3 segundos para no saturar la batería


        // =========================================================
        // 2. ACTUALIZACIÓN DE INTERFAZ
        // =========================================================
        function updateUIState(isOnline) {
            const bar = document.getElementById('connection-bar');
            let pending = JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");

            if (isOnline) {
                // VOLVIMOS A LINEA
                bar.style.display = 'none';

                // Solo cambiamos el texto central si estaba mostrando error
                if (document.getElementById('status-title').innerText.includes("Offline")) {
                    document.getElementById('status-title').innerText = "🟢 Conexión recuperada";
                    document.getElementById('status-desc').innerText = "Sincronizando...";
                    document.getElementById('main-loader').style.display = "inline-block";
                    document.getElementById('retry-btn').style.display = "none";
                }

            } else {
                // SE CAYÓ LA RED
                bar.style.display = 'block';

                document.getElementById('main-loader').style.display = "none";
                document.getElementById('status-title').innerText = "🔴 Modo Offline";

                document.getElementById('status-desc').innerText = pending.length > 0
                    ? "Se guardarán " + pending.length + " bultos en el teléfono."
                    : "Los códigos se guardarán localmente.";

                document.getElementById('retry-btn').style.display = "inline-block";
            }
        }


        // =========================================================
        // 3. LOGICA DE DATOS
        // =========================================================
        function saveCodeToLocalStorage(code) {
            let pending = JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");
            const exists = pending.find(item => item.code === code);
            if (!exists) {
                pending.push({ code: code, date: new Date().toISOString() });
                localStorage.setItem(STORAGE_KEY, JSON.stringify(pending));
            }
            // Si estamos offline, refrescamos el contador visual
            if (!navigator.onLine) updateUIState(false);
        }

        function fetchCodes(callback) {
            let pending = JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");

            if (pending.length > 0) {

                // Cortocircuito Inmediato
                if (!navigator.onLine) {
                    updateUIState(false);
                    if (callback) callback();
                    return;
                }

                // UI de Carga
                document.getElementById('main-loader').style.display = "inline-block";
                document.getElementById('status-title').innerText = "⌛️ Sincronizando...";
                document.getElementById('status-desc').innerText = "Enviando " + pending.length + " códigos...";
                document.getElementById('retry-btn').style.display = "none";

                // Timeout de 3 seg
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 3000);

                fetch("{{ route('telegram-scanner-store') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        codes: pending,
                        bot: "{{ $bot }}",
                        initData: tg.initData
                    }),
                    signal: controller.signal
                })
                    .then(response => {
                        clearTimeout(timeoutId);
                        if (!response.ok) throw new Error('Error Servidor');
                        return response.json();
                    })
                    .then(data => {
                        localStorage.removeItem(STORAGE_KEY);
                        document.getElementById('main-loader').style.display = "none";
                        document.getElementById('status-title').innerText = "✅ ¡Logrado!";
                        document.getElementById('status-desc').innerText = "Se enviaron " + pending.length + " códigos.";
                        document.getElementById('retry-btn').style.display = "inline-block";
                        tg.HapticFeedback.notificationOccurred('success');
                        if (callback) callback();
                    })
                    .catch(error => {
                        console.log("Error sync:", error);
                        updateUIState(false); // Asumimos offline si falla
                        tg.HapticFeedback.notificationOccurred('warning');
                        if (callback) callback();
                    });
            } else {
                // Nada que enviar
                document.getElementById('main-loader').style.display = "none";
                if (!document.getElementById('status-title').innerText.includes("Offline")) {
                    document.getElementById('retry-btn').style.display = "inline-block";
                }
                if (callback) callback();
            }
        }

        function openScanner() {
            tg.showScanQrPopup({ text: "Escanea la etiqueta" }, function (text) {
                // UI temporal
                document.getElementById('main-loader').style.display = "inline-block";
                document.getElementById('status-title').innerText = "⌛️ Procesando";
                document.getElementById('retry-btn').style.display = "none";

                saveCodeToLocalStorage(text);
                fetchCodes();
                return true;
            });
        }

        // =========================================================
        // 4. ARRANQUE
        // =========================================================
        // Chequeo inicial
        if (!navigator.onLine) {
            updateUIState(false);
        } else {
            fetchCodes(function () {
                openScanner();
            });
        }

        tg.onEvent('scanQrPopupClosed', function () {
            // Limpiar mensaje de 'procesando' si se cerró manual
            if (document.getElementById('status-title').innerText.includes("Procesando")) {
                document.getElementById('status-title').innerText = "Listo";
                document.getElementById('status-desc').innerText = "";
                document.getElementById('main-loader').style.display = "none";
            }
            document.getElementById('retry-btn').style.display = "inline-block";
        });

    </script>
</body>

</html>