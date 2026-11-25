<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * php artisan peaje:exportar-bd [--salida=storage/exports] [--sin-tokens]
 *
 * Exporta la BD (SQLite, MySQL o PostgreSQL) a:
 *   - peaje_bd_<fecha>.sql  : INSERTs portables (sin depender de mysqldump/sqlite3)
 *   - peaje_bd_<fecha>.json : documento JSON por tabla (BD "documental" exportable para Drive)
 * Los valores secretos (configuraciones cifradas, tokens Sanctum, sesiones) NUNCA se exportan.
 */
class ExportarBd extends Command
{
    protected $signature = 'peaje:exportar-bd {--salida= : Carpeta destino (por defecto storage/exports)}';

    protected $description = 'Exporta la base de datos del peaje a SQL y JSON para la carpeta de Google Drive';

    public const TABLAS = ['usuarios', 'casetas', 'tarifas', 'vehiculos', 'pasos', 'alertas', 'configuraciones', 'eventos_hardware'];

    public function handle(): int
    {
        $dir = $this->option('salida') ?: storage_path('exports');
        File::ensureDirectoryExists($dir);
        $sello = now()->format('Ymd_His');
        $sqlPath = $dir.DIRECTORY_SEPARATOR."peaje_bd_{$sello}.sql";
        $jsonPath = $dir.DIRECTORY_SEPARATOR."peaje_bd_{$sello}.json";
        $pdo = DB::connection()->getPdo();
        // Comillas de identificador del motor de origen: `x` en MySQL, "x" en PostgreSQL/SQLite
        $id = fn (string $c) => DB::getDriverName() === 'mysql' ? "`{$c}`" : '"'.$c.'"';

        $sql = ['-- Sistema Inteligente de Control de Peaje · exportación '.now()->toDateTimeString(),
            '-- Motor de origen: '.DB::getDriverName(), '-- Secretos omitidos (configuraciones cifradas, tokens, sesiones)', ''];
        $json = ['exportado' => now()->toIso8601String(), 'motor' => DB::getDriverName(), 'tablas' => []];

        foreach (self::TABLAS as $tabla) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            // Columnas booleanas: SQLite/MySQL las guardan como 0/1; se exportan como TRUE/FALSE para que
            // el .sql cargue igual en PostgreSQL, MySQL o SQLite (p. ej. local SQLite -> Render PostgreSQL)
            $booleanas = collect(Schema::getColumns($tabla))
                ->filter(fn ($c) => in_array(strtolower($c['type_name']), ['bool', 'boolean'], true) || strtolower($c['type']) === 'tinyint(1)')
                ->pluck('name')->flip();
            $filas = DB::table($tabla)->orderBy('id')->get()
                ->map(fn ($f) => collect((array) $f)->map(fn ($v, $c) => $v !== null && $booleanas->has($c) ? (bool) $v : $v)->all())
                ->all();
            if ($tabla === 'usuarios') {
                $filas = array_map(fn ($f) => array_diff_key($f, ['remember_token' => 1]), $filas);
            }
            if ($tabla === 'configuraciones') {
                $filas = array_map(fn ($f) => $f['es_secreto'] ? array_merge($f, ['valor' => '***OMITIDO***']) : $f, $filas);
            }
            $json['tablas'][$tabla] = $filas;
            $sql[] = "-- Tabla {$tabla}: ".count($filas).' filas';
            foreach ($filas as $f) {
                $cols = implode(', ', array_map($id, array_keys($f)));
                $vals = implode(', ', array_map(fn ($v) => $this->valorSql($v, $pdo), $f));
                $sql[] = 'INSERT INTO '.$id($tabla)." ({$cols}) VALUES ({$vals});";
            }
            $sql[] = '';
            $this->line(sprintf('  %-18s %5d filas', $tabla, count($filas)));
        }

        File::put($sqlPath, implode("\n", $sql)."\n");
        File::put($jsonPath, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->info("Exportado: {$sqlPath}");
        $this->info("Exportado: {$jsonPath}");
        $this->comment('Suba estos archivos a la carpeta de Google Drive (recursos/base_de_datos).');

        return self::SUCCESS;
    }

    /** Literal SQL portable: NULL, números, TRUE/FALSE (pgsql devuelve bool nativo) o texto escapado. */
    private function valorSql(mixed $v, \PDO $pdo): string
    {
        return match (true) {
            $v === null => 'NULL',
            is_bool($v) => $v ? 'TRUE' : 'FALSE',
            is_int($v), is_float($v) => (string) $v,
            default => $pdo->quote((string) $v),
        };
    }
}
