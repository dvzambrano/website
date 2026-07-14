<?php

namespace App\Http\Controllers;

use Dvzambrano\Filesystem\Services\FilesystemService;
use Dvzambrano\Filesystem\Services\LogService;

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

    public function readLog($type = null, $amount = 10, $log = 'laravel')
    {
        $entries = app(LogService::class)->read($log, $type ?: null, (int) $amount);

        // Pretty-print + sin escapar unicode/slashes: los mensajes llevan emoji y
        // acentos, y así se leen directamente en el navegador en vez de como \uXXXX.
        return response()->json($entries, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function exportLog($log = 'laravel')
    {
        $path = app(LogService::class)->path($log);

        if (!file_exists($path)) {
            return response()->json(['error' => "El log \"{$log}\" no existe."], 404);
        }

        return response()->download($path, basename($path));
    }

    public function clearLog($log = 'laravel')
    {
        app(LogService::class)->clear($log);
        return response()->json(['success' => true, 'message' => 'Log limpiado correctamente.']);
    }

    public function searchInLog($key, $searchValue, $log = 'storage.log', $exactMatch = true)
    {
        return app(LogService::class)->search($key, $searchValue, $log, (bool) $exactMatch);
    }

    public static function getFileNameAsUnixTime(string $extension, int $amount, string $period = 'SECONDS'): string
    {
        return app(FilesystemService::class)->generateTimestampFilename($extension, $amount, $period);
    }
}
