<?php

use App\Constants\MetalMaturity;
use App\Services\Metal\MetalMaturityService;

it('maps working days to maturity codes', function () {
    $service = new MetalMaturityService();

    expect(MetalMaturity::fromWorkingDays(0))->toBe(MetalMaturity::T0)
        ->and(MetalMaturity::fromWorkingDays(1))->toBe(MetalMaturity::T1)
        ->and(MetalMaturity::fromWorkingDays(2))->toBe(MetalMaturity::T2)
        ->and(MetalMaturity::fromWorkingDays(5))->toBe(MetalMaturity::T2);
});

it('forbids later maturities for earlier orders', function () {
    $service = new MetalMaturityService();

    expect($service->isSourceAllowed(MetalMaturity::T0, MetalMaturity::T0))->toBeTrue()
        ->and($service->isSourceAllowed(MetalMaturity::T0, MetalMaturity::T1))->toBeFalse()
        ->and($service->isSourceAllowed(MetalMaturity::T0, MetalMaturity::T2))->toBeFalse()
        ->and($service->isSourceAllowed(MetalMaturity::T1, MetalMaturity::T2))->toBeFalse()
        ->and($service->isSourceAllowed(MetalMaturity::T1, MetalMaturity::T0))->toBeTrue()
        ->and($service->isSourceAllowed(MetalMaturity::T2, MetalMaturity::T1))->toBeTrue();
});

it('orders coverage priority correctly', function () {
    expect(MetalMaturity::coveragePriority(MetalMaturity::T2))
        ->toBe([MetalMaturity::T2, MetalMaturity::T1, MetalMaturity::T0]);
});
