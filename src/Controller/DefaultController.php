<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/', name: 'default', methods: ['GET'])]
class DefaultController extends BaseController
{
    public function __invoke(): Response
    {
        $proxyLat = 33.7243396617476;
        $proxyLon = 2.8125;
        $zoom = 1;

        return $this->render(
            'default/index.html.twig',
            [
                'proxyLat' => $proxyLat,
                'proxyLon' => $proxyLon,
                'zoom' => $zoom,
            ]
        );
    }
}
