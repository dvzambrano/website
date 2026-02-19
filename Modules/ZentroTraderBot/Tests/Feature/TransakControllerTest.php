<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Modules\ZentroTraderBot\Http\Controllers\TransakController;

class TransakControllerTest extends TestCase
{
    /** @test */
    public function it_can_be_instantiated()
    {
        $controller = new TransakController();
        $this->assertInstanceOf(TransakController::class, $controller);
    }

    // Agrega aquí pruebas de métodos públicos específicos si los hay
}
