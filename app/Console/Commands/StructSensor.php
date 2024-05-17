<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class StructSensor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'struct:sensor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!Schema::hasTable('sensor')) {
            Schema::create('sensor', function (Blueprint $table) {
                $table->string('kk')->primary();
                $table->text('vv');
                $table->timestamps();
            });
        }
    }
}
