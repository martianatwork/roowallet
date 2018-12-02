<?php
/**
 * Copyright (c) 2018.
 * Martianatwork
 *
 */

namespace martianatwork\RooWallet\Models;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class WalletCurrency extends Model
{

    protected $fillable = ['name', 'symbol', 'conversion_rate'];
    /*
     *  Private Validations
     */

    private static function validateIso($iso)
    {
        $iso = strtoupper($iso);
        if (strlen($iso) != 3) {
            throw new InvalidArgumentException('The iso code must be ISO 4217 Currency Codes');
        }

        return $iso;
    }
    private static function validateConversionRate($conversion_rate)
    {
        if (!is_numeric($conversion_rate)) {
            throw new InvalidArgumentException('The Conversion Rate must be a number');
        }

        return $conversion_rate;
    }
    private static function validateName($name)
    {
        if (strlen($name) == 0) {
            throw new InvalidArgumentException('The Name can not be empty');
        }

        return $name;
    }
    private static function validateSymbol($symbol)
    {
        if (strlen($symbol) == 0) {
            throw new InvalidArgumentException('The Symbol can not be empty');
        }

        return $symbol;
    }

    /*
     *  Get ISO 4217 Currency Code List
     */

    public static function getCurrencyList()
    {
        return self::pluck('iso')->all();
    }

    /*
     *   Get Curreny by Iso Code
     */

    public static function getCurrency($iso, $status = 1)
    {
        if ($iso == 'default'){
            $iso = \Config('patosmack.roowallet.base_currency');
        }
        return self::whereIso(self::validateIso($iso))->where('enabled', $status)->first();
    }
    public static function getCurrencyByID($id, $status = 1)
    {
        return self::find($id)->where('enabled', $status)->first();
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

    public function updateCurrency($name = null, $symbol = null, $conversion_rate = null , $enabled = null)
    {
        $conversion_rate = self::validateConversionRate($conversion_rate);
        $name = self::validateName($name);
        $symbol = self::validateSymbol($symbol);

        if(empty($name) & empty($symbol) & empty($conversion_rate) & empty($enabled)){
            return false;
        }
        if ($name) {
            $wallet_currency->name = $name;
        }
        if ($symbol) {
            $wallet_currency->symbol = $symbol;
        }
        if ($conversion_rate) {
            $wallet_currency->conversion_rate = $conversion_rate;
        }
        if ($enabled) {
            $wallet_currency->enabled = $enabled;
        }
        return $this->save();
    }
}
