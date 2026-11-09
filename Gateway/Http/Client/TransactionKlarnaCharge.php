<?php

namespace Paytrail\PaymentService\Gateway\Http\Client;

use GuzzleHttp\Exception\RequestException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Sales\Model\Order;
use Paytrail\PaymentService\Logger\PaytrailLogger;
use Paytrail\PaymentService\Model\Adapter\Adapter;
use Paytrail\SDK\Request\KlarnaPaymentRequest;

class TransactionKlarnaCharge implements ClientInterface
{
    /**
     * TransactionKlarnaCharge constructor.
     *
     * @param Adapter $paytrailAdapter
     * @param PaytrailLogger $log
     */
    public function __construct(
        private readonly Adapter $paytrailAdapter,
        private readonly PaytrailLogger $log
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param TransferInterface $transferObject
     * @return array
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $request = $transferObject->getBody();

        return $this->charge(
            $request['request_data'],
            $request['order']
        );
    }

    /**
     * Charge function. Sends the Klarna Express Checkout network session token
     * to Paytrail's /payments/klarna/charge endpoint.
     *
     * @param KlarnaPaymentRequest $klarnaPayment
     * @param Order $order
     * @return array
     */
    public function charge($klarnaPayment, $order): array
    {
        $response["data"] = null;
        $response["error"] = null;

        try {
            $paytrailClient = $this->paytrailAdapter->initPaytrailMerchantClient();

            $this->log->debugLog(
                'request',
                \sprintf(
                    'Creating %s request to Paytrail API %s',
                    'klarna/charge',
                    'With order id: ' . $order->getId()
                )
            );

            $response["data"] = $paytrailClient->createKlarnaPaymentCharge($klarnaPayment);

            $this->log->debugLog(
                'response',
                sprintf(
                    'Response for order id: %s, transactionId: %s, stepUpUrl: %s, error: %s',
                    $order->getId(),
                    $response["data"]->getTransactionId(),
                    $response["data"]->getStepUpUrl(),
                    $response["data"]->getError()
                )
            );
        } catch (RequestException $e) {
            $this->log->error(\sprintf(
                'Connection error to Paytrail Payment Service API: %s Error Code: %s',
                $e->getMessage(),
                $e->getCode()
            ));

            $response["error"] = $e->getMessage();
        } catch (\Exception $e) {
            $this->log->error(
                \sprintf(
                    'A problem occurred during Paytrail Klarna charge request: %s',
                    $e->getMessage()
                ),
                $e->getTrace()
            );
            $response["error"] = $e->getMessage();
        }

        return $response;
    }
}
