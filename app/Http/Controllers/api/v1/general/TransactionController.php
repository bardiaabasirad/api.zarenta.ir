<?php

namespace App\Http\Controllers\api\v1\general;

use App\Http\Controllers\Controller;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
use Shetabit\Payment\Facade\Payment;

class TransactionController extends Controller
{
    public function check()
    {
        $transaction_id = request()->input('transaction_id');
        try {
            $receipt = Payment::transactionId($transaction_id)->verify();

            // You can show payment referenceId to the user.
            echo $receipt->getReferenceId();
        } catch (InvalidPaymentException $exception) {
            /**
            when payment is not verified, it will throw an exception.
            We can catch the exception to handle invalid payments.
            getMessage method, returns a suitable message that can be used in user interface.
             **/
            echo $exception->getMessage();
        }
    }
}
