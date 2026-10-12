<?php

namespace Tests\Feature\Inventory;

use App\Services\Inventory\SerialPrefixResolver;
use Tests\TestCase;

/**
 * Stage 1 — serial prefix mapping contract (plan §2.3).
 *
 * Pure unit tests: no database. The resolver receives an explicit
 * mapping list (prefix / product_id / track_serials) so each analysis
 * loads mappings once and never queries per row.
 */
class SerialMappingTest extends TestCase
{
    /**
     * @return array<int, array{product_id: int, prefix: string, track_serials: bool}>
     */
    private function mappings(): array
    {
        return [
            ['product_id' => 1, 'prefix' => 'ton', 'track_serials' => true],
            ['product_id' => 2, 'prefix' => 'ton9', 'track_serials' => true],
            ['product_id' => 3, 'prefix' => 'tfv', 'track_serials' => true],
            ['product_id' => 4, 'prefix' => 'tfv9', 'track_serials' => true],
            ['product_id' => 5, 'prefix' => 'tte', 'track_serials' => true],
            ['product_id' => 6, 'prefix' => 'tte9', 'track_serials' => true],
        ];
    }

    public function test_resolves_exact_lowercase_serial(): void
    {
        $resolution = SerialPrefixResolver::resolve('ton70505511', $this->mappings());

        $this->assertSame('valid', $resolution->status);
        $this->assertSame(1, $resolution->productId);
        $this->assertSame('ton', $resolution->prefix);
        $this->assertSame('ton70505511', $resolution->normalized);
    }

    public function test_matching_is_case_insensitive(): void
    {
        $resolution = SerialPrefixResolver::resolve('  TON90505511  ', $this->mappings());

        $this->assertSame('valid', $resolution->status);
        $this->assertSame(2, $resolution->productId);
        $this->assertSame('ton9', $resolution->prefix);
        $this->assertSame('ton90505511', $resolution->normalized);
    }

    public function test_longest_prefix_wins_over_shorter(): void
    {
        // ton90505511 starts with both ton and ton9 → belongs to ton9 (plus).
        $resolution = SerialPrefixResolver::resolve('ton90505511', $this->mappings());

        $this->assertSame('valid', $resolution->status);
        $this->assertSame(2, $resolution->productId);
    }

    public function test_longest_prefix_wins_for_tfv_and_tte(): void
    {
        $this->assertSame(4, SerialPrefixResolver::resolve('tfv91234567', $this->mappings())->productId);
        $this->assertSame(6, SerialPrefixResolver::resolve('tte91234567', $this->mappings())->productId);
        $this->assertSame(3, SerialPrefixResolver::resolve('tfv71234567', $this->mappings())->productId);
        $this->assertSame(5, SerialPrefixResolver::resolve('tte71234567', $this->mappings())->productId);
    }

    public function test_converts_persian_digits_to_ascii(): void
    {
        $resolution = SerialPrefixResolver::resolve('ton۹۰۵۰۵۵۱۱', $this->mappings());

        $this->assertSame('valid', $resolution->status);
        $this->assertSame('ton90505511', $resolution->normalized);
    }

    public function test_converts_arabic_digits_to_ascii(): void
    {
        $resolution = SerialPrefixResolver::resolve('ton٩٠٥٠٥٥١١', $this->mappings());

        $this->assertSame('valid', $resolution->status);
        $this->assertSame('ton90505511', $resolution->normalized);
    }

    public function test_preserves_leading_zeros_in_numeric_part(): void
    {
        $resolution = SerialPrefixResolver::resolve('ton00700123', $this->mappings());

        $this->assertSame('valid', $resolution->status);
        $this->assertSame('ton00700123', $resolution->normalized);
    }

    public function test_unknown_prefix_is_reported(): void
    {
        // stn is the real sample-file prefix but must NOT be guessed.
        $resolution = SerialPrefixResolver::resolve('stn90505511', $this->mappings());

        $this->assertSame('unknown_prefix', $resolution->status);
        $this->assertNull($resolution->productId);
        $this->assertSame('stn90505511', $resolution->normalized);
    }

    public function test_untracked_product_never_falls_back_to_shorter_prefix(): void
    {
        $mappings = [
            ['product_id' => 1, 'prefix' => 'ton', 'track_serials' => true],
            ['product_id' => 2, 'prefix' => 'ton9', 'track_serials' => false],
        ];

        $resolution = SerialPrefixResolver::resolve('ton90505511', $mappings);

        $this->assertSame('product_not_tracked', $resolution->status);
        $this->assertSame(2, $resolution->productId);
        $this->assertSame('ton9', $resolution->prefix);
    }

    public function test_empty_value_is_reported_as_empty(): void
    {
        $this->assertSame('empty', SerialPrefixResolver::resolve('', $this->mappings())->status);
        $this->assertSame('empty', SerialPrefixResolver::resolve('   ', $this->mappings())->status);
    }

    public function test_internal_space_is_invalid(): void
    {
        $this->assertSame('invalid_format', SerialPrefixResolver::resolve('ton 90505511', $this->mappings())->status);
    }

    public function test_formula_like_value_is_invalid(): void
    {
        $this->assertSame('invalid_format', SerialPrefixResolver::resolve('=ton90505511', $this->mappings())->status);
    }

    public function test_pure_number_is_invalid(): void
    {
        $this->assertSame('invalid_format', SerialPrefixResolver::resolve('90505511', $this->mappings())->status);
    }

    public function test_short_prefix_is_invalid(): void
    {
        $this->assertSame('invalid_format', SerialPrefixResolver::resolve('to12345678', $this->mappings())->status);
    }

    public function test_overlong_value_is_invalid(): void
    {
        $tooLong = 'ton'.str_repeat('1', 48); // 51 chars > 50 limit

        $this->assertSame('invalid_format', SerialPrefixResolver::resolve($tooLong, $this->mappings())->status);
    }
}
