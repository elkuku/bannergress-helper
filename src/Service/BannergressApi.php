<?php

namespace App\Service;

class BannergressApi
{
    private string $apiBase = 'https://api.bannergress.com';

    public function getProximityUri(float $latitude, float $longitude, int $limit = 10): string
    {
        return $this->buildUri([
            'orderBy' => 'proximityStartPoint',
            'online' => 'true',
            'proximityLatitude' => $latitude,
            'proximityLongitude' => $longitude,
            'limit' => $limit,
        ]);
    }

    public function buildUri(array $params = []): string
    {
        return $this->apiBase . '/bnrs' . ($params ? '?' . http_build_query($params) : '');
    }

    public function getBannerUri(string $id):string
    {
        return $this->apiBase . '/bnrs/' . $id;
    }
}
