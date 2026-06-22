<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ajoute le statut 'en_attente' (demande de permission soumise par un agent, en
     * attente de validation/rejet par le manager) à l'enum `absences.status`.
     *
     * SQLite impose une contrainte CHECK recréée à la création de la table : elle ne
     * peut pas être modifiée par un simple ALTER, il faut recréer la table. MySQL
     * supporte MODIFY COLUMN directement.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE TABLE absences_new (
                id integer primary key autoincrement not null,
                employee_id integer not null,
                start_date date not null,
                end_date date not null,
                type varchar check ("type" in (\'absence\', \'permission\')) not null,
                reason text,
                status varchar check ("status" in (\'enregistree\', \'refusee\', \'en_attente\')) not null,
                created_at datetime,
                updated_at datetime,
                foreign key("employee_id") references "employees"("id") on delete cascade
            )');
            DB::statement('INSERT INTO absences_new SELECT * FROM absences');
            DB::statement('DROP TABLE absences');
            DB::statement('ALTER TABLE absences_new RENAME TO absences');
        } else {
            DB::statement("ALTER TABLE absences MODIFY status ENUM('enregistree', 'refusee', 'en_attente') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("DELETE FROM absences WHERE status = 'en_attente'");
            DB::statement('CREATE TABLE absences_old (
                id integer primary key autoincrement not null,
                employee_id integer not null,
                start_date date not null,
                end_date date not null,
                type varchar check ("type" in (\'absence\', \'permission\')) not null,
                reason text,
                status varchar check ("status" in (\'enregistree\', \'refusee\')) not null,
                created_at datetime,
                updated_at datetime,
                foreign key("employee_id") references "employees"("id") on delete cascade
            )');
            DB::statement('INSERT INTO absences_old SELECT * FROM absences');
            DB::statement('DROP TABLE absences');
            DB::statement('ALTER TABLE absences_old RENAME TO absences');
        } else {
            DB::statement("UPDATE absences SET status = 'refusee' WHERE status = 'en_attente'");
            DB::statement("ALTER TABLE absences MODIFY status ENUM('enregistree', 'refusee') NOT NULL");
        }
    }
};
