<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title></title>

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
            min-height: 1.5em;
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

    <div id="connection-bar">⚠️ {{ __('telegrambot::bot.scanner.localmode') }}</div>

    <div class="container">
        <div id="main-loader" class="loader"></div>
        <h2 id="status-title"></h2>
        <p id="status-desc"></p>
        <button class="btn-retry" id="retry-btn" onclick="openScanner()">
            {{ __('telegrambot::bot.scanner.opencamera') }}
        </button>
    </div>

    <script>
        const tg = window.Telegram.WebApp;
        tg.ready();
        tg.expand();

        document.body.style.backgroundColor = tg.backgroundColor;
        document.body.style.color = tg.textColor;

        const STORAGE_KEY = "{{ $bot }}_pending_scans";
        let lastKnownState = true;
        let currentCoords = null; // Almacén de ubicación en tiempo real

        // =========================================================
        // 1. MONITOR DE UBICACIÓN (WATCHER)
        // =========================================================
        function startLocationWatcher() {
            if (navigator.geolocation) {
                navigator.geolocation.watchPosition(
                    (position) => {
                        currentCoords = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude,
                            acc: position.coords.accuracy
                        };
                        console.log("GPS OK");
                    },
                    (error) => console.warn("GPS Error:", error.message),
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                );
            }
        }

        // =========================================================
        // 2. EL LATIDO (PING REAL)
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
        // 3. INTERFAZ
        // =========================================================
        function updateUIState(isOnline) {
            const bar = document.getElementById('connection-bar');
            let pending = JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");

            if (isOnline) {
                bar.style.display = 'none';
                if (document.getElementById('status-title').innerText.includes("🔴")) {
                    document.getElementById('status-title').innerText = "🟢 {{ __('telegrambot::bot.scanner.online') }}";
                    document.getElementById('status-desc').innerText = "";
                }
            } else {
                bar.style.display = 'block';
                document.getElementById('main-loader').style.display = "none";
                document.getElementById('status-title').innerText = "🔴 {{ __('telegrambot::bot.scanner.offline') }}";
                document.getElementById('status-desc').innerText = pending.length > 0
                    ? pending.length + " {{ __('telegrambot::bot.scanner.localstoragedcodes') }}."
                    : "{{ __('telegrambot::bot.scanner.localstorageaction') }}.";
                document.getElementById('retry-btn').style.display = "inline-block";
            }
        }

        // =========================================================
        // 4. DATOS Y ENVÍO
        // =========================================================
        function saveCodeToLocalStorage(code) {
            let pending = JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");
            if (!pending.find(item => item.code === code)) {
                pending.push({
                    code: code,
                    date: new Date().toISOString(),
                    location: currentCoords // Guardamos la ubicación que ya tenemos en memoria
                });
                localStorage.setItem(STORAGE_KEY, JSON.stringify(pending));
                // haciendo q vibre tambien
                tg.HapticFeedback.notificationOccurred('success');
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
                document.getElementById('status-title').innerText = "⌛️ {{ __('telegrambot::bot.scanner.synchronizing') }}...";
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
                        document.getElementById('status-title').innerText = "✅ {{ __('telegrambot::bot.scanner.fetch.title') }}";
                        document.getElementById('status-desc').innerText = pending.length + " {{ __('telegrambot::bot.scanner.fetch.desc') }}.";
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
            tg.showScanQrPopup({ text: "{{ __('telegrambot::bot.scanner.prompt') }}" }, function (text) {
                document.getElementById('main-loader').style.display = "inline-block";
                document.getElementById('status-title').innerText = "⌛️ {{ __('telegrambot::bot.scanner.procesing') }}";
                document.getElementById('retry-btn').style.display = "none";

                saveCodeToLocalStorage(text);
                fetchCodes();
                return true;
            });
        }

        // =========================================================
        // 5. INICIO CONTROLADO
        // =========================================================

        async function bootstrap() {
            let gpsSuccess = false;
            let gpsRequired = parseInt("{{ $gpsrequired }}");

            // 1. Solo intentamos obtener GPS si no es -1
            if (gpsRequired !== -1) {
                try {
                    document.getElementById('status-title').innerText = "{{ __('telegrambot::bot.scanner.loadinggps') }}...";
                    document.getElementById('main-loader').style.display = "inline-block";

                    await new Promise((resolve) => {
                        navigator.geolocation.getCurrentPosition(
                            (position) => {
                                currentCoords = {
                                    lat: position.coords.latitude,
                                    lng: position.coords.longitude,
                                    acc: position.coords.accuracy
                                };
                                gpsSuccess = true;
                                resolve();
                            },
                            (error) => {
                                console.warn("GPS denegado:", error);
                                gpsSuccess = false;
                                resolve();
                            },
                            { enableHighAccuracy: true, timeout: 5000 }
                        );
                    });
                } catch (e) {
                    gpsSuccess = false;
                }
            }

            // 2. Lógica de Validación de Obligatoriedad
            if (gpsRequired === 1 && !gpsSuccess) {
                document.getElementById('main-loader').style.display = "none";
                document.getElementById('status-title').innerText = "❌ {{ __('telegrambot::bot.scanner.gps_denied_title') }}";
                document.getElementById('status-desc').innerText = "{{ __('telegrambot::bot.scanner.gps_denied_desc') }}";

                const retryBtn = document.getElementById('retry-btn');
                retryBtn.innerText = "{{ __('telegrambot::bot.scanner.retry_gps') }}";
                retryBtn.onclick = () => location.reload();
                retryBtn.style.display = "inline-block";

                tg.HapticFeedback.notificationOccurred('error');
                return;
            }

            // 3. SOLO activamos el watcher si el GPS está habilitado (0 o 1)
            if (gpsRequired !== -1) {
                startLocationWatcher();
            }

            // 4. Proceder a la cámara
            fetchCodes(() => {
                if (lastKnownState) {
                    openScanner();
                } else {
                    updateUIState(false);
                }
            });
        }

        // Ejecutamos el arranque
        bootstrap();

        tg.onEvent('scanQrPopupClosed', () => {
            document.getElementById('retry-btn').style.display = "inline-block";
        });

    </script>
</body>

</html>