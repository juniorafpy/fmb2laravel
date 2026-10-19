<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Importaciones de modulos Oracle Forms (.fmb) procesadas con fmb2laravel.
 *
 * Cada fila registra el archivo subido, el resumen de la extraccion
 * (triggers, reglas, tablas, mensajes y advertencias) y los archivos
 * generados en el disco local (storage/app/private/fmb-imports/{id}).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fmb_imports', function (Blueprint $table) {
            $table->id();
            $table->string('archivo_original');
            $table->string('ruta_archivo');
            $table->string('modulo');
            $table->string('sha256', 64)->nullable();
            $table->unsignedBigInteger('tamano')->nullable();
            $table->string('estado', 12)->default('pendiente');
            $table->text('error')->nullable();
            $table->json('resumen')->nullable();
            $table->json('archivos')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fmb_imports');
    }
};
