<?php

namespace Modules\Account\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

class Tax extends Model
{

    protected $fillable = [];
    protected $migrationOrder = 5;
    protected $table = "account_tax";

    /**
     * List of fields for managing postings.
     *
     * @param Blueprint $table
     * @return void
     */
    public function migration(Blueprint $table)
    {
        $table->increments('id');
        $table->string('tax_rate_name')->nullable();
        $table->string('tax_number', 100)->nullable();
        $table->tinyInteger('default')->nullable();
        $table->string('created_by', 50)->nullable();
        $table->string('updated_by', 50)->nullable();
        $table->timestamps();
    }

}
