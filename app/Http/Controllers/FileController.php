<?php

namespace App\Http\Controllers;

use Dvzambrano\Filesystem\Services\FilesystemService;

class FileController extends Controller
{
    public static $AUTODESTROY_DIR = "/autodestroy";

    public function renderAndDestroy($format, $name)
    {
        app(FilesystemService::class)->deleteOldTempFiles(public_path() . self::$AUTODESTROY_DIR);

        $report_path = public_path() . self::$AUTODESTROY_DIR . "/{$name}.{$format}";

        if (!file_exists($report_path)) {
            return response()->json([
                "code"  => "404",
                "error" => "El reporte {$name} no existe.",
            ], 404);
        }

        return response()->download($report_path);
    }

    // Las rutas /logs/* (visor, export, clear, búsqueda) ahora las sirve
    // dvzambrano/filesystem directamente — ver su propio LogViewerController.

    public static function getFileNameAsUnixTime(string $extension, int $amount, string $period = 'SECONDS'): string
    {
        return app(FilesystemService::class)->generateTimestampFilename($extension, $amount, $period);
    }
}
