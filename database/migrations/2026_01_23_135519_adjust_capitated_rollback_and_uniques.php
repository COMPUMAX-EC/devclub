<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fecha centinela para filas "vigentes" o no-rollback.
     */
    private string $sentinelDateTime = '2000-01-01 00:00:00';

    public function up(): void
    {
        // 1) Normalizar datos previos en las tres tablas con rollback
        $this->normalizeStatusAndRolledBackAt('capitados_product_insureds');
        $this->normalizeStatusAndRolledBackAt('capitados_contracts');
        $this->normalizeStatusAndRolledBackAt('capitados_monthly_records');

        // 2) Hacer status NOT NULL DEFAULT 'active' y rolled_back_at NOT NULL DEFAULT '2000-01-01 00:00:00'

        // capitados_product_insureds
        DB::statement("
            ALTER TABLE `capitados_product_insureds`
            MODIFY `status` VARCHAR(32) NOT NULL DEFAULT 'active'
        ");
        DB::statement("
            ALTER TABLE `capitados_product_insureds`
            MODIFY `rolled_back_at` DATETIME NOT NULL DEFAULT '{$this->sentinelDateTime}'
        ");

        // capitados_contracts
        DB::statement("
            ALTER TABLE `capitados_contracts`
            MODIFY `status` VARCHAR(32) NOT NULL DEFAULT 'active'
        ");
        DB::statement("
            ALTER TABLE `capitados_contracts`
            MODIFY `rolled_back_at` DATETIME NOT NULL DEFAULT '{$this->sentinelDateTime}'
        ");

        // capitados_monthly_records
        DB::statement("
            ALTER TABLE `capitados_monthly_records`
            MODIFY `status` VARCHAR(32) NOT NULL DEFAULT 'active'
        ");
        DB::statement("
            ALTER TABLE `capitados_monthly_records`
            MODIFY `rolled_back_at` DATETIME NOT NULL DEFAULT '{$this->sentinelDateTime}'
        ");

        // 3) Índices UNIQUE basados en (status, rolled_back_at)

        // 3.a) capitados_monthly_records:
        //     - quitamos el índice viejo que te está reventando
        //     - creamos el nuevo UNIQUE(product_id, person_id, coverage_month, status, rolled_back_at)
        $this->dropIndexIfExists('capitados_monthly_records', 'capitados_monthly_records_unique');

        Schema::table('capitados_monthly_records', function (Blueprint $table) {
            $table->unique(
                ['product_id', 'person_id', 'coverage_month', 'status', 'rolled_back_at'],
                'capitated_monthly_records_prod_person_month_status_rb_unique'
            );
        });

        // 3.b) capitados_product_insureds:
        //     - localizamos cualquier UNIQUE que use product_id + document_number
        //       (por ejemplo, el clásico UNIQUE(product_id, document_number) u otro similar)
        //     - lo eliminamos
        //     - creamos el nuevo UNIQUE(product_id, document_number, status, rolled_back_at)
        $this->dropUniqueIndexContainingColumns(
            'capitados_product_insureds',
            ['product_id', 'document_number']
        );

        Schema::table('capitados_product_insureds', function (Blueprint $table) {
            $table->unique(
                ['product_id', 'document_number', 'status', 'rolled_back_at'],
                'capitated_product_insureds_prod_doc_status_rb_unique'
            );
        });

        // Para capitados_contracts en esta migración solo normalizo status/rolled_back_at.
        // Si más adelante queremos un UNIQUE similar, conviene revisar primero datos y
        // reglas de continuidad (múltiples contratos expirados).
    }

    public function down(): void
    {
        // Quitar UNIQUE nuevos
        Schema::table('capitados_monthly_records', function (Blueprint $table) {
            $table->dropUnique('capitated_monthly_records_prod_person_month_status_rb_unique');
        });

        Schema::table('capitados_product_insureds', function (Blueprint $table) {
            $table->dropUnique('capitated_product_insureds_prod_doc_status_rb_unique');
        });

        // Volver a dejar status/rolled_back_at como NULLable (ajusta si tu migración anterior usaba otro default)

        DB::statement("
            ALTER TABLE `capitados_product_insureds`
            MODIFY `status` VARCHAR(32) NULL DEFAULT NULL
        ");
        DB::statement("
            ALTER TABLE `capitados_product_insureds`
            MODIFY `rolled_back_at` DATETIME NULL DEFAULT NULL
        ");

        DB::statement("
            ALTER TABLE `capitados_contracts`
            MODIFY `status` VARCHAR(32) NULL DEFAULT NULL
        ");
        DB::statement("
            ALTER TABLE `capitados_contracts`
            MODIFY `rolled_back_at` DATETIME NULL DEFAULT NULL
        ");

        DB::statement("
            ALTER TABLE `capitados_monthly_records`
            MODIFY `status` VARCHAR(32) NULL DEFAULT NULL
        ");
        DB::statement("
            ALTER TABLE `capitados_monthly_records`
            MODIFY `rolled_back_at` DATETIME NULL DEFAULT NULL
        ");

        // NOTA: no recreo aquí los UNIQUE antiguos porque su definición exacta puede variar
        // según el entorno. Si necesitas un down 100% simétrico, habría que reponerlos a mano.
    }

    /**
     * Normaliza status/rolled_back_at para una tabla concreta.
     *
     * Reglas:
     * - status NULL o vacío => 'active'
     * - filas status='active' sin rolled_back_at => centinela 2000-01-01 00:00:00
     * - filas status='rolled_back' sin rolled_back_at => COALESCE(updated_at, created_at, NOW())
     */
    private function normalizeStatusAndRolledBackAt(string $table): void
    {
        // status NULL o vacío -> 'active'
        DB::table($table)
            ->where(function ($q) {
                $q->whereNull('status')
                  ->orWhere('status', '');
            })
            ->update(['status' => 'active']);

        // Activos sin fecha de rollback -> centinela
        DB::table($table)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('rolled_back_at')
                  ->orWhere('rolled_back_at', '0000-00-00 00:00:00');
            })
            ->update(['rolled_back_at' => $this->sentinelDateTime]);

        // Rolled_back sin fecha -> usamos updated_at/created_at/ahora
        DB::table($table)
            ->where('status', 'rolled_back')
            ->where(function ($q) {
                $q->whereNull('rolled_back_at')
                  ->orWhere('rolled_back_at', '0000-00-00 00:00:00');
            })
            ->update([
                'rolled_back_at' => DB::raw('COALESCE(updated_at, created_at, NOW())'),
            ]);
    }

    /**
     * Drop de un índice por nombre si existe (MySQL/MariaDB).
     */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

        if (!empty($rows)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
        }
    }

    /**
     * Elimina cualquier UNIQUE (no primario) que contenga al menos las columnas indicadas.
     *
     * Lo uso para localizar el viejo UNIQUE(product_id, document_number) en capitados_product_insureds.
     */
    private function dropUniqueIndexContainingColumns(string $table, array $columns): void
    {
        $rows = DB::select("
            SHOW INDEX FROM `{$table}`
            WHERE Non_unique = 0 AND Key_name <> 'PRIMARY'
        ");

        if (empty($rows)) {
            return;
        }

        // Agrupar columnas por Key_name
        $byIndex = [];
        foreach ($rows as $row) {
            $key = $row->Key_name;
            $col = $row->Column_name;
            $byIndex[$key][] = $col;
        }

        sort($columns);

        foreach ($byIndex as $indexName => $cols) {
            $colsSorted = $cols;
            sort($colsSorted);

            // ¿El índice contiene al menos todas las columnas pedidas?
            $containsAll = empty(array_diff($columns, $colsSorted));

            if ($containsAll) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
            }
        }
    }
};
