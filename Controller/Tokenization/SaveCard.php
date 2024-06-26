<?php

namespace Paytrail\PaymentService\Controller\Tokenization;

use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\CommandManagerPoolInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\PaymentTokenFactory;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Model\Receipt\ProcessService;
use Paytrail\SDK\Model\Token\Card;
use Paytrail\SDK\Response\GetTokenResponse;
use Psr\Log\LoggerInterface;

class SaveCard implements ActionInterface
{

    private $errorMsg = null;

    /**
     * @var string[]
     */
    private $cardTypes = [
        'Visa'       => 'VI',
        'MasterCard' => 'MC',
        'Discover'   => 'DI',
        'Amex'       => 'AE',
        'Maestro'    => 'SM',
        'Solo'       => 'SO'
    ];

    /**
     * SaveCard constructor.
     *
     * @param Context $context
     * @param CommandManagerPoolInterface $commandManagerPool
     * @param CustomerSession $customerSession
     * @param PaymentTokenFactory $paymentTokenFactory
     * @param SerializerInterface $jsonSerializer
     * @param EncryptorInterface $encryptor
     * @param PaymentTokenRepositoryInterface $tokenRepository
     * @param LoggerInterface $logger
     * @param Config $gatewayConfig
     * @param PaymentTokenManagementInterface $paymentTokenManagementInterface
     * @param Session $checkoutSession
     * @param ProcessService $processService
     */
    public function __construct(
        private Context                         $context,
        private CommandManagerPoolInterface     $commandManagerPool,
        private CustomerSession                 $customerSession,
        private PaymentTokenFactory             $paymentTokenFactory,
        private SerializerInterface             $jsonSerializer,
        private EncryptorInterface              $encryptor,
        private PaymentTokenRepositoryInterface $tokenRepository,
        private LoggerInterface                 $logger,
        private Config                          $gatewayConfig,
        private PaymentTokenManagementInterface $paymentTokenManagementInterface,
        private Session                         $checkoutSession,
        private ProcessService                  $processService
    ) {
    }

    /**
     * @return ResponseInterface
     * @throws CheckoutException
     * @throws CommandException
     * @throws NotFoundException
     */
    public function execute()
    {
        /** @var string $tokenizationId */
        $tokenizationId = $this->context->getRequest()->getParam('checkout-tokenization-id');

        if (!$tokenizationId || $tokenizationId === '') {
            $this->checkoutSession->setData(
                'paytrail_previous_error',
                __('Card saving has been aborted. Please contact customer service.')
            );
            return $this->redirect();
        }
        $responseData = $this->getResponseData($tokenizationId);
        try {
            $customerId   = $this->customerSession->getCustomerId();
            $paymentToken = $this->paymentTokenManagementInterface->getByPublicHash(
                $this->createPublicHash($responseData->getCard(), $customerId),
                $customerId
            );

            if ($paymentToken) {
                $paymentToken->setIsVisible(true);
                $paymentToken->setIsActive(true);
                $paymentToken->setGatewayToken($responseData->getToken());
                $this->tokenRepository->save($paymentToken);
            } else {
                $this->saveToken($responseData);
            }
        } catch (AlreadyExistsException $e) {
            $this->context->getMessageManager()->addErrorMessage('This card has already been added to your vault');
            $this->logger->error($e->getMessage());
            return $this->redirect();
        }

        // success
        $this->checkoutSession->setData('paytrail_previous_success', __('Card added successfully'));
        return $this->redirect();
    }

    /**
     * Get token response data.
     *
     * @param string $tokenizationId
     *
     * @return mixed
     * @throws CheckoutException
     * @throws NotFoundException
     * @throws CommandException
     */
    private function getResponseData($tokenizationId)
    {
        $commandExecutor = $this->commandManagerPool->get('paytrail');
        $response        = $commandExecutor->executeByCode(
            'token_request',
            null,
            [
                'tokenization_id' => $tokenizationId
            ]
        );

        $errorMsg = $response['error'];

        if (isset($errorMsg)) {
            $this->errorMsg = ($errorMsg);
            $this->processService->processError($errorMsg);
        }

        return $response["data"];
    }

    /**
     * Get response data.
     *
     * @param GetTokenResponse $responseData
     */
    private function saveToken($responseData)
    {
        if (!$responseData) {
            $this->context->getMessageManager()->addErrorMessage('There is a problem communicating with the provider');
            $this->logger->error('There is a problem communicating with the provider. Response data: ' . $responseData);
            $this->context->getRedirect()->redirect(
                $this->context->getResponse(),
                'checkout',
                ['_fragment' => 'payment']
            );
            return $this->context->getResponse();
        }

        $gatewayToken = $responseData->getToken();
        $cardData     = $responseData->getCard();

        $vaultPaymentToken = $this->paymentTokenFactory->create(PaymentTokenFactory::TOKEN_TYPE_CREDIT_CARD);
        $customerId        = $this->customerSession->getCustomer()->getId();
        $vaultPaymentToken->setCustomerId($customerId);
        $vaultPaymentToken->setPaymentMethodCode($this->gatewayConfig->getCcVaultCode());
        $vaultPaymentToken->setExpiresAt(
            $this->getExpiresDate($cardData->getExpireMonth(), $cardData->getExpireYear())
        );
        $tokenDetails = $this->jsonSerializer->serialize(
            [
                'type'           => $this->cardTypes[$cardData->getType()],
                'maskedCC'       => $cardData->getPartialPan(),
                'expirationDate' => $cardData->getExpireYear() . '/' . $cardData->getExpireMonth()
            ]
        );
        $vaultPaymentToken->setGatewayToken($gatewayToken);
        $vaultPaymentToken->setTokenDetails($tokenDetails);
        $vaultPaymentToken->setPublicHash($this->createPublicHash($cardData, $customerId));
        $this->tokenRepository->save($vaultPaymentToken);
    }

    /**
     * Create public hash.
     *
     * @param Card $addingCard
     * @param string $customerId
     *
     * @return string
     */
    private function createPublicHash($addingCard, $customerId)
    {
        $tokenDetails = $this->jsonSerializer->serialize(
            [
                'type'           => $this->cardTypes[$addingCard->getType()],
                'maskedCC'       => $addingCard->getPartialPan(),
                'expirationDate' => $addingCard->getExpireYear() . '/' . $addingCard->getExpireMonth()
            ]
        );
        return $this->encryptor->getHash(
            $customerId
            . Config::CC_VAULT_CODE
            . $this->cardTypes[$addingCard->getType()]
            . $tokenDetails
        );
    }

    /**
     * Return expires date for credit card from month/year.
     *
     * @param string $expMonth
     * @param string $expYear
     *
     * @return string
     */
    private function getExpiresDate($expMonth, $expYear): string
    {
        $expiresDate = sprintf(
            '%s-%s-01',
            $expYear,
            $expMonth
        );

        return date("Y-m-t 23:59:59", strtotime($expiresDate));
    }

    /**
     * Redirect method.
     *
     * @return ResponseInterface
     */
    private function redirect(): ResponseInterface
    {
        $customRedirectUrl = $this->context->getRequest()->getParam('custom_redirect_url');

        if ($customRedirectUrl) {
            $this->context->getRedirect()->redirect(
                $this->context->getResponse(),
                $customRedirectUrl
            );
        } else {
            $this->context->getRedirect()->redirect(
                $this->context->getResponse(),
                'checkout',
                ['_fragment' => 'payment']
            );
        }

        return $this->context->getResponse();
    }
}
