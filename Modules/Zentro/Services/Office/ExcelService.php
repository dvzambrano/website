<?php

namespace Modules\Zentro\Services\Office;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ExcelService
{
    /**
     * Importador dinámico de Excel
     * @param string $path Ruta del archivo
     * @param array $columnsToRead Letras de las columnas (ej: ['A', 'C', 'D'])
     * @param int $headerRow Fila donde están los títulos (ej: 2)
     * @param string|null $sheetName Nombre de la hoja (opcional)
     */
    public static function import(
        string $path,
        array $columnsToRead,
        int $headerRow,
        string $sheetName = null,
        array $fieldMapping = [],
        array $seedFields = [],
        string $seedName = "fingerprint",
    ) {
        $spreadsheet = IOFactory::load($path);
        $sheet = $sheetName ? $spreadsheet->getSheetByName($sheetName) : $spreadsheet->getActiveSheet();

        $highestRow = $sheet->getHighestRow();
        $data = [];
        $headers = [];

        // 1. Mapear encabezados con traducción opcional
        foreach ($columnsToRead as $column) {
            $cellValue = (string) $sheet->getCell($column . $headerRow)->getValue();

            // Limpieza de encabezado (minúsculas, sin tildes, sin caracteres especiales)
            $header = strtolower(trim($cellValue));
            $header = strtr(
                mb_convert_encoding($header, 'ISO-8859-1', 'UTF-8'),
                mb_convert_encoding('áéíóúñ', 'ISO-8859-1', 'UTF-8'),
                'aeioun'
            );
            $cleanHeader = preg_replace('/[^a-z0-9]/', '', $header);

            // Si existe en el mapeo, usamos el nombre de la DB, si no, el nombre limpio
            $headers[$column] = $fieldMapping[$cleanHeader] ?? $cleanHeader;
        }

        // 2. Procesar filas
        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $rowData = [];
            $hasContent = false;

            foreach ($columnsToRead as $column) {
                $cellValue = (string) $sheet->getCell($column . $row)->getFormattedValue();

                // Limpieza de espacios dobles y extremos
                $cellValue = preg_replace('/\s+/', ' ', $cellValue);
                $cellValue = trim($cellValue);

                $rowData[$headers[$column]] = $cellValue;
                if (!empty($cellValue))
                    $hasContent = true;
            }

            if ($hasContent) {
                // --- GENERACIÓN DEL SEED DINÁMICO ---
                $rowData[$seedName] = null; // Por defecto vacío

                if (!empty($seedFields)) {
                    $seedString = "";
                    foreach ($seedFields as $field) {
                        // Concatenamos solo si el campo existe en los datos procesados
                        $seedString .= $rowData[$field] ?? '';
                    }

                    if (!empty($seedString)) {
                        // Generamos un hash de 12 caracteres basado en los campos elegidos
                        $rowData[$seedName] = strtoupper(substr(md5($seedString), 0, 12));
                    }
                }

                $data[] = $rowData;
            }
        }

        return $data;
    }
}