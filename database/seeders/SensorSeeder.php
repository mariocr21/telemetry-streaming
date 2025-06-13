<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Sensor;

class SensorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sensors = [
            // PIDs estándar OBD2 Modo 01
            [
                'pid' => '0x00',
                'name' => 'PIDs Soportados (01-20)',
                'description' => 'Bitmap de PIDs soportados del 01 al 20',
                'category' => 'diagnostics',
                'unit' => 'bit_encoded',
                'data_type' => 'bit_encoded',
                'data_bytes' => 4,
                'is_standard' => true,
                'requires_calculation' => false
            ],
            [
                'pid' => '0x01',
                'name' => 'Monitor Status',
                'description' => 'Estado del monitor de diagnóstico',
                'category' => 'diagnostics',
                'unit' => 'bit_encoded',
                'data_type' => 'bit_encoded',
                'data_bytes' => 4,
                'is_standard' => true,
                'requires_calculation' => false
            ],
            [
                'pid' => '0x02',
                'name' => 'Freeze Frame DTC',
                'description' => 'Código de error que causó el freeze frame',
                'category' => 'diagnostics',
                'unit' => 'dtc_code',
                'data_type' => 'numeric',
                'data_bytes' => 2,
                'is_standard' => true,
                'requires_calculation' => false
            ],
            [
                'pid' => '0x03',
                'name' => 'Fuel System Status',
                'description' => 'Estado del sistema de combustible',
                'category' => 'fuel',
                'unit' => 'bit_encoded',
                'data_type' => 'bit_encoded',
                'data_bytes' => 2,
                'is_standard' => true,
                'requires_calculation' => false
            ],
            [
                'pid' => '0x04',
                'name' => 'Engine Load',
                'description' => 'Carga calculada del motor',
                'category' => 'engine',
                'unit' => '%',
                'data_type' => 'numeric',
                'min_value' => 0,
                'max_value' => 100,
                'data_bytes' => 1,
                'is_standard' => true,
                'requires_calculation' => false,
                'calculation_formula' => 'A * 100 / 255'
            ],
            [
                'pid' => '0x05',
                'name' => 'Engine Coolant Temperature',
                'description' => 'Temperatura del refrigerante del motor',
                'category' => 'engine',
                'unit' => '°C',
                'data_type' => 'numeric',
                'min_value' => -40,
                'max_value' => 215,
                'data_bytes' => 1,
                'is_standard' => true,
                'requires_calculation' => false,
                'calculation_formula' => 'A - 40'
            ],
            [
                'pid' => '0x06',
                'name' => 'Short Term Fuel Trim Bank 1',
                'description' => 'Ajuste de combustible a corto plazo - Banco 1',
                'category' => 'fuel',
                'unit' => '%',
                'data_type' => 'numeric',
                'min_value' => -100,
                'max_value' => 99.22,
                'data_bytes' => 1,
                'is_standard' => true,
                'requires_calculation' => false,
                'calculation_formula' => '(A - 128) * 100 / 128'
            ],
            [
                'pid' => '0x07',
                'name' => 'Long Term Fuel Trim Bank 1',
                'description' => 'Ajuste de combustible a largo plazo - Banco 1',
                'category' => 'fuel',
                'unit' => '%',
                'data_type' => 'numeric',
                'min_value' => -100,
                'max_value' => 99.22,
                'data_bytes' => 1,
                'is_standard' => true,
                'requires_calculation' => false,
                'calculation_formula' => '(A - 128) * 100 / 128'
            ],
            [
                'pid' => '0x08',
                'name' => 'Short Term Fuel Trim Bank 2',
                'description' => 'Ajuste de combustible a corto plazo - Banco 2',
                'category' => 'fuel',
                'unit' => '%',
                'data_type' => 'numeric',
                'min_value' => -100,
                'max_value' => 99.22,
                'data_bytes' => 1,
                'is_standard' => true,
                'requires_calculation' => false,
                'calculation_formula' => '(A - 128) * 100 / 128'
            ],
            [
                'pid' => '0x09',
                'name' => 'Long Term Fuel Trim Bank 2',
                'description' => 'Ajuste de combustible a largo plazo - Banco 2',
                'category' => 'fuel',
                'unit' => '%',
                'data_type' => 'numeric',
                'min_value' => -100,
                'max_value' => 99.22,
                'data_bytes' => 1,
                'is_standard' => true,
                'requires_calculation' => false,
                'calculation_formula' => '(A - 128) * 100 / 128'
            ],
            [
                'pid' => '0x0A',
                'name' => 'Fuel Pressure',
                'description' => 'Presión del combustible',
                'category' => 'fuel',
                'unit' => 'kPa',
                'data_type' => 'numeric',
                'min_value' => 0,
                'max_value' => 765,
                'data_bytes' => 1,
                'is_standard' => true,
                'requires_calculation' => false,
                'calculation_formula' => 'A * 3'
            ],
            [
                'pid' => '0x0B',
                'name' => 'Intake Manifold Pressure',
                'description' => 'Presión absoluta del múltiple de admisión',
                'category' => 'engine',
                'unit' => 'kPa',
                'data_type' => 'numeric',
                'min_value' => 0,
                'max_value' => 255,
                'data_bytes' => 1,
                'is_standard' => true,
                'requires_calculation' => false
            ],
            [
                'pid' => '0x0C',
                'name' => 'Engine RPM',
                'description' => 'Revoluciones por minuto del motor',
                'category' => 'engine',
                'unit' => 'RPM',
                'data_type' => 'numeric',
                'min_value' => 0,
                'max_value' => 16383.75,
                'data_bytes' => 2,
                'is_standard' => true,
                'requires_calculation' => false,
                'calculation_formula' => '((A * 256) + B) / 4'
            ],
            [
                'pid' => '0x0D',
                'name' => 'Vehicle Speed',
                'description' => 'Velocidad del vehículo',
                'category' => 'vehicle',
                'unit' => 'km/h',
                'data_type' => 'numeric',
                'min_value' => 0,
                'max_value' => 255,
                'data_bytes' => 1,
                'is_standard' => true,
                'requires_calculation' => false
            ],
            [
                'pid' => '0x0E',
                'name' => 'Timing Advance',
                'description' => 'Avance de encendido',
                'category' => 'engine',
                'unit' => '°',
                'data_type' => 'numeric',
                'min_value' => -64,
                'max_value' => 63.5,
                'data_bytes' => 1,
                'is_standard' => true,
                'requires_calculation' => false,
                'calculation_formula' => '(A - 128) / 2'
            ],
            [
                'pid' => '0x0F',
                'name' => 'Intake Air Temperature',
                'description' => 'Temperatura del aire de admisión',
                'category' => 'engine',
                'unit' => '°C',
                'data_type' => 'numeric',
                'min_value' => -40,
                'max_value' => 215,
                'data_bytes' => 1,
                'is_standard' => true,
                'requires_calculation' => false,
                'calculation_formula' => 'A - 40'
            ],
            [
                'pid' => '0x10',
                'name' => 'MAF Air Flow Rate',
                'description' => 'Flujo de aire del sensor MAF',
                'category' => 'engine',
                'unit' => 'g/s',
                'data_type' => 'numeric',
                'min_value' => 0,
                'max_value' => 655.35,
                'data_bytes' => 2,
                'is_standard' => true,
                'requires_calculation' => false,
                'calculation_formula' => '((A * 256) + B) / 100'
            ],
            [
                'pid' => '0x11',
                'name' => 'Throttle Position',
                'description' => 'Posición del acelerador',
                'category' => 'engine',
                'unit' => '%',
                'data_type' => 'numeric',
                'min_value' => 0,
                'max_value' => 100,
                'data_bytes' => 1,
                'is_standard' => true,
                'requires_calculation' => false,
                'calculation_formula' => 'A * 100 / 255'
            ],
            [
                'pid' => '0x12',
                'name' => 'Commanded Secondary Air Status',
                'description' => 'Estado del aire secundario comandado',
                'category' => 'emissions',
                'unit' => 'bit_encoded',
                'data_type' => 'bit_encoded',
                'data_bytes' => 1,
                'is_standard' => true,
                'requires_calculation' => false
            ],
            [
                'pid' => '0x13',
                'name' => 'Oxygen Sensors Present',
                'description' => 'Sensores de oxígeno presentes',
                'category' => 'emissions',
                'unit' => 'bit_encoded',
                'data_type' => 'bit_encoded',
                'data_bytes' => 1,
                'is_standard' => true,
                'requires_calculation' => false
            ],
            [
                'pid' => '0x14',
                'name' => 'Oxygen Sensor 1 Voltage',
                'description' => 'Voltaje del sensor de oxígeno 1',
                'category' => 'emissions',
                'unit' => 'V',
                'data_type' => 'numeric',
                'min_value' => 0,
                'max_value' => 1.275,
                'data_bytes' => 2,
                'is_standard' => true,
                'requires_calculation' => false,
                'calculation_formula' => 'A / 200'
            ],
            [
                'pid' => '0x15',
                'name' => 'Oxygen Sensor 2 Voltage',
                'description' => 'Voltaje del sensor de oxígeno 2',
                'category' => 'emissions',
                'unit' => 'V',
                'data_type' => 'numeric',
                'min_value' => 0,
                'max_value' => 1.275,
                'data_bytes' => 2,
                'is_standard' => true,
                'requires_calculation' => false,
                'calculation_formula' => 'A / 200'
            ],
            [
                'pid' => '0x1C',
                'name' => 'OBD Standards',
                'description' => 'Estándares OBD que cumple el vehículo',
                'category' => 'diagnostics',
                'unit' => 'encoded',
                'data_type' => 'numeric',
                'data_bytes' => 1,
                'is_standard' => true,
                'requires_calculation' => false
            ],
            [
                'pid' => '0x1F',
                'name' => 'Run Time Since Engine Start',
                'description' => 'Tiempo de funcionamiento desde el arranque',
                'category' => 'engine',
                'unit' => 'seconds',
                'data_type' => 'numeric',
                'min_value' => 0,
                'max_value' => 65535,
                'data_bytes' => 2,
                'is_standard' => true,
                'requires_calculation' => false,
                'calculation_formula' => '(A * 256) + B'
            ],
            [
                'pid' => '0x2F',
                'name' => 'Fuel Level Input',
                'description' => 'Nivel de combustible en el tanque',
                'category' => 'fuel',
                'unit' => '%',
                'data_type' => 'numeric',
                'min_value' => 0,
                'max_value' => 100,
                'data_bytes' => 1,
                'is_standard' => true,
                'requires_calculation' => false,
                'calculation_formula' => 'A * 100 / 255'
            ],
            [
                'pid' => '0x42',
                'name' => 'Battery Voltage',
                'description' => 'Voltaje de la batería del vehículo',
                'category' => 'electrical',
                'unit' => 'V',
                'data_type' => 'numeric',
                'min_value' => 0,
                'max_value' => 25,
                'data_bytes' => 4,
                'is_standard' => false,
                'requires_calculation' => false
            ],
            [
                'pid' => 'lat',
                'name' => 'Latitude',
                'description' => 'Latitud GPS reportada por el dispositivo OBD2',
                'category' => 'gps',
                'unit' => '°',
                'data_type' => 'numeric',
                'min_value' => -90,
                'max_value' => 90,
                'data_bytes' => 8,
                'is_standard' => false,
                'requires_calculation' => false
            ],
            [
                'pid' => 'lng',
                'name' => 'Longitude',
                'description' => 'Longitud GPS reportada por el dispositivo OBD2',
                'category' => 'gps',
                'unit' => '°',
                'data_type' => 'numeric',
                'min_value' => -180,
                'max_value' => 180,
                'data_bytes' => 8,
                'is_standard' => false,
                'requires_calculation' => false
            ],
            [
                'pid' => 'vel_kmh',
                'name' => 'GPS Speed',
                'description' => 'Velocidad calculada por GPS',
                'category' => 'gps',
                'unit' => 'km/h',
                'data_type' => 'numeric',
                'min_value' => 0,
                'max_value' => 500,
                'data_bytes' => 4,
                'is_standard' => false,
                'requires_calculation' => false
            ],
            [
                'pid' => 'alt_m',
                'name' => 'Altitude',
                'description' => 'Altitud GPS sobre el nivel del mar',
                'category' => 'gps',
                'unit' => 'm',
                'data_type' => 'numeric',
                'min_value' => -500,
                'max_value' => 10000,
                'data_bytes' => 4,
                'is_standard' => false,
                'requires_calculation' => false
            ],
            [
                'pid' => 'rumbo',
                'name' => 'Heading',
                'description' => 'Rumbo/dirección del movimiento según GPS',
                'category' => 'gps',
                'unit' => '°',
                'data_type' => 'numeric',
                'min_value' => 0,
                'max_value' => 359.99,
                'data_bytes' => 4,
                'is_standard' => false,
                'requires_calculation' => false
            ],
            [
                'pid' => 'FC_LH',
                'name' => 'Fuel Consumption',
                'description' => 'Consumo en litros por hora',
                'category' => 'fuel',
                'unit' => 'L/h',
                'data_type' => 'numeric',
                'min_value' => 0,
                'max_value' => null,
                'data_bytes' => 4,
                'is_standard' => false,
                'requires_calculation' => true,
            ],
            [
                'pid' => 'MI_KL',
                'name' => 'Fuel Efficiency',
                'description' => 'Rendimiento en kilómetros por litro',
                'category' => 'fuel',
                'unit' => 'km/L',
                'data_type' => 'numeric',
                'min_value' => 0,
                'max_value' => null,
                'data_bytes' => 4,
                'is_standard' => false,
                'requires_calculation' => true,
            ]

        ];

        foreach ($sensors as $sensorData) {
            Sensor::updateOrCreate(
                ['pid' => $sensorData['pid']], // Buscar por PID
                $sensorData // Crear o actualizar con estos datos
            );
        }

        $this->command->info('Sensores OBD2 estándar creados/actualizados exitosamente.');
    }
}
