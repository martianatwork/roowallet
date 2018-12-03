<?php
/**
 * Copyright (c) 2018.
 * Martianatwork
 *
 */

namespace martianatwork\RooWallet\Models;

use Illuminate\Database\Eloquent\Model;
use martianatwork\RooWallet\RooWallet;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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


    public function calculateFunds()
    {
        $balance = self::calculateFundsbyWallet();
        if ($balance != $this->funds) {
            self::saveFundsByWallet();
        }
        return $balance;
    }
    private function roundNumber($number)
    {
        return round($number, 4);
    }

    private function calculateFundsbyWallet()
    {
        $credits = $this->walletTransactions->where('wallet_id', $this->id)->where('direction', WalletTransaction::DIRECTION_CREDIT)->where('deleted', 0)->sum('amount');
        $debits = $this->walletTransactions->where('wallet_id', $this->id)->where('direction', WalletTransaction::DIRECTION_DEBIT)->where('deleted', 0)->sum('amount');
        $balance = self::roundNumber($credits) - self::roundNumber($debits);
        return self::roundNumber($balance);
    }

    public function saveFundsByWallet()
    {
        $balance = self::calculateFundsbyWallet();
        $this->funds = $balance;
        $this->funds_update = Carbon::now();
        return $this->save();
    }

    public function getCredits()
    {
        return WalletTransaction::where('wallet_id', $this->id)->where('direction', WalletTransaction::DIRECTION_CREDIT)->where('deleted', 0)->sum('amount');
    }

    public function getDebits()
    {
        return self::roundNumber(WalletTransaction::where('wallet_id', $this->id)->where('direction', WalletTransaction::DIRECTION_DEBIT)->where('deleted', 0)->sum('amount'));
    }

    public function deposit($amount, $refence_id = null, $reference_description = null, $token = '')
    {
        if ($amount <= 0) {
            return false;
        }
        $transaction = new WalletTransaction();

        DB::transaction(function () use ($transaction, $amount, $refence_id, $reference_description, $token) {
            try {
                $transaction->wallet_id = $this->id;
                $transaction->amount = self::roundNumber($amount);
                $transaction->action = WalletTransaction::ACTION_DEPOSIT;
                $transaction->direction = WalletTransaction::DIRECTION_CREDIT;
                $transaction->type = WalletTransaction::TYPE_AMOUNT;
                $transaction->reference_id = $refence_id;
                $transaction->reference_description = $reference_description;
                $transaction->token = $token;
                $transaction->wallet_currency_id = $this->wallet_currency_id;
                if (config('patosmack.roowallet.user_model_selector')){
                    $user = config('patosmack.roowallet.user_model_selector');
                    $transaction->{$user} = $this->{$user};
                }

                $transaction->save();
                self::saveFundsByWallet();

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }
        });
        if ($transaction->wallet_id > 0) {
            return true;
        }
        return false;
    }

    private function testWithdraw($amount)
    {
        if ($amount <= 0) {
            return false;
        }

        $balance = self::calculateFundsbyWallet();

        $amount = self::roundNumber($amount);
        $left = self::roundNumber($balance - $amount);
        if ($left >= 0) {
            return true;
        }
        return false;
    }

    public function canWithdraw($amount)
    {
        if ($amount <= 0) {
            return false;
        }

        $amount = self::roundNumber($amount);
        $balance = self::calculateFundsbyWallet();
        $left = self::roundNumber($balance - $amount);

        if ($left >= 0) {
            return true;
        }
        return false;
    }

    public function withdraw($amount, $refence_id = null, $reference_description = null, $token = '')
    {

        if (self::testWithdraw($amount)) {

            $transaction = new WalletTransaction();

            DB::transaction(function () use ($transaction, $amount, $refence_id, $reference_description, $token) {
                try {
                    $transaction->wallet_id = $this->id;
                    $transaction->amount = self::roundNumber($amount);
                    $transaction->action = WalletTransaction::ACTION_WITHDRAW;
                    $transaction->direction = WalletTransaction::DIRECTION_DEBIT;
                    $transaction->type = WalletTransaction::TYPE_AMOUNT;
                    $transaction->reference_id = $refence_id;
                    $transaction->reference_description = $reference_description;
                    $transaction->token = $token;
                    $transaction->wallet_currency_id = $this->wallet_currency_id;
                    if (config('patosmack.roowallet.user_model_selector')){
                        $user = config('patosmack.roowallet.user_model_selector');
                        $transaction->{$user} = $this->{$user};
                    }

                    $transaction->save();
                    self::saveFundsByWallet();

                } catch (\Exception $e) {
                    DB::rollback();
                    throw $e;
                }
            });
            if ($transaction->wallet_id > 0) {
                return true;
            }
        }
        return false;
    }
    public function convertamount($amount, $baseCurrency, $currency)
    {
        if (is_numeric($amount)) {
            if ($baseCurrency != $currency) {
                $baseCurrency = $this->currencyRepo->getCurrency($baseCurrency);
                $currency = $this->currencyRepo->getCurrency($currency);
                if ($baseCurrency && $currency) {
                    if ($baseCurrency->conversion_rate != $currency->conversion_rate) {
                        $result = $amount / $currency->conversion_rate * $baseCurrency->conversion_rate;
                        return (object) array('result' => self::roundNumber($result), 'rate' => $currency->conversion_rate);
                    }
                    return self::roundNumber($amount);
                }
                return false;
            }
            return false;
        }
        return false;
    }
    public function refund($txnid, $message = null)
    {
        $wallet = $this->wallet($currency_iso);
        if (!$wallet) {
            return false;
        }

        if (self::testWithdraw($wallet, $amount)) {

            $transaction = new WalletTransaction();

            DB::transaction(function () use ($transaction, $wallet, $amount, $refence_id, $reference_description, $token) {
                try {
                    $transaction->wallet_id = $wallet->id;
                    $transaction->amount = self::roundNumber($amount);
                    $transaction->action = WalletTransaction::ACTION_WITHDRAW;
                    $transaction->direction = WalletTransaction::DIRECTION_DEBIT;
                    $transaction->type = WalletTransaction::TYPE_AMOUNT;
                    $transaction->reference_id = $refence_id;
                    $transaction->reference_description = $reference_description;
                    $transaction->token = $token;
                    $transaction->wallet_currency_id = $wallet->wallet_currency_id;

                    $transaction->save();
                    self::saveFundsByWallet($wallet);

                } catch (\Exception $e) {
                    DB::rollback();
                    throw $e;
                }
            });
            if ($transaction->wallet_id > 0) {
                return true;
            }
        }
        return false;
    }
}
