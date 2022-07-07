<?php

namespace Modules\Isp\Entities;

use Modules\Base\Entities\BaseModel;
use Illuminate\Database\Schema\Blueprint;
use Modules\Base\Classes\Migration;

class ConnectionInvoice extends BaseModel
{

    protected $fillable = ['connection_id', 'invoice_id'];
    public $migrationDependancy = ['isp_connection', 'account_invoice'];
    protected $table = "isp_connection_invoice";

    /**
     * List of fields for managing postings.
     *
     * @param Blueprint $table
     * @return void
     */
    public function migration(Blueprint $table)
    {
        $table->increments('id');
        $table->integer('connection_id')->unsigned()->nullable();
        $table->integer('invoice_id')->unsigned()->nullable();
    }


    public function post_migration(Blueprint $table)
    {
        if (Migration::checkKeyExist('isp_connection_invoice', 'connection_id')) {
            $table->foreign('connection_id')->references('id')->on('isp_connection')->nullOnDelete();
        }
        if (Migration::checkKeyExist('isp_connection_invoice', 'invoice_id')) {
            $table->foreign('invoice_id')->references('id')->on('account_invoice')->nullOnDelete();
        }
    }
}
