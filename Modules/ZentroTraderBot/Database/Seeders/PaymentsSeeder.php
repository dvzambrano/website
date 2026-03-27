<?php
namespace Modules\ZentroTraderBot\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\ZentroTraderBot\Entities\Paymentmethods;
use Modules\ZentroTraderBot\Entities\Currencies;

class PaymentsSeeder extends Seeder
{
    public function run()
    {
        // 1. DEFINICIÓN DE MONEDAS
        $currencies = [
            ['code' => 'USD', 'name' => 'Dólar Estadounidense', 'symbol' => '$'],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€'],
            ['code' => 'CAD', 'name' => 'Dólar Canadiense', 'symbol' => '$'],
            ['code' => 'CUP', 'name' => 'Peso Cubano', 'symbol' => '₱'],
            ['code' => 'MLC', 'name' => 'MLC (Cuba)', 'symbol' => '$'],
            ['code' => 'MXN', 'name' => 'Peso Mexicano', 'symbol' => '$'],
            ['code' => 'COP', 'name' => 'Peso Colombiano', 'symbol' => '$'],
            ['code' => 'ARS', 'name' => 'Peso Argentino', 'symbol' => '$'],
            ['code' => 'BRL', 'name' => 'Real Brasileño', 'symbol' => 'R$'],
            ['code' => 'CLP', 'name' => 'Peso Chileno', 'symbol' => '$'],
            ['code' => 'PEN', 'name' => 'Sol Peruano', 'symbol' => 'S/'],
            ['code' => 'VES', 'name' => 'Bolívar Soberano', 'symbol' => 'Bs'],
        ];

        foreach ($currencies as $c) {
            Currencies::updateOrCreate(['code' => $c['code']], $c);
        }

        // 2. DEFINICIÓN DE MÉTODOS DE PAGO (GENÉRICOS)
        $methods = [
            // Norteamérica & Global
            ['name' => 'Zelle', 'identifier' => 'zelle', 'icon' => '🟣'],
            ['name' => 'Cash App', 'identifier' => 'cashapp', 'icon' => '🟢'],
            ['name' => 'Interac e-Transfer', 'identifier' => 'interac', 'icon' => '🇨🇦'],
            ['name' => 'PayPal', 'identifier' => 'paypal', 'icon' => '🔵'],

            // Iberoamérica (España & LATAM General)
            ['name' => 'Bizum', 'identifier' => 'bizum', 'icon' => '📲'],
            ['name' => 'Revolut', 'identifier' => 'revolut', 'icon' => '💳'],
            ['name' => 'Transferencia Bancaria', 'identifier' => 'bank_transfer', 'icon' => '🏦'],
            ['name' => 'Binance Pay (USDT)', 'identifier' => 'binance_pay', 'icon' => '🔶'],

            // Regionales Específicos
            ['name' => 'Enzona', 'identifier' => 'enzona', 'icon' => '🇨🇺'],
            ['name' => 'Transfermóvil', 'identifier' => 'transfermovil', 'icon' => '📱'],
            ['name' => 'SPEI', 'identifier' => 'spei', 'icon' => '🇲🇽'],
            ['name' => 'NEQUI', 'identifier' => 'nequi', 'icon' => '🇨🇴'],
            ['name' => 'Daviplata', 'identifier' => 'daviplata', 'icon' => '🔴'],
            ['name' => 'PIX', 'identifier' => 'pix', 'icon' => '🇧🇷'],
            ['name' => 'Pago Móvil', 'identifier' => 'pago_movil', 'icon' => '🇻🇪'],
            ['name' => 'Mercado Pago', 'identifier' => 'mercadopago', 'icon' => '🤝'],
        ];

        foreach ($methods as $m) {
            Paymentmethods::updateOrCreate(['identifier' => $m['identifier']], $m);
        }

        // 3. RELACIONES Y LÍMITES (PIVOT)
        $this->relate();
    }

    private function relate()
    {
        // Helper para obtener IDs rápido
        $curr = fn($code) => Currencies::where('code', $code)->first()->id;
        $meth = fn($slug) => Paymentmethods::where('identifier', $slug)->first()->id;

        $relations = [
            // USA (USD)
            ['c' => 'USD', 'm' => 'zelle', 'min' => 20, 'max' => 5000],
            ['c' => 'USD', 'm' => 'cashapp', 'min' => 10, 'max' => 2000],
            ['c' => 'USD', 'm' => 'paypal', 'min' => 50, 'max' => 1000],

            // CANADA (CAD)
            ['c' => 'CAD', 'm' => 'interac', 'min' => 10, 'max' => 3000],
            ['c' => 'CAD', 'm' => 'bank_transfer', 'min' => 100, 'max' => 10000],

            // ESPAÑA / EUROPA (EUR)
            ['c' => 'EUR', 'm' => 'bizum', 'min' => 5, 'max' => 500],
            ['c' => 'EUR', 'm' => 'revolut', 'min' => 10, 'max' => 10000],
            ['c' => 'EUR', 'm' => 'bank_transfer', 'min' => 50, 'max' => 20000],

            // CUBA (CUP / MLC)
            ['c' => 'CUP', 'm' => 'transfermovil', 'min' => 500, 'max' => 500000],
            ['c' => 'CUP', 'm' => 'enzona', 'min' => 100, 'max' => 100000],
            ['c' => 'MLC', 'm' => 'bank_transfer', 'min' => 10, 'max' => 5000],

            // MÉXICO (MXN)
            ['c' => 'MXN', 'm' => 'spei', 'min' => 100, 'max' => 50000],
            ['c' => 'MXN', 'm' => 'mercadopago', 'min' => 50, 'max' => 20000],

            // COLOMBIA (COP)
            ['c' => 'COP', 'm' => 'nequi', 'min' => 10000, 'max' => 5000000],
            ['c' => 'COP', 'm' => 'daviplata', 'min' => 10000, 'max' => 5000000],

            // BRASIL (BRL)
            ['c' => 'BRL', 'm' => 'pix', 'min' => 1, 'max' => 50000],
        ];

        foreach ($relations as $rel) {
            $currency = Currencies::find($curr($rel['c']));
            $currency->paymentmethods()->syncWithoutDetaching([
                $meth($rel['m']) => [
                    'min_limit' => $rel['min'],
                    'max_limit' => $rel['max'],
                    'instructions' => "Operación estándar para {$rel['m']}",
                    'is_active' => true,
                ]
            ]);
        }
    }
}
