<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\InvoiceService;

class InvoiceServiceTest extends TestCase
{
    /** @test */
    public function currencySymbol_returns_expected_symbols_for_known_codes()
    {
        $this->assertSame('Rs.', InvoiceService::currencySymbol('INR'));
        $this->assertSame('$', InvoiceService::currencySymbol('USD'));
        $this->assertSame('€', InvoiceService::currencySymbol('EUR'));
        $this->assertSame('£', InvoiceService::currencySymbol('GBP'));
        $this->assertSame('AED', InvoiceService::currencySymbol('AED'));
        $this->assertSame('SAR', InvoiceService::currencySymbol('SAR'));
        $this->assertSame('S$', InvoiceService::currencySymbol('SGD'));
        $this->assertSame('RM', InvoiceService::currencySymbol('MYR'));
        $this->assertSame('A$', InvoiceService::currencySymbol('AUD'));
        $this->assertSame('C$', InvoiceService::currencySymbol('CAD'));
    }

    /** @test */
    public function currencySymbol_is_case_insensitive()
    {
        $this->assertSame('Rs.', InvoiceService::currencySymbol('inr'));
        $this->assertSame('$', InvoiceService::currencySymbol('usd'));
    }

    /** @test */
    public function currencySymbol_falls_back_to_dollar_for_unknown_codes()
    {
        $this->assertSame('$', InvoiceService::currencySymbol('XXX'));
        $this->assertSame('$', InvoiceService::currencySymbol(''));
        $this->assertSame('$', InvoiceService::currencySymbol(null));
    }
}
