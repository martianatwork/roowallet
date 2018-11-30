<?php
namespace martianatwork\RooWallet;

use martianatwork\RooWallet\Repository\RooWalletCurrency;
use martianatwork\RooWallet\Repository\RooWalletTransaction;
use martianatwork\RooWallet\Repository\RooWalletWallet;

/**
 * RooWallet Package
 * Digital Wallet for Laravel
 *
 * @author Patricio Alvarez
 *
 */
class RooWallet
{
    const version = "1.0.0";

    private static $currencies_iso;

    protected $currencyRepo;
    protected $walletRepo;
    protected $transactionRepo;

    public function __construct()
    {
        self::$currencies_iso = array_keys(config('patosmack.roowallet.available_currencies'));
        $this->currencyRepo = new RooWalletCurrency(self::$currencies_iso);
        $this->walletRepo = new RooWalletWallet($this->currencyRepo);
        $this->transactionRepo = new RooWalletTransaction($this->currencyRepo, $this->walletRepo);
    }

    /*
     *  Currency
     */

    public function getCurrencyList()
    {
        return $this->currencyRepo->getCurrencyList();
    }

    public function getCurrency($iso)
    {
        return $this->currencyRepo->getCurrency($iso);
    }
    public function getCurrencyByid($id)
    {
        return $this->currencyRepo->getCurrencyByID($id);
    }

    public function addCurrency($iso, $name, $symbol, $conversion_rate, $enabled = 0)
    {
        return $this->currencyRepo->addCurrency($iso, $name, $symbol, $conversion_rate, $enabled);
    }

    public function updateCurrency($iso, $name, $symbol, $conversion_rate, $enabled = 0)
    {
        return $this->currencyRepo->updateCurrency($iso, $name, $symbol, $conversion_rate, $enabled);
    }

    /*
     *  Wallet
     */

    public function getWallet($user_id, $currency_iso)
    {
        return $this->walletRepo->getWallet($user_id, $currency_iso);
    }

    public function createWallet($user_id, $currency_iso)
    {
        return $this->walletRepo->createWallet($user_id, $currency_iso);
    }

    /*
     *  Transaction
     */

    public function getTransactions($user_id, $currency_iso)
    {
        return $this->transactionRepo->getTransactions($user_id, $currency_iso);
    }
    public function getAllTransactions($user_id)
    {
        return $this->transactionRepo->getAllTransactions($user_id);
    }

    public function funds($user_id, $currency_iso)
    {
        return $this->transactionRepo->calculateFunds($user_id, $currency_iso);
    }

    public function deposit($user_id, $currency_iso, $amount, $refence_id = null, $reference_description = null, $token = '')
    {
        return $this->transactionRepo->deposit($user_id, $currency_iso, $amount, $refence_id, $reference_description, $token);
    }

    public function canWithdraw($user_id, $currency_iso, $amount)
    {
        return $this->transactionRepo->canWithdraw($user_id, $currency_iso, $amount);
    }

    public function withdraw($user_id, $currency_iso, $amount, $refence_id = null, $reference_description = null, $token = '')
    {
        return $this->transactionRepo->withdraw($user_id, $currency_iso, $amount, $refence_id, $reference_description, $token);
    }

    public function getCredits($user_id, $currency_iso)
    {
        return $this->transactionRepo->getCredits($user_id, $currency_iso);
    }

    public function getDebits($user_id, $currency_iso)
    {
        return $this->transactionRepo->getDebits($user_id, $currency_iso);
    }
    public function refund($txnid, $message = null)
    {
        return $this->transactionRepo->refund($txnid, $message);
    }

    /**
     * Convert amount
     */
    public function convertamount($amount, $baseCurrency, $currency)
    {
        return $this->transactionRepo->convertamount($amount, $baseCurrency, $currency);
    }

}
