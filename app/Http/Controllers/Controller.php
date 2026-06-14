<?php

namespace App\Http\Controllers;

use Dvzambrano\Metadata\Services\MetadataService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function __construct()
    {
        try {
            app(MetadataService::class)->loadIntoConfig();
        } catch (\Throwable $e) {
            // DB not available (migrations pending or CLI context)
        }
    }
}
