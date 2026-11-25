<?php

namespace Database\Seeders;

use App\Models\Vehiculo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * 22 vehículos bolivianos registrados. 18 provienen de las fotos reales del curso
 * (database/seeders/evidencias/manifest.csv) y 4 completan las categorías bus / camión multieje.
 *
 * Escenarios para el agente y las alertas:
 *  - con saldo y pasos históricos  -> estado 1 "registrada y con paso"
 *  - registrados sin paso          -> estado 2 "registrada sin paso"
 *  - registrados sin saldo         -> alerta "sin_saldo"
 *  - placas de fotos NO registradas (1917YBP, 6248RFY, 6480ZDE) -> estado 3 "identificada no registrada"
 */
class VehiculoSeeder extends Seeder
{
    public const VEHICULOS = [
        // placa, propietario, ci, categoria, ejes, marca, modelo, color, anio, saldo, foto
        ['1151DKL', 'María Quispe Mamani', '4789123 LP', 'auto', 2, 'Volkswagen', 'Parati', 'Blanco', 2004, 120.00, '01_1151DKL.jpg'],
        ['1231BTN', 'Juan Carlos Choque Limachi', '5123456 LP', 'auto', 2, 'Toyota', 'Corolla', 'Blanco', 1998, 85.50, '02_1231BTN.jpg'],
        ['1701NAC', 'Rosa Mamani Condori', '3987654 LP', 'auto', 2, 'Mitsubishi', 'Space Wagon', 'Negro', 2001, 60.00, '04_1701NAC.jpg'],
        ['5312FAE', 'Luis Fernando Apaza', '6011223 LP', 'auto', 2, 'Suzuki', 'Celerio', 'Gris', 2019, 45.00, '05_5312FAE.jpg'],
        ['1483AXF', 'Carmen Ticona Huanca', '4455667 LP', 'auto', 2, 'Toyota', 'Starlet', 'Plata', 1997, 32.00, '06_1483AXF.jpg'],
        ['1985LNH', 'Sindicato Mixto de Transporte "Línea 205"', '1020304050', 'minibus', 2, 'Toyota', 'Hiace', 'Rojo', 1995, 250.00, '08_1985LNH.jpg'],
        ['1078LPS', 'Radio Taxi "Illimani" – Pedro Huanca', '3344556 LP', 'auto', 2, 'Toyota', 'Corolla', 'Blanco', 1996, 75.00, '10_1078LPS.jpg'],
        ['4472ASC', 'Andrea Gutiérrez Salinas', '7788990 SC', 'auto', 2, 'Audi', 'Q5', 'Negro', 2015, 300.00, '11_4472ASC.jpg'],
        ['1534PHK', 'Hospital Arco Iris (ambulancia)', '1029384756', 'minibus', 2, 'Toyota', 'Hiace Ambulancia', 'Blanco', 2008, 500.00, '12_1534PHK.jpg'],
        ['1640SSU', 'Ricardo Flores Callisaya', '5566778 LP', 'auto', 2, 'Toyota', 'Corolla', 'Plata', 2000, 54.00, '15_1640SSU.jpg'],
        ['1985CXX', 'Distribuidora Paceña S.R.L.', '2030405060', 'camion', 2, 'Nissan', 'Cabstar', 'Blanco', 2012, 400.00, '14_1985CXX.jpg'],
        ['2481KLM', 'Transportes Andinos S.A.', '3040506070', 'camion', 3, 'Volvo', 'FM 370', 'Rojo', 2017, 800.00, null],
        ['3820HTD', 'Flota "Trans Copacabana"', '4050607080', 'bus', 2, 'Mercedes-Benz', 'O-500', 'Azul', 2016, 600.00, null],
        // Registrados SIN paso
        ['194UXI', 'Hugo Paredes Ramos', '1234567 LP', 'auto', 2, 'Volkswagen', 'Escarabajo', 'Crema', 1978, 40.00, '16_194UXI.jpg'],
        ['5592UTF', 'Valeria Rojas Cuéllar', '8899001 LP', 'auto', 2, 'JAC', 'JS4', 'Rojo', 2023, 150.00, '18_5592UTF.jpg'],
        ['1344FTL', 'Jorge Antonio Vargas', '2233445 CB', 'auto', 2, 'Toyota', 'Land Cruiser Prado', 'Plata', 2006, 210.00, '19_1344FTL.jpg'],
        ['4105GRB', 'Flota "El Dorado"', '5060708090', 'bus', 3, 'Volvo', 'B420R', 'Blanco', 2019, 900.00, null],
        ['2950KPL', 'Cisternas del Altiplano S.A.', '6070809010', 'camion', 5, 'Scania', 'R450', 'Blanco', 2020, 1500.00, null],
        // Registrados SIN SALDO (generan alerta "sin_saldo")
        ['1703XFY', 'Martha Céspedes Ayala', '3456789 LP', 'auto', 2, 'Toyota', 'Corona', 'Gris', 1994, 2.00, '20_1703XFY.jpg'],
        ['1210GIL', 'Ernesto Gil Laura', '4567890 LP', 'auto', 2, 'Toyota', 'Corolla', 'Azul', 1993, 0.00, '21_1210GIL.jpg'],
        ['4472IUU', 'Cooperativa de Transporte "Villa Fátima"', '7080901020', 'minibus', 2, 'King Long', 'Kingo', 'Plata', 2018, 1.50, '17_4472IUU.jpg'],
        ['6652ZNC', 'Pollos "Chikn Fried" (reparto)', '8090102030', 'camion', 2, 'Foton', 'Aumark', 'Blanco', 2021, 0.00, '22_6652ZNC.jpg'],
    ];

    public function run(): void
    {
        foreach (self::VEHICULOS as [$placa, $prop, $ci, $cat, $ejes, $marca, $modelo, $color, $anio, $saldo, $foto]) {
            Vehiculo::updateOrCreate(['placa' => $placa], [
                'propietario' => $prop, 'ci_nit' => $ci, 'categoria' => $cat, 'ejes' => $ejes, 'marca' => $marca,
                'modelo' => $modelo, 'color' => $color, 'anio' => $anio, 'saldo' => $saldo, 'activo' => true,
                'telefono' => '7'.substr(preg_replace('/\D/', '', $ci).'0000000', 0, 7),
                'foto_referencia' => $foto ? self::copiarFoto($foto) : null,
                'observaciones' => $foto ? 'Foto real del curso (registro base / evidencia inicial).' : 'Registro complementario de categoría.',
            ]);
        }
    }

    /** Copia la foto del repositorio al disco público (storage/app/public/evidencias/seed). */
    public static function copiarFoto(string $archivo): ?string
    {
        $origen = database_path('seeders/evidencias/'.$archivo);
        if (! is_file($origen)) {
            return null;
        }
        $destino = 'evidencias/seed/'.$archivo;
        if (! Storage::disk('public')->exists($destino)) {
            Storage::disk('public')->put($destino, file_get_contents($origen));
        }

        return $destino;
    }
}
