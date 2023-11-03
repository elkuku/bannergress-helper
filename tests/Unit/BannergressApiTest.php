<?php

namespace App\Tests\Unit;

use App\Service\BannergressApi;
use PHPUnit\Framework\TestCase;

class BannergressApiTest extends TestCase
{
    public function testDefaultUri(): void
    {
        $api = new BannergressApi();

        self::assertSame(
            'https://api.bannergress.com/bnrs',
            $api->buildUri()
        );
    }

    public function testParams(): void
    {
        $api = new BannergressApi();

        self::assertSame(
            'https://api.bannergress.com/bnrs?param=value',
            $api->buildUri(['param' => 'value'])
        );
    }

    public function testMoreParams(): void
    {
        $api = new BannergressApi();

        self::assertSame(
            'https://api.bannergress.com/bnrs?param=value&param2=value2',
            $api->buildUri(['param' => 'value', 'param2' => 'value2'])
        );
    }

    public function testfindByProximity()
    {
        $api = new BannergressApi();

        self::assertSame(
            'https://api.bannergress.com/bnrs?param=value&param2=value2',
            $api->findByProximity(50.041186, 8.235160)
//            $api->findByProximity(12.34, -12.34)
        );

    }
}
