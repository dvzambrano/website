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
            transition: background-color 0.3s;
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

        /* Banner de estado de conexión */
        #connection-bar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            padding: 5px;
            font-size: 0.8rem;
            font-weight: bold;
            color: white;
            display: none;
        }
    </style>
</head>

<body>

    <div id="connection-bar"></div>

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

        // Estilos base
        document.body.style.backgroundColor = tg.backgroundColor;
        document.body.style.color = tg.textColor;

        const STORAGE_KEY = "{{ $bot }}_pending_scans";

        // --- 1. MONITOR DE RED EN TIEMPO REAL ---
        // Esto se ejecuta apenas cambias el estado del teléfono (Modo Avión)
        window.addEventListener('online', () => {
            updateUIState(true);
            // Intentar auto-sincronizar si vuelve la red
            fetchCodes();
        });
        window.addEventListener('offline', () => {
            updateUIState(false);
        });

        function updateUIState(isOnline) {
            const bar = document.getElementById('connection-bar');

            if (isOnline) {
                bar.style.display = 'none'; // Ocultamos barra roja si hay red
                // Si estábamos en pantalla de error, restauramos texto
                if (document.getElementById('status-title').innerText.includes("Offline")) {
                    document.getElementById('status-title').innerText = "🟢 Conectado";
                    document.getElementById('status-desc').innerText = "Listo para sincronizar.";
                }
            } else {
                bar.style.display = 'block';
                bar.style.backgroundColor = '#d32f2f';
                bar.innerText = "SIN CONEXIÓN";

                // Actualizamos el centro de la pantalla inmediatamente
                document.getElementById('main-loader').style.display = "none";
                document.getElementById('status-title').innerText = "🔴 Modo Offline";

                let pending = JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");
                document.getElementById('status-desc').innerText = pending.length > 0
                    ? "Tienes " + pending.length + " códigos guardados."
                    : "No hay conexión a internet.";

                document.getElementById('retry-btn').style.display = "inline-block";
            }
        }

        // --- 2. GESTIÓN DE DATOS ---
        function saveCodeToLocalStorage(code) {
            let pending = JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");
            const exists = pending.find(item => item.code === code);
            if (!exists) {
                pending.push({ code: code, date: new Date().toISOString() });
                localStorage.setItem(STORAGE_KEY, JSON.stringify(pending));
            }
            // Actualizar UI inmediatamente si estamos offline
            if (!navigator.onLine) updateUIState(false);
        }

        // --- 3. SINCRONIZACIÓN ---
        function fetchCodes(callback) {
            let pending = JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");

            if (pending.length > 0) {

                // CORTOCIRCUITO 1: Si el navegador ya sabe que no hay red, paramos AQUÍ.
                if (!navigator.onLine) {
                    updateUIState(false); // Reforzamos la UI
                    if (callback) callback();
                    return;
                }

                // Si hay red, mostramos loader
                document.getElementById('main-loader').style.display = "inline-block";
                document.getElementById('status-title').innerText = "⌛️ Sincronizando...";
                document.getElementById('retry-btn').style.display = "none";

                // Timeout de seguridad (3 segundos)
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
                        if (!response.ok) throw new Error('Error servidor');
                        return response.json();
                    })
                    .then(data => {
                        // ÉXITO
                        localStorage.removeItem(STORAGE_KEY);
                        document.getElementById('main-loader').style.display = "none";
                        document.getElementById('status-title').innerText = "✅ ¡Logrado!";
                        document.getElementById('status-desc').innerText = "Procesados " + pending.length + " códigos.";
                        document.getElementById('retry-btn').style.display = "inline-block";
                        tg.HapticFeedback.notificationOccurred('success');
                        if (callback) callback();
                    })
                    .catch(error => {
                        // ERROR (Timeout o Red)
                        console.log("Error sync:", error);
                        updateUIState(false); // Usamos la función centralizada
                        tg.HapticFeedback.notificationOccurred('warning');
                        if (callback) callback();
                    });
            } else {
                // Nada pendiente
                document.getElementById('main-loader').style.display = "none";
                document.getElementById('retry-btn').style.display = "inline-block";
                if (navigator.onLine) {
                    document.getElementById('status-title').innerText = "Listo";
                    document.getElementById('status-desc').innerText = "Presiona para escanear.";
                } else {
                    updateUIState(false);
                }
                if (callback) callback();
            }
        }

        function openScanner() {
            tg.showScanQrPopup({ text: "Escanea la etiqueta" }, function (text) {
                // UI temporal mientras procesamos
                document.getElementById('main-loader').style.display = "inline-block";
                document.getElementById('status-title').innerText = "⌛️ Procesando";
                document.getElementById('retry-btn').style.display = "none";

                saveCodeToLocalStorage(text);
                fetchCodes();
                return true;
            });
        }

        // --- INICIO ---
        // Verificamos estado inicial
        if (!navigator.onLine) {
            updateUIState(false);
        } else {
            // Si hay red, intentamos sync y luego abrir cámara
            fetchCodes(function () {
                openScanner();
            });
        }

        tg.onEvent('scanQrPopupClosed', function () {
            // Si cerramos el scanner y no hay error ni éxito, mostramos estado neutro
            if (document.getElementById('status-title').innerText === "⌛️ Procesando") {
                document.getElementById('status-title').innerText = "Pausa";
                document.getElementById('status-desc').innerText = "";
                document.getElementById('main-loader').style.display = "none";
            }
            document.getElementById('retry-btn').style.display = "inline-block";
        });

    </script>
</body>

</html>