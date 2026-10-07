<?php

namespace Vulpo\Seo\Schema;

use Vulpo\Seo\Schema\Support\Normalize;
use Vulpo\Seo\Support\Settings;

/**
 * MerchantReturnPolicy: how long a customer has, and who pays the postage.
 *
 * In the EU the answer is usually the statutory fourteen days, which is a fact
 * about the shop rather than about any one product -- hence fromSettings().
 */
final class ReturnPolicy
{
    private ?int $days = null;

    private bool $permitted = true;

    /** @var array<int, string> */
    private array $countries = [];

    private ?string $fees = null;

    private string $method = 'ReturnByMail';

    public static function make(): self
    {
        return new self;
    }

    public static function fromSettings(): ?self
    {
        $days = Settings::get('shop_return_days');

        if (! is_numeric($days)) {
            return null;
        }

        $policy = (int) $days > 0 ? self::make()->days((int) $days) : self::make()->noReturns();

        if ($countries = Settings::list('shop_return_countries')) {
            $policy->country(...array_map('strval', $countries));
        }

        if ($fees = Settings::string('shop_return_fees')) {
            $policy->fees($fees);
        }

        return $policy;
    }

    public function days(int $days): self
    {
        $this->days = max(0, $days);
        $this->permitted = $this->days > 0;

        return $this;
    }

    public function noReturns(): self
    {
        $this->permitted = false;
        $this->days = null;

        return $this;
    }

    public function country(string ...$codes): self
    {
        foreach ($codes as $code) {
            if ($code = Normalize::country($code)) {
                $this->countries[] = $code;
            }
        }

        $this->countries = array_values(array_unique($this->countries));

        return $this;
    }

    /** FreeReturn, ReturnShippingFees or RestockingFees. */
    public function fees(string $fee = 'FreeReturn'): self
    {
        $this->fees = $fee;

        return $this;
    }

    public function method(string $method = 'ReturnByMail'): self
    {
        $this->method = $method;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function toArray(SchemaContext $context): ?array
    {
        if (! $this->permitted) {
            return [
                '@type' => 'MerchantReturnPolicy',
                'returnPolicyCategory' => 'https://schema.org/MerchantReturnNotPermitted',
            ];
        }

        if ($this->days === null) {
            return null;
        }

        return Normalize::compact([
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => $this->countries,
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays' => $this->days,
            'returnMethod' => 'https://schema.org/'.$this->method,
            'returnFees' => $this->fees ? 'https://schema.org/'.$this->fees : null,
        ]);
    }
}
