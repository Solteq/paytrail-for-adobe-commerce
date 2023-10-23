<?php


use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Model\Order;
use Paytrail\PaymentService\Model\FinnishReferenceNumber;
use Paytrail\PaymentService\Model\Receipt\ProcessPayment;
use Paytrail\PaymentService\Controller\Receipt\Index;
use PHPUnit\Framework\TestCase;

class ReceiptIndexUnitTest extends TestCase
{
    private $referenceNumberMock;
    private $sessionMock;
    private $processPaymentMock;
    private $requestMock;
    private $resultFactoryMock;
    private $messageManagerMock;

    private $orderMock;

    private $resultInterfaceMock;

    private function getSimpleMock($originalClassName)
    {
        return $this->getMockBuilder($originalClassName)
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function setUp(): void
    {
        $this->referenceNumberMock = $this->getSimpleMock(FinnishReferenceNumber::class);
        $this->sessionMock = $this->getSimpleMock(Session::class);
        $this->processPaymentMock = $this->getSimpleMock(ProcessPayment::class);
        $this->requestMock = $this->getSimpleMock(RequestInterface::class);
        $this->resultFactoryMock = $this->getSimpleMock(ResultFactory::class);
        $this->messageManagerMock = $this->getSimpleMock(ManagerInterface::class);

        $this->indexController = new Index(
            $this->referenceNumberMock,
            $this->sessionMock,
            $this->processPaymentMock,
            $this->requestMock,
            $this->resultFactoryMock,
            $this->messageManagerMock
        );

        $this->quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->setMethods(['setIsActive'])
            ->getMock();
//        $this->resultInterfaceMock = $this->getSimpleMock(\Magento\Framework\Controller\ResultInterface::class);
//        $this->resultInterfaceMock = $this->getMockClass(\Magento\Framework\Controller\ResultInterface::class, ['setPath']);
        $methods = ['setPath', 'setHeader', 'setHttpResponseCode', 'renderResult'];
        $this->resultInterfaceMock = $this->getMockBuilder(\Magento\Framework\Controller\ResultInterface::class)
            ->setMethods($methods)
            ->disableOriginalConstructor()
            ->getMock();
        $this->quoteRepositoryMock = $this->getSimpleMock(QuoteRepository::class);
        $this->orderMock = $this->getSimpleMock(Order::class);
    }

    public function testExecute()
    {
        $this->requestMock
            ->expects($this->atLeast(1))
            ->method('getParam')
            ->willReturn('checkout-reference');

        $this->referenceNumberMock
            ->expects($this->once())
            ->method('getOrderByReference')
            ->willReturn($this->orderMock);

        $this->orderMock
            ->expects($this->once())
            ->method('getStatus')
            ->willReturn('processing');

        $this->resultFactoryMock
            ->expects($this->once())
            ->method('create')
            ->willReturn($this->resultInterfaceMock);

        $this->resultInterfaceMock
            ->method('setPath')
            ->willReturn($this->resultInterfaceMock);

        $this->indexController->execute();
    }
}
