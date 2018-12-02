<?php
/**
 * Copyright (c) 2018.
 * Martianatwork
 *
 */

namespace martianatwork\RooWallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class WalletTransaction extends Model
{

    const ACTION_DEPOSIT = "DEPOSIT";
    const ACTION_WITHDRAW = "WITHDRAW";

    const DIRECTION_DEBIT = "DEBIT";
    const DIRECTION_CREDIT = "CREDIT";
    const DIRECTION_ADJUST = "ADJUST";

    const TYPE_AMOUNT = "AMOUNT";

    protected $fillable = ['wallet_id', 'amount', 'action', 'direction', 'type', 'reference_id', 'reference_description', 'token', 'deleted', 'delete_motive'];

    public function wallet()
    {
        return $this->belongsTo('martianatwork\RooWallet\Models\Wallet');
    }


    private static function roundNumber($number)
    {
        return round($number, 4);
    }

    public static function getTransactionsByCurrency($user_id, $currency_iso)
    {
        return self::where('user_id', $user_id)->where('currency_id',$currency_iso)->get();
    }
    public static function getTransactionsByuser($user_id)
    {
        return self::where('user_id', $user_id)->get();
    }

    public function calculateFunds()
    {
        $wallet = $this->wallet;
        if ($wallet) {
            $balance = self::calculateFundsbyWallet($wallet);
            if ($balance != $wallet->funds) {
                self::saveFundsByWallet($wallet);
            }
            return $balance;
        }
        return 0;
    }

    private function saveFundsByWallet($wallet)
    {
        $balance = self::calculateFundsbyWallet($wallet);
        $wallet->funds = $balance;
        $wallet->funds_update = Carbon::now();
        return $wallet->save();
    }

    private function calculateFundsbyWallet($wallet)
    {
        $credits = self::where('wallet_id', $wallet->id)->where('direction', self::DIRECTION_CREDIT)->where('deleted', 0)->sum('amount');
        $debits = self::where('wallet_id', $wallet->id)->where('direction', self::DIRECTION_DEBIT)->where('deleted', 0)->sum('amount');
        $balance = self::roundNumber($credits) - self::roundNumber($debits);
        return self::roundNumber($balance);
    }

    public function getCredits()
    {
        $wallet = $this->wallet;
        if ($wallet) {
            return self::where('wallet_id', $wallet->id)->where('direction', self::DIRECTION_CREDIT)->where('deleted', 0)->sum('amount');
        }
        return 0;
    }

    public function getDebits()
    {
        $wallet = $this->wallet;
        if ($wallet) {
            return self::roundNumber(self::where('wallet_id', $wallet->id)->where('direction', self::DIRECTION_DEBIT)->where('deleted', 0)->sum('amount'));
        }
        return 0;
    }

    public function deposit($amount, $refence_id = null, $reference_description = null, $token = '')
    {
        $wallet = $this->wallet;

        if (!$wallet) {
            return false;
        }

        if ($amount <= 0) {
            return false;
        }

        $transaction = new WalletTransaction();

        DB::transaction(function () use ($transaction, $wallet, $amount, $refence_id, $reference_description, $token) {
            try {
                $transaction->wallet_id = $wallet->id;
                $transaction->amount = self::roundNumber($amount);
                $transaction->action = self::ACTION_DEPOSIT;
                $transaction->direction = self::DIRECTION_CREDIT;
                $transaction->type = self::TYPE_AMOUNT;
                $transaction->reference_id = $refence_id;
                $transaction->reference_description = $reference_description;
                $transaction->token = $token;
                $transaction->wallet_currency_id = $wallet->wallet_currency_id;
                if (config('patosmack.roowallet.user_model_selector')){
                    $user = config('patosmack.roowallet.user_model_selector');
                    $transaction->{$user} = $wallet->{$user};
                }

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
        return false;
    }

    private function testWithdraw($wallet, $amount)
    {
        if (!$wallet) {
            return false;
        }

        if ($amount <= 0) {
            return false;
        }

        $balance = self::calculateFundsbyWallet($wallet);

        $amount = self::roundNumber($amount);
        $left = self::roundNumber($balance - $amount);
        if ($left >= 0) {
            return true;
        }
        return false;
    }

    public function withdraw($amount, $refence_id = null, $reference_description = null, $token = '')
    {
        $wallet = $this->wallet;
        if (!$wallet) {
            return false;
        }

        if (self::testWithdraw($wallet, $amount)) {

            $transaction = new WalletTransaction();

            DB::transaction(function () use ($transaction, $wallet, $amount, $refence_id, $reference_description, $token) {
                try {
                    $transaction->wallet_id = $wallet->id;
                    $transaction->amount = self::roundNumber($amount);
                    $transaction->action = self::ACTION_WITHDRAW;
                    $transaction->direction = self::DIRECTION_DEBIT;
                    $transaction->type = self::TYPE_AMOUNT;
                    $transaction->reference_id = $refence_id;
                    $transaction->reference_description = $reference_description;
                    $transaction->token = $token;
                    $transaction->wallet_currency_id = $wallet->wallet_currency_id;
                    if (config('patosmack.roowallet.user_model_selector')){
                        $user = config('patosmack.roowallet.user_model_selector');
                        $transaction->{$user} = $wallet->{$user};
                    }

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

    /**
     * @param $txnid
     * @param null $message
     * @return bool
     *
     * User Construction
     */
    public function refund($txnid, $message = null)
    {
        $wallet = $this->wallet;
        if (!$wallet) {
            return false;
        }

        if (self::testWithdraw($wallet, $amount)) {

            $transaction = new WalletTransaction();

            DB::transaction(function () use ($transaction, $wallet, $amount, $refence_id, $reference_description, $token) {
                try {
                    $transaction->wallet_id = $wallet->id;
                    $transaction->amount = self::roundNumber($amount);
                    $transaction->action = self::ACTION_WITHDRAW;
                    $transaction->direction = self::DIRECTION_DEBIT;
                    $transaction->type = self::TYPE_AMOUNT;
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
