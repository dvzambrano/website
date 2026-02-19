<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Modules\ZentroTraderBot\Http\Controllers\ZeroExController;

class ZeroExControllerTest extends TestCase
{
    /** @test */
    public function it_can_be_instantiated()
    {
        $controller = new ZeroExController();
        $this->assertInstanceOf(ZeroExController::class, $controller);
    }

    // Agrega aquí pruebas de métodos públicos específicos si los hay
}
