<?php

namespace Modules\ZentroPackageBot\Http\Controllers;

use App\Http\Controllers\JsonsController;
use Modules\Laravel\Services\Codes\QrService;
use Modules\ZentroPackageBot\Entities\Packages;

class PackagesController extends JsonsController
{
    public function printLabels()
    {
        $qrService = new QrService();

        $packages = $this->get(Packages::class, "id", ">", "0");

        return view("zentropackagebot::labels.print", ["qrService" => $qrService, "packages" => $packages->toArray()]);
    }
}
