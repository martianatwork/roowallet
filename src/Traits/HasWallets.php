<?php
/**
 * Copyright (c) 2018.
 * Martianatwork
 *
 */

namespace martianatwork\RooWallet\Traits;


use InvalidArgumentException;
use martianatwork\RooWallet\Models\Wallet;
use martianatwork\RooWallet\Models\WalletCurrency;
use martianatwork\RooWallet\Models\WalletTransaction;
use martianatwork\RooWallet\RooWallet;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

trait HasWallets
{

    /**
     * Get Subscriptions relatinship.
     *
     * @return morphMany Relatinship.
     */
    public function user()
    {
        return $this->morphMany(config('patosmack.roowallet.user_model'), 'model');
    }

    public static function funds($iso = 'default') {
        return (new  RooWallet())->funds($id,$iso);
    }

    /*
     *  Private Validations
     */

    public static function validateIso($iso)
    {
        $iso = strtoupper($iso);
        if (strlen($iso) != 3) {
            throw new InvalidArgumentException('The iso code must be ISO 4217 Currency Codes');
        }

        return $iso;
    }
    public static function validateConversionRate($conversion_rate)
    {
        if (!is_numeric($conversion_rate)) {
            throw new InvalidArgumentException('The Conversion Rate must be a number');
        }

        return $conversion_rate;
    }
    public static function validateName($name)
    {
        if (strlen($name) == 0) {
            throw new InvalidArgumentException('The Name can not be empty');
        }

        return $name;
    }
    public static function validateSymbol($symbol)
    {
        if (strlen($symbol) == 0) {
            throw new InvalidArgumentException('The Symbol can not be empty');
        }

        return $symbol;
    }

    public function currencySelector(){
        return config('patosmack.roowallet.currencies_model',WalletCurrency::class);
    }
    /**
     * Retrieve all currencies
     */
    public function currencies()
    {
        return config('patosmack.roowallet.currencies_model', WalletCurrency::class)::all();
    }

    /**
     * Retrieve currency
     */
    public function currency($iso, $status = 1)
    {
        return $this->currencySelector()::getCurrency($iso, $status);
    }
    /**
     * Retrieve currency
     */
    public function currencyList()
    {
        return $this->currencySelector()::getCurrencyList();
    }

    /*
     *   Add a new Currency, if the currency Iso Code already exists, the old Currency Will be updated.
     */

    public function addCurrency($iso, $name, $symbol, $conversion_rate, $enabled = 0)
    {
        $conversion_rate = self::validateConversionRate($conversion_rate);
        $name = self::validateName($name);
        $symbol = self::validateSymbol($symbol);

        $wallet_currency = self::getCurrency($iso);
        if (!$wallet_currency) {
            $wallet_currency = new WalletCurrency();
            $wallet_currency->iso = $iso;
        }
        $wallet_currency->name = $name;
        $wallet_currency->symbol = $symbol;
        $wallet_currency->conversion_rate = $conversion_rate;
        $wallet_currency->enabled = $enabled;
        return $wallet_currency->save();
    }

    /*
     *   Update Currency, if the currency Iso Code.
     */

    public function updateCurrency($iso, $name = null, $symbol = null, $conversion_rate = null , $enabled = null)
    {

        if(empty($name) & empty($symbol) & empty($conversion_rate) & empty($enabled)){
            return false;
        }

        $wallet_currency = $this->currency($iso);
        if (!$wallet_currency) {
            return false;
        }

        if ($name) {
            $name = self::validateName($name);
            $wallet_currency->name = $name;
        }
        if ($symbol) {
            $symbol = self::validateSymbol($symbol);
            $wallet_currency->symbol = $symbol;
        }
        if ($conversion_rate) {
            $conversion_rate = self::validateConversionRate($conversion_rate);
            $wallet_currency->conversion_rate = $conversion_rate;
        }
        if ($enabled) {
            $wallet_currency->enabled = $enabled;
        }

        return $wallet_currency->save();
    }

    /**
     * WALLETS
     */

    /**
     * Retrieve all wallets of this user
     */
    public function wallets()
    {
        return $this->hasMany(config('patosmack.roowallet.wallet_model', Wallet::class),config('patosmack.roowallet.user_model_selector'));
    }

    /**
     * Retrieve wallet of this user by currency
     */
    public function wallet($iso = 'default')
    {
        $currency = $this->currency($iso)->id;
        return $this->wallets->where('wallet_currency_id',$currency)->first();
    }

    public function calculateFunds($iso = 'default')
    {
        return $this->wallet($iso)->funds;
    }
    public function balance($iso = 'default')
    {
        return $this->wallet($iso)->funds;
    }
    private function roundNumber($number)
    {
        return round($number, 4);
    }

    public function getCredits()
    {
        return self::roundNumber(WalletTransaction::where('wallet_id', $this->id)->where('direction', WalletTransaction::DIRECTION_CREDIT)->where('deleted', 0)->sum('amount'));
    }

    public function getDebits()
    {
        return self::roundNumber(WalletTransaction::where('wallet_id', $this->id)->where('direction', WalletTransaction::DIRECTION_DEBIT)->where('deleted', 0)->sum('amount'));
    }

    public function deposit($amount, $refence_id = null, $reference_description = null, $token = '',$iso = 'default')
    {
        return $this->wallet($iso)->deposit($amount, $refence_id, $reference_description, $token);
    }

    public function testWithdraw($amount, $iso = 'default')
    {
        return $this->wallet($iso)->testWithdraw($amount);
    }
    public function canWithdraw($amount, $iso = 'default')
    {
        return $this->wallet($iso)->canWithdraw($amount);
    }

    public function withdraw($amount, $refence_id = null, $reference_description = null, $token = '', $iso = 'default')
    {
        return $this->wallet($iso)->deposit($amount, $refence_id, $reference_description, $token);
    }
    public function convertamount($amount, $baseCurrency, $currency)
    {
        return;
    }

    /**
     * TRANSACTIONS
     */

    /**
     * Retrieve all transactions of this user
     */
    public function transactions()
    {
        return $this->hasManyThrough(config('patosmack.roowallet.transaction_model', WalletTransaction::class), config('patosmack.roowallet.wallet_model', Wallet::class))->latest();
    }
    /**
     * Retrieve all transactions of this user
     */
    public function transaction($id)
    {
        return $this->transactions()->find($id);
    }

    public function getTransactionsByCurrency($currency_iso)
    {
        $wallet = $this->wallet($currency_iso);
        if ($wallet) {
            return $this->transactions->where('wallet_id', $wallet->id)->get();
        }
        return array();
    }



}