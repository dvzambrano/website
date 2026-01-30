<?php

namespace Modules\Zentro\Services\Codes;

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrService
{
    /**
     * Genera un código QR en formato SVG (ideal para impresión sin pérdida de calidad)
     * @param string $data El contenido del QR (ej: el fingerprint/internal_ref)
     * @param int $size Tamaño en píxeles. Debe ser como minimo 120px para que la camara lo lea sin esfuerzo
     * @return string Código fuente del SVG
     */
    public function generateSvg(string $data, int $size = 200): string
    {
        // Configuramos el estilo (Tamaño y margen)
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);

        // Retorna el string del SVG listo para mostrar en HTML o guardar
        return $writer->writeString($data);
    }
}