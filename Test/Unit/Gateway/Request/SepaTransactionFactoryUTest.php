<?php
/**
 * Shop System Plugins - Terms of Use
 *
 * The plugins offered are provided free of charge by Wirecard Central Eastern Europe GmbH
 * (abbreviated to Wirecard CEE) and are explicitly not part of the Wirecard CEE range of
 * products and services.
 *
 * They have been tested and approved for full functionality in the standard configuration
 * (status on delivery) of the corresponding shop system. They are under General Public
 * License Version 3 (GPLv3) and can be used, developed and passed on to third parties under
 * the same terms.
 *
 * However, Wirecard CEE does not provide any guarantee or accept any liability for any errors
 * occurring when used in an enhanced, customized shop system configuration.
 *
 * Operation in an enhanced, customized configuration is at your own risk and requires a
 * comprehensive test phase by the user of the plugin.
 *
 * Customers use the plugins at their own risk. Wirecard CEE does not guarantee their full
 * functionality neither does Wirecard CEE assume liability for any disadvantages related to
 * the use of the plugins. Additionally, Wirecard CEE does not guarantee the full functionality
 * for customized shop systems or installed plugins of other vendors of plugins within the same
 * shop system.
 *
 * Customers are responsible for testing the plugin's functionality before starting productive
 * operation.
 *
 * By installing the plugin into the shop system the customer agrees to these terms of use.
 * Please do not use the plugin if you do not agree to these terms of use!
 */

namespace Wirecard\ElasticEngine\Test\Unit\Gateway\Request;

use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\AddressAdapterInterface;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Wirecard\ElasticEngine\Gateway\Request\SepaTransactionFactory;
use Wirecard\PaymentSdk\Entity\AccountHolder;
use Wirecard\PaymentSdk\Entity\Amount;
use Wirecard\PaymentSdk\Entity\CustomField;
use Wirecard\PaymentSdk\Entity\CustomFieldCollection;
use Wirecard\PaymentSdk\Entity\Redirect;
use Wirecard\PaymentSdk\Entity\Mandate;
use Wirecard\PaymentSdk\Transaction\SepaTransaction;

class SepaTransactionFactoryUTest extends \PHPUnit_Framework_TestCase
{
    const ORDER_ID = '1234567';

    private $urlBuilder;

    private $resolver;

    private $storeManager;

    private $config;

    private $payment;

    private $order;

    private $paymentInfo;

    private $commandSubject;

    public function setUp()
    {
        $this->urlBuilder = $this->getMockBuilder(UrlInterface::class)->disableOriginalConstructor()->getMock();
        $this->urlBuilder->method('getRouteUrl')->willReturn('http://magen.to/');

        $this->resolver = $this->getMockBuilder(ResolverInterface::class)->disableOriginalConstructor()->getMock();
        $this->resolver->method('getLocale')->willReturn('en_US');

        $store = $this->getMockBuilder(StoreInterface::class)->disableOriginalConstructor()->getMock();
        $store->method('getName')->willReturn('My shop name');

        $this->storeManager = $this->getMockBuilder(StoreManagerInterface::class)->disableOriginalConstructor()->getMock();
        $this->storeManager->method('getStore')->willReturn($store);

        $this->config = $this->getMockBuilder(ConfigInterface::class)->disableOriginalConstructor()->getMock();

        $address = $this->getMockBuilder(AddressAdapterInterface::class)->disableOriginalConstructor()->getMock();
        $address->method('getEmail')->willReturn('test@example.com');
        $address->method('getFirstname')->willReturn('Joe');
        $address->method('getLastname')->willReturn('Doe');

        $this->order = $this->getMockBuilder(OrderAdapterInterface::class)
            ->disableOriginalConstructor()->getMock();
        $this->order->method('getOrderIncrementId')->willReturn(self::ORDER_ID);
        $this->order->method('getBillingAddress')->willReturn($address);
        $this->order->method('getShippingAddress')->willReturn($address);
        $this->order->method('getGrandTotalAmount')->willReturn('1.0');
        $this->order->method('getCurrencyCode')->willReturn('EUR');

        $this->paymentInfo = $this->getMock(InfoInterface::class);

        $this->payment = $this->getMockBuilder(PaymentDataObjectInterface::class)
            ->disableOriginalConstructor()->getMock();
        $this->payment->method('getOrder')->willReturn($this->order);

        $this->commandSubject = ['payment' => $this->payment];
    }

    public function testCreateMinimum()
    {
        $transaction = new SepaTransaction();
        $transactionFactory = new SepaTransactionFactory($this->urlBuilder, $this->resolver, $this->storeManager, $transaction, $this->config);

        $expected = $this->minimumExpectedTransaction();

        $this->assertEquals($expected, $transactionFactory->create($this->commandSubject));
    }

    public function testCreateSetsBic()
    {
        $this->config->expects($this->at(0))->method('getValue')->willReturn(true);
        $transaction = new SepaTransaction();
        $transactionFactory = new SepaTransactionFactory($this->urlBuilder, $this->resolver, $this->storeManager, $transaction, $this->config);

        $expected = $this->minimumExpectedTransaction();
        $expected->setBic('WIREDEMMXXX');

        $this->assertEquals($expected, $transactionFactory->create($this->commandSubject));
    }

    /**
     * @return SepaTransaction
     */
    private function minimumExpectedTransaction()
    {
        $additionalInfo = array(
            'bankBic' => 'WIREDEMMXXX',
            'bankAccountIban' => 'DE42512308000000060004',
            'accountFirstName' => 'Jane',
            'accountLastName' => 'Doe'
        );
        $this->payment->expects(static::once())
            ->method('getPayment')
            ->willReturn($this->paymentInfo);

        $this->paymentInfo->expects(static::once())
            ->method('getAdditionalInformation')
            ->willReturn($additionalInfo);

        $expected = new SepaTransaction();

        $expected->setAmount(new Amount(1.0, 'EUR'));
        $expected->setNotificationUrl('http://magen.to/frontend/notify');
        $expected->setRedirect(new Redirect(
            'http://magen.to/frontend/redirect',
            'http://magen.to/frontend/cancel',
            'http://magen.to/frontend/redirect'));

        $customFields = new CustomFieldCollection();
        $customFields->add(new CustomField('orderId', self::ORDER_ID));
        $expected->setCustomFields($customFields);
        $expected->setLocale('en');
        $expected->setEntryMode('ecommerce');

        $mandate = new Mandate('12345678');

        $accountHolder = new AccountHolder();
        $accountHolder->setFirstName('Jane');
        $accountHolder->setLastName('Doe');
        $expected->setAccountHolder($accountHolder);
        $expected->setIban('DE42512308000000060004');
        $expected->setMandate($mandate);

        return $expected;
    }
}
