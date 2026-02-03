<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Cambiar valores del ENUM realm: ('backoffice','customer') -> ('admin','customer')
        //    y migrar datos existentes de 'backoffice' a 'admin'
        DB::statement("UPDATE users SET realm = 'admin' WHERE realm = 'backoffice'");
        DB::statement("ALTER TABLE users MODIFY COLUMN realm ENUM('admin','customer') NOT NULL");

        // 2) Renombrar tabla de tokens de reset
        if (Schema::hasTable('password_reset_tokens_backoffice') && !Schema::hasTable('password_reset_tokens_admin')) {
            Schema::rename('password_reset_tokens_backoffice', 'password_reset_tokens_admin');
        }
    }

    public function down(): void
    {
        // Revertir (por si hiciera falta)
        DB::statement("UPDATE users SET realm = 'backoffice' WHERE realm = 'admin'");
        DB::statement("ALTER TABLE users MODIFY COLUMN realm ENUM('backoffice','customer') NOT NULL");

        if (Schema::hasTable('password_reset_tokens_admin') && !Schema::hasTable('password_reset_tokens_backoffice')) {
            Schema::rename('password_reset_tokens_admin', 'password_reset_tokens_backoffice');
        }
    }
};
