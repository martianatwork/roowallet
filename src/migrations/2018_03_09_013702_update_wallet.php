<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateWallet extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropForeign('wallets_user_id_foreign');
            $table->dropUnique('wallets_user_id_unique');
            $table->foreign('user_id')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');

        });
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->unsignedInteger('wallet_currency_id');
            $table->foreign('wallet_currency_id')->references('id')->on('wallet_currencies')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('wallets', function (Blueprint $table) {
            // $table->unique('user_id');
        });
        Schema::table('wallet_transactions', function (Blueprint $table) {

            $table->dropForeign('wallet_transactions_wallet_currency_id_foreign');
            $table->dropColumn('wallet_currency_id');
        });
    }
}
