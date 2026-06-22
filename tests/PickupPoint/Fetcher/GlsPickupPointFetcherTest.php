<?php

declare(strict_types=1);

namespace App\Tests\PickupPoint\Fetcher;

use App\Enum\Carrier;
use App\Enum\PickupPointStatus;
use App\Enum\PickupPointType;
use App\Model\Country;
use App\PickupPoint\Fetcher\FetchConfig;
use App\PickupPoint\Fetcher\GlsPickupPointFetcher;
use App\PickupPoint\Fetcher\PickupPointData;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * Unit tests for the GLS XML -> PickupPointData mapping.
 *
 * The HTTP layer is stubbed with MockHttpClient and the real Serializer/XmlEncoder is used on
 * purpose: the attribute type-casting performed while decoding the XML is part of the behaviour
 * under test (it is what turns IsParcelLocker="1" and ZipCode="58601" into usable values).
 */
final class GlsPickupPointFetcherTest extends TestCase
{
    public function testMapsAllDropoffPointsFromRealResponse(): void
    {
        $points = $this->fetch($this->realResponse());

        self::assertCount(8, $points);
        self::assertContainsOnlyInstancesOf(PickupPointData::class, $points);
    }

    public function testMapsParcelLockerAsBoxWithAllFields(): void
    {
        $box = $this->fetch($this->realResponse())[0];

        self::assertSame('CZ58601-PARCELLOCK01', $box->id);
        self::assertSame(Carrier::GLS, $box->carrier);
        self::assertSame(PickupPointType::BOX, $box->type);
        self::assertSame(PickupPointStatus::AVAILABLE, $box->status);
        self::assertSame('Jihlava', $box->city);
        self::assertSame('GLS BOX', $box->name);
        self::assertSame('S. K. Neumanna 590/22', $box->address);
        self::assertSame('58601', $box->zipCode);
        self::assertSame('CZ', $box->country);
        self::assertEqualsWithDelta(49.4048, $box->latitude, 1e-7);
        self::assertEqualsWithDelta(15.557, $box->longitude, 1e-7);
        self::assertStringContainsString('Monday 00:00-24:00', (string) $box->openingHours);
    }

    public function testMapsRegularPointAsPointType(): void
    {
        $point = $this->fetch($this->realResponse())[1];

        self::assertSame('39301-ELPESRO', $point->id);
        self::assertSame(PickupPointType::POINT, $point->type);
    }

    public function testParsesOpeningHoursAndSkipsDaysWithoutHours(): void
    {
        // 39301-ELPESRO is open Mon–Sat and has an empty OpenHours for Sunday.
        $hours = (string) $this->fetch($this->realResponse())[1]->openingHours;

        self::assertStringContainsString('Saturday 08:00-11:00', $hours);
        self::assertStringNotContainsString('Sunday', $hours);
        self::assertSame(6, substr_count($hours, "\n") + 1);
    }

    public function testUsesLowercaseIdAttributeWhenPresent(): void
    {
        $xml = $this->dropoffData('CZ', implode('', [
            $this->dropoffPoint('Id="LOWER-1" Name="X" Address="A" CtrCode="CZ" ZipCode="10000" CityName="Praha" IsParcelLocker="0" GeoLat="50.0" GeoLng="14.0"'),
            $this->dropoffPoint('ID="UPPER-2" Name="Y" Address="B" CtrCode="CZ" ZipCode="20000" CityName="Brno" IsParcelLocker="0" GeoLat="49.2" GeoLng="16.6"'),
        ]));

        $points = $this->fetch($xml);

        self::assertSame('LOWER-1', $points[0]->id);
        self::assertSame('UPPER-2', $points[1]->id);
    }

