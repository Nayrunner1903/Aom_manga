<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /**
         * SQLite requires CHECK constraints at table creation. The integer type
         * check rejects fractional storage even though SQLite uses type affinity.
         */
        DB::statement(<<<'SQL'
            CREATE TABLE transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                user_id INTEGER NOT NULL,
                category_id INTEGER NOT NULL,
                type VARCHAR NOT NULL CHECK (type IN ('income', 'expense')),
                amount_satang INTEGER NOT NULL
                    CHECK (typeof(amount_satang) = 'integer' AND amount_satang > 0),
                description TEXT NULL,
                transaction_date DATE NOT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                FOREIGN KEY (category_id, user_id, type)
                    REFERENCES categories (id, user_id, type)
                    ON DELETE NO ACTION ON UPDATE RESTRICT
            )
            SQL);

        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['user_id', 'transaction_date', 'id']);
            $table->index(['user_id', 'type', 'transaction_date']);
            $table->index(['category_id', 'user_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
