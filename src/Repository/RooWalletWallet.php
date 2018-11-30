<?php namespace martianatwork\RooWallet\Repository;

use Carbon\Carbon;
use martianatwork\RooWallet\Models\Wallet;

/**
 * RooWallet Package
 * Digital Wallet for Laravel
 *
 * @author Patricio Alvarez
 *
 */
class RooWalletWallet
{

    protected $currencyRepo;

    public function __construct($currency_repo)
    {
        $this->currencyRepo = $currency_repo;
    }

    public function getWallet($user_id, $currency_iso)
    {
        $currency = $this->currencyRepo->getCurrency($currency_iso);
        if (!$currency) {
            return false;
        }
        return Wallet::where('user_id', intval($user_id))->where('wallet_currency_id', $currency->id)->first();
    }
    public function getWallets($user_id)
    {
        return Wallet::where('user_id', intval($user_id))->get()->all();
    }

    public function createWallet($user_id, $currency_iso)
    {
        $currency = $this->currencyRepo->getCurrency($currency_iso);
        if (!$currency) {
            return false;
        }

        $wallet = self::getWallet($user_id, $currency_iso);
        if (!$wallet) {
            $wallet = new Wallet();
            $wallet->user_id = $user_id;
            $wallet->wallet_currency_id = $currency->id;
            $wallet->funds = 0;
            $wallet->funds_update = Carbon::now();
            return $wallet->save();
        }
        return false;
    }

}
