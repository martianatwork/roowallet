<?php

namespace martianatwork\RooWallet\Models;

use Illuminate\Database\Eloquent\Model;
use martianatwork\RooWallet\RooWallet;

class Wallet extends Model
{

    protected $fillable = ['user_id', 'wallet_currency_id', 'funds', 'funds_update'];

    public function user()
    {
        $user_model = config('patosmack.roowallet.user_model');
        return $this->belongsTo($user_model);
    }

    public function walletCurrency()
    {
        return $this->belongsTo('martianatwork\RooWallet\Models\WalletCurrency');
    }

    public function walletTransactions()
    {
        return $this->hasMany('martianatwork\RooWallet\Models\WalletTransaction');
    }
    public function funds(){
        return (new  RooWallet())->funds($this->user->id,$this->walletCurrency->iso);
    }
}
