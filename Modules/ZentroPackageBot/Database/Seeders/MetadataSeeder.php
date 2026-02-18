<?php
namespace Modules\ZentroPackageBot\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Laravel\Entities\Metadatas;

class MetadataSeeder extends Seeder
{

    public function run(): void
    {
        //  Seeders to this Module
        // -------------------------------------------------------------------------------

        Metadatas::create([
            'name' => 'app_zentropackagebot_scanner_gpsrequired',
            'value' => '1',
            'comment' => 'Definicion de obligatoriedad del GPS al usar el escaner: 1 obligatorio, 0 opcional, -1 innecesario',
            'metadatatype' => 1,
            'is_visible' => 1,
        ]);
    }
}
