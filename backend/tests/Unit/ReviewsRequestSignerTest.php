<?php

namespace Tests\Unit;

use App\Services\Yandex\ReviewsRequestSigner;
use PHPUnit\Framework\TestCase;

class ReviewsRequestSignerTest extends TestCase
{
    private ReviewsRequestSigner $signer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signer = new ReviewsRequestSigner;
    }

    /**
     * Контрольный вектор снят с живого запроса: с этой подписью Яндекс
     * ответил 200 и отдал отзывы. Если тест упадёт — алгоритм разъехался
     * с тем, что считает фронт Яндекса, и парсер начнёт получать 400.
     *
     * @return array{0: array<string, string>, 1: string}
     */
    public static function knownVector(): array
    {
        return [
            [
                'ajax' => '1',
                'businessId' => '1124715036',
                'csrfToken' => '995376acd3fa959b15e031c5127053b537e96e90:1789151481',
                'locale' => 'ru_RU',
                'page' => '1',
                'pageSize' => '50',
                'ranking' => 'by_time',
                'sessionId' => '1789151481527016-14773805336183489435-balancer-l7leveler-kubr-yp-sas-195-BAL',
            ],
            '3006753564',
        ];
    }

    public function test_it_reproduces_the_signature_yandex_accepted(): void
    {
        [$params, $expected] = self::knownVector();

        $this->assertSame($expected, $this->signer->sign($params));
    }

    public function test_it_builds_the_same_query_string_as_the_yandex_frontend(): void
    {
        [$params] = self::knownVector();

        $this->assertSame(
            'ajax=1&businessId=1124715036'
            .'&csrfToken=995376acd3fa959b15e031c5127053b537e96e90%3A1789151481'
            .'&locale=ru_RU&page=1&pageSize=50&ranking=by_time'
            .'&sessionId=1789151481527016-14773805336183489435-balancer-l7leveler-kubr-yp-sas-195-BAL',
            $this->signer->queryString($params),
        );
    }

    public function test_keys_are_sorted_ignoring_case(): void
    {
        // «page» должен идти раньше «pageSize», а «Zebra» — раньше «apple»
        // только при чувствительной сортировке, которой здесь быть не должно.
        $query = $this->signer->queryString([
            'pageSize' => '50',
            'Zebra' => '1',
            'apple' => '2',
            'page' => '1',
        ]);

        $this->assertSame('apple=2&page=1&pageSize=50&Zebra=1', $query);
    }

    public function test_values_are_percent_encoded_per_rfc_3986(): void
    {
        $query = $this->signer->queryString(['token' => 'a:b c/d']);

        $this->assertSame('token=a%3Ab%20c%2Fd', $query);
    }

    public function test_with_signature_appends_the_s_parameter(): void
    {
        [$params, $expected] = self::knownVector();

        $signed = $this->signer->withSignature($params);

        $this->assertSame($expected, $signed['s']);
        $this->assertSame($params['businessId'], $signed['businessId']);
    }

    public function test_signature_is_an_unsigned_32_bit_number(): void
    {
        // Умножение на 33 быстро переполняет int32; проверяем, что результат
        // не уходит в отрицательные числа, как было бы без приведения `>>> 0`.
        $signature = (int) $this->signer->sign(['a' => str_repeat('x', 512)]);

        $this->assertGreaterThanOrEqual(0, $signature);
        $this->assertLessThanOrEqual(0xFFFFFFFF, $signature);
    }
}
