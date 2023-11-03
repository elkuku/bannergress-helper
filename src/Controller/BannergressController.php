<?php

namespace App\Controller;

use App\Service\BannergressApi;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/bannergress')]
class BannergressController extends BaseController
{

    #[Route('/query-proximity', name: 'app_bannergress_queryproximity', methods: ['GET'])]
    public function queryProximity(Request $request, BannergressApi $bannergressApi,): Response
    {
        $lat = (float)$request->get('lat');
        $lon = (float)$request->get('lon');

        $uri = $bannergressApi->getProximityUri($lat, $lon);

        $client = HttpClient::create();
        $response = $client->request(
            'GET',
            $uri
        );

        return $this->render('default/_mission-list.html.twig', ['missions' => $response->toArray()]);
//        return $this->json($response->toArray());//
    }

    #[Route('/query-proximity-data', name: 'app_bannergress_queryproximity_data', methods: ['GET'])]
    public function queryProximityData(Request $request, BannergressApi $bannergressApi,): JsonResponse
    {
        $lat = (float)$request->get('lat');
        $lon = (float)$request->get('lon');

        $uri = $bannergressApi->getProximityUri($lat, $lon);

        $client = HttpClient::create();
        $response = $client->request(
            'GET',
            $uri
        );

        //return $this->render('default/_mission-list.html.twig', ['missions' => $response->toArray()]);
        return $this->json($response->toArray());
    }

    #[Route('/query-mission-data', name: 'app_bannergress_query_mission_data', methods: ['GET'])]
    public function queryMissionData(Request $request, BannergressApi $bannergressApi,): Response
    {
        $id = $request->get('id');

        $uri = $bannergressApi->getBannerUri($id);

        $client = HttpClient::create();
        $response = $client->request(
            'GET',
            $uri
        );

        return $this->render('default/_mission.html.twig', ['mission' => $response->toArray()]);
        return new Response('hey');
    }
}
