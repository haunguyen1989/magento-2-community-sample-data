<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Bundle\Test\Unit\Pricing\Price;

use Magento\Bundle\Pricing\Price\FinalPrice;
use Magento\Catalog\Pricing\Price\RegularPrice;

/**
 * Class BundleSelectionPriceTest
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class BundleSelectionPriceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Magento\Bundle\Pricing\Price\BundleSelectionPrice
     */
    protected $selectionPrice;

    /**
     * @var \Magento\Framework\Pricing\Adjustment\CalculatorInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $calculatorMock;

    /**
     * @var \Magento\Catalog\Model\Product|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $productMock;

    /**
     * @var \Magento\Catalog\Model\Product|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $bundleMock;

    /**
     * @var \Magento\Framework\Event\Manager|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $eventManagerMock;

    /**
     * @var \Magento\Framework\Pricing\PriceInfo\Base|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $priceInfoMock;

    /**
     * @var \Magento\Catalog\Pricing\Price\FinalPrice|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $finalPriceMock;

    /**
     * @var \Magento\Catalog\Pricing\Price\RegularPrice|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $regularPriceMock;

    /**
     * @var \Magento\Bundle\Pricing\Price\DiscountCalculator|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $discountCalculatorMock;

    /**
     * @var \Magento\Framework\Pricing\PriceCurrencyInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $priceCurrencyMock;

    /**
     * Test setUp
     */
    protected function setUp()
    {
        $this->productMock = $this->getMock(
            \Magento\Catalog\Model\Product::class,
            ['__wakeup', 'getPriceInfo', 'getSelectionPriceType', 'getSelectionPriceValue'],
            [],
            '',
            false
        );

        $this->bundleMock = $this->getMock(
            \Magento\Catalog\Model\Product::class,
            ['__wakeup', 'getPriceType', 'getPriceInfo', 'setFinalPrice', 'getData'],
            [],
            '',
            false
        );
        $this->calculatorMock = $this->getMockBuilder(\Magento\Framework\Pricing\Adjustment\CalculatorInterface::class)
            ->getMockForAbstractClass();
        $this->eventManagerMock = $this->getMock(
            \Magento\Framework\Event\Manager::class,
            ['dispatch'],
            [],
            '',
            false
        );
        $this->priceInfoMock = $this->getMock(
            \Magento\Framework\Pricing\PriceInfo\Base::class,
            ['getPrice'],
            [],
            '',
            false
        );
        $this->discountCalculatorMock = $this->getMock(
            \Magento\Bundle\Pricing\Price\DiscountCalculator::class,
            [],
            [],
            '',
            false
        );
        $this->finalPriceMock = $this->getMock(
            \Magento\Catalog\Pricing\Price\FinalPrice::class,
            [],
            [],
            '',
            false
        );
        $this->regularPriceMock = $this->getMock(
            \Magento\Catalog\Pricing\Price\RegularPrice::class,
            [],
            [],
            '',
            false
        );
        $this->productMock->expects($this->atLeastOnce())
            ->method('getPriceInfo')
            ->will($this->returnValue($this->priceInfoMock));

        $this->priceCurrencyMock = $this->getMock(\Magento\Framework\Pricing\PriceCurrencyInterface::class);

        $this->setupSelectionPrice();
    }

    /**
     * @param bool $useRegularPrice
     * @param int $qty
     */
    protected function setupSelectionPrice($useRegularPrice = false, $qty = 1)
    {
        $this->selectionPrice = new \Magento\Bundle\Pricing\Price\BundleSelectionPrice(
            $this->productMock,
            $qty,
            $this->calculatorMock,
            $this->priceCurrencyMock,
            $this->bundleMock,
            $this->eventManagerMock,
            $this->discountCalculatorMock,
            $useRegularPrice
        );
    }

    /**
     *  test fro method getValue with dynamic productType
     *
     * @param bool $useRegularPrice
     * @dataProvider useRegularPriceDataProvider
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function testGetValueTypeDynamic($useRegularPrice)
    {
        $this->setupSelectionPrice($useRegularPrice);
        $priceCode = $useRegularPrice ? RegularPrice::PRICE_CODE : FinalPrice::PRICE_CODE;
        $regularPrice = 100.125;
        $discountedPrice = 70.453;
        $actualPrice = $useRegularPrice ? $regularPrice : $discountedPrice;
        $expectedPrice = $useRegularPrice ? round($regularPrice, 2) : round($discountedPrice, 2);

        $this->bundleMock->expects($this->once())
            ->method('getPriceType')
            ->will($this->returnValue(\Magento\Bundle\Model\Product\Price::PRICE_TYPE_DYNAMIC));
        $this->priceInfoMock->expects($this->once())
            ->method('getPrice')
            ->with($this->equalTo($priceCode))
            ->will($this->returnValue($this->finalPriceMock));
        $this->finalPriceMock->expects($this->once())
            ->method('getValue')
            ->will($this->returnValue($actualPrice));

        if (!$useRegularPrice) {
            $this->discountCalculatorMock->expects($this->once())
                ->method('calculateDiscount')
                ->with(
                    $this->equalTo($this->bundleMock),
                    $this->equalTo($actualPrice)
                )
                ->will($this->returnValue($discountedPrice));
        }

        $this->priceCurrencyMock->expects($this->once())
            ->method('round')
            ->with($actualPrice)
            ->will($this->returnValue($expectedPrice));

        $this->assertEquals($expectedPrice, $this->selectionPrice->getValue());
    }

    /**
     * test for method getValue with type Fixed and selectionPriceType not null
     *
     * @param bool $useRegularPrice
     * @dataProvider useRegularPriceDataProvider
     */
    public function testGetValueTypeFixedWithSelectionPriceType($useRegularPrice)
    {
        $this->setupSelectionPrice($useRegularPrice);
        $regularPrice = 100.125;
        $discountedPrice = 70.453;
        $actualPrice = $useRegularPrice ? $regularPrice : $discountedPrice;
        $expectedPrice = $useRegularPrice ? round($regularPrice, 2) : round($discountedPrice, 2);

        $this->bundleMock->expects($this->once())
            ->method('getPriceType')
            ->will($this->returnValue(\Magento\Bundle\Model\Product\Price::PRICE_TYPE_FIXED));
        $this->bundleMock->expects($this->atLeastOnce())
            ->method('getPriceInfo')
            ->will($this->returnValue($this->priceInfoMock));
        $this->priceInfoMock->expects($this->once())
            ->method('getPrice')
            ->with($this->equalTo(RegularPrice::PRICE_CODE))
            ->will($this->returnValue($this->regularPriceMock));
        $this->regularPriceMock->expects($this->once())
            ->method('getValue')
            ->will($this->returnValue($actualPrice));
        $this->bundleMock->expects($this->once())
            ->method('setFinalPrice')
            ->will($this->returnSelf());
        $this->eventManagerMock->expects($this->once())
            ->method('dispatch');
        $this->bundleMock->expects($this->exactly(2))
            ->method('getData')
            ->will(
                $this->returnValueMap(
                    [
                        ['qty', null, 1],
                        ['final_price', null, 100],
                    ]
                )
            );
        $this->productMock->expects($this->once())
            ->method('getSelectionPriceType')
            ->will($this->returnValue(true));
        $this->productMock->expects($this->any())
            ->method('getSelectionPriceValue')
            ->will($this->returnValue($actualPrice));

        if (!$useRegularPrice) {
            $this->discountCalculatorMock->expects($this->once())
                ->method('calculateDiscount')
                ->with(
                    $this->equalTo($this->bundleMock),
                    $this->equalTo($actualPrice)
                )
                ->will($this->returnValue($discountedPrice));
        }

        $this->priceCurrencyMock->expects($this->once())
            ->method('round')
            ->with($actualPrice)
            ->will($this->returnValue($expectedPrice));

        $this->assertEquals($expectedPrice, $this->selectionPrice->getValue());
    }

    /**
     * test for method getValue with type Fixed and selectionPriceType is empty or zero
     *
     * @param bool $useRegularPrice
     * @dataProvider useRegularPriceDataProvider
     */
    public function testGetValueTypeFixedWithoutSelectionPriceType($useRegularPrice)
    {
        $this->setupSelectionPrice($useRegularPrice);
        $regularPrice = 100.125;
        $discountedPrice = 70.453;
        $convertedValue = 100.247;
        $actualPrice = $useRegularPrice ? $convertedValue : $discountedPrice;
        $expectedPrice = $useRegularPrice ? round($convertedValue, 2) : round($discountedPrice, 2);

        $this->bundleMock->expects($this->once())
            ->method('getPriceType')
            ->will($this->returnValue(\Magento\Bundle\Model\Product\Price::PRICE_TYPE_FIXED));
        $this->productMock->expects($this->once())
            ->method('getSelectionPriceType')
            ->will($this->returnValue(false));
        $this->productMock->expects($this->any())
            ->method('getSelectionPriceValue')
            ->will($this->returnValue($regularPrice));

        $this->priceCurrencyMock->expects($this->once())
            ->method('convert')
            ->with($regularPrice)
            ->will($this->returnValue($convertedValue));

        if (!$useRegularPrice) {
            $this->discountCalculatorMock->expects($this->once())
                ->method('calculateDiscount')
                ->with(
                    $this->equalTo($this->bundleMock),
                    $this->equalTo($convertedValue)
                )
                ->will($this->returnValue($discountedPrice));
        }

        $this->priceCurrencyMock->expects($this->once())
            ->method('round')
            ->with($actualPrice)
            ->will($this->returnValue($expectedPrice));

        $this->assertEquals($expectedPrice, $this->selectionPrice->getValue());
    }

    /**
     * @return array
     */
    public function useRegularPriceDataProvider()
    {
        return [
            'useRegularPrice' => [
                true,
            ],
            'notUseRegularPrice' => [
                false,
            ],
        ];
    }

    public function testGetProductFixedBundle()
    {
        $this->bundleMock->expects($this->any())
            ->method('getPriceType')
            ->will($this->returnValue(\Magento\Bundle\Model\Product\Price::PRICE_TYPE_FIXED));
        $product = $this->selectionPrice->getProduct();
        $this->assertEquals($this->bundleMock, $product);
    }

    public function testGetProductDynamicBundle()
    {
        $this->bundleMock->expects($this->any())
            ->method('getPriceType')
            ->will($this->returnValue(\Magento\Bundle\Model\Product\Price::PRICE_TYPE_DYNAMIC));
        $product = $this->selectionPrice->getProduct();
        $this->assertEquals($this->productMock, $product);
    }

    public function testGetAmount()
    {
        $this->setupSelectionPrice();

        $price = 10.;
        $amount = 20.;

        $this->priceInfoMock->expects($this->once())
            ->method('getPrice')
            ->with(\Magento\Bundle\Pricing\Price\FinalPrice::PRICE_CODE)
            ->willReturn($this->finalPriceMock);

        $this->finalPriceMock->expects($this->once())
            ->method('getValue')
            ->willReturn($price);

        $this->discountCalculatorMock->expects($this->once())
            ->method('calculateDiscount')
            ->with($this->bundleMock, $price)
            ->willReturn($price);

        $this->priceCurrencyMock->expects($this->once())
            ->method('round')
            ->with($price)
            ->willReturn($price);

        $this->bundleMock->expects($this->any())
            ->method('getPriceType')
            ->willReturn(\Magento\Bundle\Model\Product\Price::PRICE_TYPE_DYNAMIC);

        $this->calculatorMock->expects($this->once())
            ->method('getAmount')
            ->with($price, $this->productMock, null)
            ->willReturn($amount);

        $this->assertEquals($amount, $this->selectionPrice->getAmount());
    }

    /**
     * Test for method getValue with type Fixed and selectionPriceType is empty or zero.
     *
     * @param bool $useRegularPrice
     * @return void
     *
     * @dataProvider useRegularPriceDataProvider
     */
    public function testFixedPriceWithMultipleQty($useRegularPrice)
    {
        $qty = 2;

        $this->setupSelectionPrice($useRegularPrice, $qty);
        $regularPrice = 100.125;
        $discountedPrice = 70.453;
        $convertedValue = 100.247;
        $actualPrice = $useRegularPrice ? $convertedValue : $discountedPrice;
        $expectedPrice = $useRegularPrice ? round($convertedValue, 2) : round($discountedPrice, 2);

        $this->bundleMock->expects($this->once())
            ->method('getPriceType')
            ->will($this->returnValue(\Magento\Bundle\Model\Product\Price::PRICE_TYPE_FIXED));
        $this->productMock->expects($this->once())
            ->method('getSelectionPriceType')
            ->will($this->returnValue(false));
        $this->productMock->expects($this->any())
            ->method('getSelectionPriceValue')
            ->will($this->returnValue($regularPrice));

        $this->priceCurrencyMock->expects($this->once())
            ->method('convert')
            ->with($regularPrice)
            ->will($this->returnValue($convertedValue));

        if (!$useRegularPrice) {
            $this->discountCalculatorMock->expects($this->once())
                ->method('calculateDiscount')
                ->with(
                    $this->equalTo($this->bundleMock),
                    $this->equalTo($convertedValue)
                )
                ->will($this->returnValue($discountedPrice));
        }

        $this->priceCurrencyMock->expects($this->once())
            ->method('round')
            ->with($actualPrice)
            ->will($this->returnValue($expectedPrice));

        $this->assertEquals($expectedPrice, $this->selectionPrice->getValue());
    }
}
