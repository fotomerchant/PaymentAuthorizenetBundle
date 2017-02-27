<?php

namespace FM\Payment\AuthorizenetBundle\Plugin;

use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Plugin\AbstractPlugin;
use JMS\Payment\CoreBundle\Plugin\Exception\FinancialException;
use JMS\Payment\CoreBundle\Plugin\PluginInterface;
use Omnipay\AuthorizeNet\AIMGateway;
use Omnipay\Common\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class CheckoutPlugin extends AbstractPlugin
{
    /**
     * @var \Omnipay\AuthorizeNet\AIMGateway
     */
    protected $gateway;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var string
     */
    protected $processesType;

    public function __construct(AIMGateway $gateway)
    {
        parent::__construct();

        $this->gateway = $gateway;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    public function setProcessesType($processesType)
    {
        $this->processesType = $processesType;
    }

    public function processes($name)
    {
        return $name == $this->processesType;
    }

    public function approveAndDeposit(FinancialTransactionInterface $transaction, $retry)
    {
        $parameters = $this->getPurchaseParameters($transaction);

        $response = $this->gateway->purchase($parameters)->send();

        if ($this->logger) {
            $requestData = (array) $response->getRequest()->getData();
            $data = (array) $response->getData();

            $this->logger->info(json_encode($requestData));
            $this->logger->info(json_encode($data));
        }

        if ($response->isSuccessful()) {
            $transaction->setProcessedAmount($transaction->getRequestedAmount());
            $transaction->setReferenceNumber($response->getTransactionReference());
            $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
            $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);

            if ($this->logger) {
                $this->logger->info(
                    sprintf(
                        'Payment is successful for transaction "%s".',
                        $response->getTransactionReference()
                    )
                );
            }

            return;
        }

        if ($this->logger) {
            $this->logger->info(
                sprintf(
                    'Payment failed for transaction "%s" with message: %s.',
                    $response->getTransactionReference(),
                    $response->getMessage()
                )
            );
        }

        $ex = $this->handleError($response, $transaction);

        throw $ex;
    }

    /**
     * @param FinancialTransactionInterface $transaction
     *
     * @return array
     */
    protected function getPurchaseParameters(FinancialTransactionInterface $transaction)
    {
        /**
         * @var \JMS\Payment\CoreBundle\Model\PaymentInterface $payment
         */
        $payment = $transaction->getPayment();

        /**
         * @var \JMS\Payment\CoreBundle\Model\PaymentInstructionInterface $paymentInstruction
         */
        $paymentInstruction = $payment->getPaymentInstruction();

        /**
         * @var \JMS\Payment\CoreBundle\Model\ExtendedDataInterface $data
         */
        $data = $transaction->getExtendedData();

        $transaction->setTrackingId($payment->getId());

        $parameters = [
            'amount' => $payment->getTargetAmount(),
            'currency' => $paymentInstruction->getCurrency(),
            'description' => $data->get('description'),
            'dataValue' => $data->get('dataValue'),
            'dataDescriptor' => $data->get('dataDescriptor'),
            'apiLoginId' => $data->get('apiLoginId'),
            'transactionKey' => $data->get('transactionKey'),
            'duplicateWindow' => $data->get('duplicateWindow'),
            'invoiceNumber' => $data->get('invoiceNumber'),
            'developerMode' => $data->get('developerMode'),
        ];

        return $parameters;
    }

    /**
     * @param ResponseInterface             $response
     * @param FinancialTransactionInterface $transaction
     *
     * @return FinancialException
     */
    private function handleError(ResponseInterface $response, FinancialTransactionInterface $transaction)
    {
        $data = $response->getData();

        $errorDetails = (array) $data->transactionResponse->errors->error;

        $ex = new FinancialException($data->transactionResponse->errors->error->errorText);
        $ex->addProperty('error', $errorDetails['errorCode'].": ".$errorDetails['errorText']);
        $ex->setFinancialTransaction($transaction);

        $transaction->setResponseCode('FAILED');
        $transaction->setReasonCode($errorDetails['errorCode'].": ".$errorDetails['errorText']);
        $transaction->setState(FinancialTransactionInterface::STATE_FAILED);

        return $ex;
    }
}
