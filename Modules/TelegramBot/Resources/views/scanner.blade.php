<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Escaner</title>

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
        }

        .container {
            padding: 20px;
        }

        .loader {
            border: 4px solid rgba(0, 0, 0, 0.1);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border-left-color: var(--tg-theme-button-color);
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-bottom: 15px;
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
            font-size: 1.2rem;
            margin-bottom: 10px;
        }

        p {
            font-size: 0.9rem;
            opacity: 0.7;
        }

        /* Botón de re-intento por si el usuario cierra el popup por error */
        .btn-retry {
            margin-top: 20px;
            padding: 10px 20px;
            background-color: var(--tg-theme-button-color);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: none;
            /* Se muestra solo si falla o cierran */
        }
    </style>
</head>

<body>

    <div class="container" id="main-content">
        <div class="loader"></div>
        <h2 id="status-title">Iniciando Escáner...</h2>
        <p id="status-desc">Se abrirá la cámara de Telegram para leer el código.</p>
        <button class="btn-retry" id="retry-btn" onclick="openScanner()">Reabrir Cámara</button>
    </div>

    <script>
        const tg = window.Telegram.WebApp;

        // Configurar la WebApp
        tg.ready();
        tg.expand(); // Expandir al máximo

        // Aplicar colores del tema de Telegram automáticamente
        document.body.style.backgroundColor = tg.backgroundColor;
        document.body.style.color = tg.textColor;

        function openScanner() {
            tg.showScanQrPopup({ text: "Escanea la etiqueta" }, function (text) {
                // 1. Cambiamos la interfaz para que el usuario sepa que se está procesando
                document.getElementById('status-title').innerText = "Procesando...";
                document.getElementById('status-desc').innerText = "Enviando código: " + text;

                // guardandp el codigo como pendiente por si no hubiera coneccion
                saveCodeToLocalStorage(text);

                // intentamos enviar al servidor los pendientes por procesar
                fetchCodes();

                // IMPORTANTE: Retornar true aquí cierra el POPUP nativo inmediatamente.
                // Si quieres que el popup se quede abierto hasta que el fetch termine, 
                // podrías retornar false, pero es mejor cerrarlo y mostrar el loader en la webapp.
                return true;
            });
        }

        // Función para guardar en la memoria del teléfono
        function saveCodeToLocalStorage(code) {
            let pending = JSON.parse(localStorage.getItem("{{ $bot }}_pending_scans") || "[]");
            // Opcional: Evitar duplicar el mismo código en la misma sesión offline
            const exists = pending.find(item => item.code === code);
            if (!exists) {
                pending.push({
                    code: code,
                    date: new Date().toISOString()
                });
                localStorage.setItem("{{ $bot }}_pending_scans", JSON.stringify(pending));
            }
        }
        function fetchCodes(callback) {
            let pending = JSON.parse(localStorage.getItem("{{ $bot }}_pending_scans") || "[]");
            if (pending.length > 0) {
                // Mostrar visualmente que estamos trabajando
                document.getElementById('status-title').innerText = "Sincronizando...";
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
                }).then(response => {
                    if (!response.ok) throw new Error('Error en red');
                    return response.json();
                }).then(data => {
                    document.getElementById('status-title').innerText = "✅ ¡Logrado!";
                    document.getElementById('status-desc').innerText = "Se procesaron " + pending.length + " códigos correctamente.";
                    document.getElementById('retry-btn').style.display = "inline-block";

                    // Limpiamos el localStorage INMEDIATAMENTE al recibir éxito
                    localStorage.removeItem("{{ $bot }}_pending_scans");

                    // Vibración de éxito (Feedback háptico)
                    tg.HapticFeedback.notificationOccurred('success');

                    if (callback) callback();

                }).catch(error => {
                    document.getElementById('status-title').innerText = "🔴 Modo Offline";
                    document.getElementById('status-desc').innerText = "Tienes " + pending.length + " códigos guardados. Se enviarán cuando tengas señal.";
                    document.getElementById('retry-btn').style.display = "inline-block";

                    tg.HapticFeedback.notificationOccurred('warning');

                    if (callback) callback();
                });
            }
            else
                if (callback) callback();
        }

        // Ejecutar automáticamente al cargar
        try {
            // intentamos enviar al servidor los pendientes por procesar
            fetchCodes(function () {
                openScanner();
            });
        } catch (e) {
            document.getElementById('status-title').innerText = "Error";
            document.getElementById('status-desc').innerText = "No se pudo acceder a la cámara nativa.";
            document.getElementById('retry-btn').style.display = "inline-block";
        }

        // Si el usuario cierra el popup nativo sin escanear, mostramos el botón de reintento
        tg.onEvent('scanQrPopupClosed', function () {
            document.getElementById('status-title').innerText = "Escáner cerrado";
            //document.getElementById('status-desc').innerText = "No se detectó ningún código.";
            document.getElementById('retry-btn').style.display = "inline-block";
        });

    </script>
</body>

</html>