<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Hoja de Etiquetas</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            background: #f4f4f4;
        }

        /* Contenedor de la cuadrícula */
        .label-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            /* 2 etiquetas por fila */
            gap: 15px;
            max-width: 800px;
            margin: auto;
        }

        /* Estilo de cada etiqueta */
        .label-card {
            background: white;
            border: 2px solid #000;
            padding: 15px;
            display: flex;
            align-items: center;
            border-radius: 8px;
            page-break-inside: avoid;
        }

        .qr-section {
            flex: 1;
        }

        .info-section {
            flex: 2;
            padding-left: 15px;
            border-left: 1px dashed #ccc;
        }

        .recipient {
            font-weight: bold;
            font-size: 14px;
            text-transform: uppercase;
        }

        .awb {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            margin: 5px 0;
        }

        .fingerprint {
            color: #666;
            font-size: 10px;
        }

        .dest {
            float: right;
            background: #000;
            color: #fff;
            padding: 2px 8px;
            font-weight: bold;
        }

        /* Estilos para impresión */
        @media print {
            body {
                background: white;
                padding: 0;
            }

            .no-print {
                display: none;
            }

            .label-grid {
                gap: 10px;
            }

            .label-card {
                border: 1px solid #eee;
            }

            /* Ahorro de tinta */
        }
    </style>
</head>

<body>

    <div class="no-print" style="text-align:center; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor:pointer;">
            🖨️ Imprimir Etiquetas
        </button>
    </div>

    <div class="label-grid">
        @foreach($packages as $pkg)
            <div class="label-card">
                <div class="qr-section">
                    {!! $qrService->generateSvg($pkg['fingerprint'], 120) !!}
                </div>
                <div class="info-section">
                    <span class="dest">{{ $pkg['destination_code'] }}</span>
                    <div class="fingerprint">ID: {{ $pkg['fingerprint'] }}</div>
                    <div class="awb">AWB: {{ $pkg['awb'] }}</div>
                    <div class="recipient">{{ $pkg['recipient_name'] }}</div>
                    <div class="fingerprint">{{ $pkg['full_address'] }}</div>
                    <div class="fingerprint">{{ $pkg['recipient_phone'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

</body>

</html>