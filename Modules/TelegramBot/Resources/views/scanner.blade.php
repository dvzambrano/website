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
        }

        .btn-retry {
            margin-top: 25px;
            padding: 12px 24px;
            background-color: var(--tg-theme-button-color);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
            display: none;
        }

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

    <div id="connection-bar">⚠️ SIN CONEXIÓN - MODO LOCAL</div>

    <div class="container">
        <div id="main-loader" class="loader"></div>
        <h2 id="status-title"></h2>
        <p id="status-desc"></p>
        <button class="btn-retry" id="retry-btn" onclick="openScanner()">Abrir Cámara</button>
    </div>

    <script>
        const tg = window.Telegram.WebApp;
        tg.ready();
        tg.expand();

        document.body.style.backgroundColor = tg.backgroundColor;
        document.body.style.color = tg.textColor;

        const STORAGE_KEY = "{{ $bot }}_pending_scans";
        let lastKnownState = true; // Asumimos online al inicio hasta el primer ping

        // =========================================================
        // 1. EL LATIDO (PING REAL)
        // =========================================================
        setInterval(async () => {
            let isOnline;
            try {
                // Ping a Google (o podrías usar tu propia URL de salud del servidor)
                await fetch("https://www.google.com/favicon.ico", {
                    mode: 'no-cors', cache: 'no-store', signal: AbortSignal.timeout(2000)
                });
                isOnline = true;
            } catch (e) {
                isOnline = false;
            }

            if (isOnline !== lastKnownState) {
                lastKnownState = isOnline;
                updateUIState(isOnline);
                if (isOnline) fetchCodes(); // Sincroniza apenas detecte internet
            }
        }, 3000);

        // =========================================================
        // 2. INTERFAZ
        // =========================================================
        function updateUIState(isOnline) {
            const bar = document.getElementById('connection-bar');
            let pending = JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");

            if (isOnline) {
                bar.style.display = 'none';
                if (document.getElementById('status-title').innerText.includes("Offline")) {
                    document.getElementById('status-title').innerText = "🟢 En Línea";
                    document.getElementById('status-desc').innerText = "Sincronizando bultos...";
                }
            } else {
                bar.style.display = 'block';
                document.getElementById('main-loader').style.display = "none";
                document.getElementById('status-title').innerText = "🔴 Modo Offline";
                document.getElementById('status-desc').innerText = pending.length > 0
                    ? "Tienes " + pending.length + " códigos guardados localmente."
                    : "Los códigos se guardarán en el teléfono.";
                document.getElementById('retry-btn').style.display = "inline-block";
            }
        }

        // =========================================================
        // 3. DATOS Y ENVÍO
        // =========================================================
        function saveCodeToLocalStorage(code) {
            let pending = JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");
            if (!pending.find(item => item.code === code)) {
                pending.push({ code: code, date: new Date().toISOString() });
                localStorage.setItem(STORAGE_KEY, JSON.stringify(pending));
            }
            if (!lastKnownState) updateUIState(false);
        }

        function fetchCodes(callback) {
            let pending = JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");

            if (pending.length > 0) {
                // --- CORTOCIRCUITO BASADO EN EL LATIDO ---
                if (!lastKnownState) {
                    updateUIState(false);
                    if (callback) callback();
                    return;
                }

                document.getElementById('main-loader').style.display = "inline-block";
                document.getElementById('status-title').innerText = "⌛️ Sincronizando...";
                document.getElementById('retry-btn').style.display = "none";

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
                    })
                })
                    .then(response => {
                        if (!response.ok) throw new Error();
                        return response.json();
                    })
                    .then(data => {
                        let STORAGE_KEY = "{{ $bot }}_pending_scans";
                        localStorage.removeItem(STORAGE_KEY);
                        document.getElementById('main-loader').style.display = "none";
                        document.getElementById('status-title').innerText = "✅ ¡Logrado!";
                        document.getElementById('status-desc').innerText = "Se procesaron " + pending.length + " códigos.";
                        document.getElementById('retry-btn').style.display = "inline-block";
                        tg.HapticFeedback.notificationOccurred('success');
                        if (callback) callback();
                    })
                    .catch(() => {
                        updateUIState(false);
                        tg.HapticFeedback.notificationOccurred('warning');
                        if (callback) callback();
                    });
            } else {
                document.getElementById('main-loader').style.display = "none";
                if (callback) callback();
            }
        }

        function openScanner() {
            tg.showScanQrPopup({ text: "Escanea la etiqueta" }, function (text) {
                document.getElementById('main-loader').style.display = "inline-block";
                document.getElementById('status-title').innerText = "⌛️ Procesando";
                document.getElementById('retry-btn').style.display = "none";

                saveCodeToLocalStorage(text);
                fetchCodes();
                return true;
            });
        }

        // Inicio
        fetchCodes(() => {
            if (lastKnownState) openScanner();
        });

        tg.onEvent('scanQrPopupClosed', () => {
            document.getElementById('retry-btn').style.display = "inline-block";
        });

    </script>
</body>

</html>