    public function testSkipsDropoffPointsWithoutId(): void
    {
        $xml = $this->dropoffData('CZ', implode('', [
            $this->dropoffPoint('ID="HAS-ID" Name="X" Address="A" CtrCode="CZ" ZipCode="10000" CityName="Praha" IsParcelLocker="0" GeoLat="50.0" GeoLng="14.0"'),
            $this->dropoffPoint('Name="NoId" Address="B" CtrCode="CZ" ZipCode="20000" CityName="Brno" IsParcelLocker="0" GeoLat="49.0" GeoLng="16.0"'),
        ]));

        $points = $this->fetch($xml);

        self::assertCount(1, $points);
        self::assertSame('HAS-ID', $points[0]->id);
    }

    public function testThrowsWhenResponseCountryDoesNotMatchRequested(): void
    {
        // Response reports DE while CZ was requested.
        $xml = $this->dropoffData('DE', $this->dropoffPoint(
            'ID="X" Name="X" Address="A" CtrCode="DE" ZipCode="10000" CityName="Berlin" IsParcelLocker="0" GeoLat="52.5" GeoLng="13.4"'
        ));

        $this->expectException(\RuntimeException::class);

        $this->fetch($xml, 'CZ');
    }

    /**
     * Documents the desired behaviour for a point without an <Openings> element: it should map
     * with openingHours = null.
     *
     * CURRENTLY EXPECTED TO FAIL (red): parseOpeningHours(array $openings) is non-nullable and is
     * called with `$item['Openings'] ?? null`, so a missing <Openings> raises a TypeError. This
     * test goes green once the parameter accepts null (the fix discussed during review).
     */
    public function testReturnsNullOpeningHoursWhenOpeningsMissing(): void
    {
        $withOpenings = $this->dropoffPoint('ID="WITH" Name="X" Address="A" CtrCode="CZ" ZipCode="10000" CityName="Praha" IsParcelLocker="0" GeoLat="50.0" GeoLng="14.0"');
        $withoutOpenings = '<DropoffPoint ID="NO-OPENINGS" Name="Y" Address="B" CtrCode="CZ" ZipCode="20000" CityName="Brno" IsParcelLocker="0" GeoLat="49.0" GeoLng="16.0"></DropoffPoint>';

        $points = $this->fetch($this->dropoffData('CZ', $withOpenings . $withoutOpenings));

        self::assertCount(2, $points);
        self::assertNull($points[1]->openingHours);
    }

    /**
     * Runs the fetcher against a stubbed HTTP response and returns the mapped points.
     *
     * @return list<PickupPointData>
     */
    private function fetch(string $responseXml, string $requestedCountry = 'CZ'): array
    {
        $serializer = new Serializer([], [new XmlEncoder()]);
        $httpClient = new MockHttpClient(new MockResponse($responseXml));

        $fetcher = new GlsPickupPointFetcher($httpClient, $serializer);

        return iterator_to_array($fetcher->fetch(new FetchConfig(new Country($requestedCountry))), false);
    }

    private function realResponse(): string
    {
        return (string) file_get_contents(__DIR__ . '/../../Fixtures/gls_parcelshop.xml');
    }

    /**
     * Wraps dropoff points in the GLS envelope (DropoffData > CtrCode + Data).
     */
    private function dropoffData(string $ctrCode, string $dropoffPoints): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<DropoffData>'
            . '<CtrCode>' . $ctrCode . '</CtrCode>'
            . '<Updated>2026-06-22T10:00:38</Updated>'
            . '<Data>' . $dropoffPoints . '</Data>'
            . '</DropoffData>';
    }

    /**
     * Builds a single <DropoffPoint> from its attribute string. Always emits two opening days so
     * the decoded <Openings> is a list (a single child would decode to a non-list — a separate
     * edge case not under test here).
     */
    private function dropoffPoint(string $attributes): string
    {
        return '<DropoffPoint ' . $attributes . '>'
            . '<Openings>'
            . '<Openings Day="Monday" OpenHours="08:00-16:00" MidBreak=""/>'
            . '<Openings Day="Tuesday" OpenHours="08:00-16:00" MidBreak=""/>'
            . '</Openings>'
            . '</DropoffPoint>';
    }
}
