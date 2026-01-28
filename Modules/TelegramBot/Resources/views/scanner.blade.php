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

        #connection-badge {
            font-size: 0.8rem;
            margin-top: 15px;
            font-weight: bold;
        }
    </style>
</head>

<body>

    <div class="container" id="main-content">
        <div id="main-loader" class="loader"></div>
        <h2 id="status-title">Iniciando...</h2>
        <p id="status-desc">Verificando estado del sistema.</p>

        <button class="btn-retry" id="retry-btn" onclick="openScanner()">Reabrir Cámara</button>

        <div id="connection-badge"></div>
    </div>

    <script>
        const tg = window.Telegram.WebApp;

        tg.ready();
        tg.expand();

        // Estilos dinámicos de Telegram
        document.body.style.backgroundColor = tg.backgroundColor;
        document.body.style.color = tg.textColor;

        // --- GESTIÓN DE LOCALSTORAGE ---
        function saveCodeToLocalStorage(code) {
            const storageKey = "{{ $bot }}_pending_scans";
            let pending = JSON.parse(localStorage.getItem(storageKey) || "[]");

            const exists = pending.find(item => item.code === code);
            if (!exists) {
                pending.push({
                    code: code,
                    date: new Date().toISOString()
                });
                localStorage.setItem(storageKey, JSON.stringify(pending));
            }
            return pending;
        }

        // --- NÚCLEO DE PROCESAMIENTO ---
        function fetchCodes(callback) {
            const storageKey = "{{ $bot }}_pending_scans";
            let pending = JSON.parse(localStorage.getItem(storageKey) || "[]");

            if (pending.length > 0) {
                // 1. CORTOCIRCUITO: Si el navegador dice que está offline, ni lo intenta
                if (!navigator.onLine) {
                    showOfflineStatus(pending.length);
                    if (callback) callback();
                    return;
                }

                // 2. Si hay red, procedemos con el envío
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
                        if (!response.ok) throw new Error('Error en servidor');
                        return response.json();
                    })
                    .then(data => {
                        // ÉXITO
                        localStorage.removeItem(storageKey);
                        document.getElementById('main-loader').style.display = "none";
                        document.getElementById('status-title').innerText = "✅ ¡Logrado!";
                        document.getElementById('status-desc').innerText = "Se procesaron " + pending.length + " códigos correctamente.";
                        document.getElementById('retry-btn').style.display = "inline-block";

                        tg.HapticFeedback.notificationOccurred('success');
                        if (callback) callback();
                    })
                    .catch(error => {
                        // ERROR DE RED REAL (Señal débil o caída de servidor)
                        showOfflineStatus(pending.length);
                        if (callback) callback();
                    });
            } else {
                // No hay nada pendiente
                document.getElementById('main-loader').style.display = "none";
                document.getElementById('retry-btn').style.display = "inline-block";
                if (callback) callback();
            }
        }

        function showOfflineStatus(count) {
            document.getElementById('main-loader').style.display = "none";
            document.getElementById('status-title').innerText = "🔴 Modo Offline";
            document.getElementById('status-desc').innerText = "Sin conexión. Tienes " + count + " códigos guardados en el teléfono.";
            document.getElementById('retry-btn').style.display = "inline-block";
            tg.HapticFeedback.notificationOccurred('warning');
        }

        // --- CÁMARA ---
        function openScanner() {
            tg.showScanQrPopup({ text: "Escanea la etiqueta del bulto" }, function (text) {
                // Feedback visual inmediato
                document.getElementById('main-loader').style.display = "inline-block";
                document.getElementById('status-title').innerText = "⌛️ Procesando";
                document.getElementById('status-desc').innerText = "Guardando código...";
                document.getElementById('retry-btn').style.display = "none";

                saveCodeToLocalStorage(text);
                fetchCodes(); // Intentará enviar si hay red, sino saltará al aviso offline

                return true; // Cierra el popup nativo
            });
        }

        // --- MONITOREO DE RED ---
        function updateNetworkStatus() {
            const badge = document.getElementById('connection-badge');
            if (navigator.onLine) {
                badge.innerText = "🟢 Conectado";
                badge.style.color = "#2e7d32";
            } else {
                badge.innerText = "🔴 Sin conexión";
                badge.style.color = "#d32f2f";
            }
        }

        // --- INICIO ---
        try {
            updateNetworkStatus();
            window.addEventListener('online', updateNetworkStatus);
            window.addEventListener('offline', updateNetworkStatus);

            // Al cargar, sincronizamos pendientes y luego abrimos cámara
            fetchCodes(function () {
                openScanner();
            });
        } catch (e) {
            document.getElementById('status-title').innerText = "❌ Error";
            document.getElementById('status-desc').innerText = "No se pudo iniciar el sistema.";
            document.getElementById('retry-btn').style.display = "inline-block";
        }

        // Evento de cierre de cámara por el usuario
        tg.onEvent('scanQrPopupClosed', function () {
            // Solo limpiamos si no hay un mensaje de éxito/error activo
            if (document.getElementById('status-title').innerText === "⌛️ Procesando") {
                document.getElementById('status-title').innerText = "Escáner en pausa";
                document.getElementById('status-desc').innerText = "Presiona el botón para continuar.";
            }
            document.getElementById('retry-btn').style.display = "inline-block";
        });

    </script>
</body>

</html>