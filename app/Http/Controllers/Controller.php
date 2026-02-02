<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

use App\Models\MetadataTypes;
use App\Models\Metadatas;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function __construct()
    {
        // GETTING & SHARING METADATAS TO ALL VIEWS
        $metadatas = Metadatas::with("metadatatypes")->get();

        foreach ($metadatas as $metadata) {
            // Ahora el tipo ya viene cargado en el objeto, no hay que hacer consulta extra
            $typeCode = $metadata->metadatatypes->code ?? "";
            $name = str_replace("_", ".", $metadata->name);
            \Config::set($typeCode . "." . $name, $metadata->value);
        }
    }
}

