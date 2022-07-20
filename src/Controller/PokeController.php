<?php

namespace App\Controller;

use App\Entity\Poke;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use phpDocumentor\Reflection\Type;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api", name="api_")
 */
class PokeController extends AbstractController
{
    #[Route('/pokes', name: 'new_poke', methods: "POST")]
    public function poke(ManagerRegistry $doctrine, Request $request):Response
    {
        $entityManager = $doctrine->getManager();
        $poke = new Poke();
        $sender = $request->request->get('sender');
        $recipient = $request->request->get('recipient');
        $poke->setSender($sender);
        $poke->setRecipient($recipient);
        $entityManager->persist($poke);
        $entityManager->flush();
        return $this->json(['success' => 'sėkmingai bakstelta']);
    }
    /**
     * @Route("/get-pokes", name="get_pokes", methods={"POST"})
     */
    public function count(ManagerRegistry $doctrine, Request $request):Response
    {
        $email = $request->request->get('email');
        $pokes = $doctrine
            ->getRepository(Poke::class)
            ->findBy(array('recipient' => $email));
        return $this->json($pokes);
    }
    /**
     * @Route("/get-user-pokes", name="get_user_pokes", methods={"POST"})
     */
    public function userPokes(ManagerRegistry $doctrine, Request $request):JsonResponse
    {
        $limit = $request->request->get('limit');
        $email = $request->request->get('email');
        $pokes = $doctrine
            ->getRepository(Poke::class)
            ->findBy(array('recipient' => $email), limit: $limit);
        return $this->json($pokes);
    }
    /**
     * @Route("/get-all-pokes", name="get_all_pokes", methods={"POST"})
     */
    public function allPokes(ManagerRegistry $doctrine, Request $request):JsonResponse
    {

        $pokes = $doctrine
            ->getRepository(Poke::class)
            ->findBy(array(), limit: $request->request->get('limit'), offset: $request->request->get('offset'));
        $lastPage = $doctrine
            ->getRepository(Poke::class)
            ->findAll();

        $data = [];
        foreach ($pokes as $poke) {
            $data[] = [
                'id' => $poke->getId(),
                'sender' => $poke->getSender(),
                'recipient' => $poke->getRecipient(),
                'date_time' => $poke->getDateTime(),
            ];
        }
        return $this->json(['data' => $data, 'page' => $lastPage]);
    }
    /**
     * @Route("/filter-pokes-by-name", name="filter-pokes-by-email", methods={"POST"})
     */
    public function userByName(ManagerRegistry $doctrine, Request $request): Response
    {
        $name = $request->request->get('name');
        $user = $doctrine
            ->getRepository(User::class)
            ->findBy(array('firstName' => $name));
        return $this->json($user);
    }
    /**
     * @Route("/filter-pokes-from", name="filter-pokes-from", methods={"POST"})
     */
    public function pokesFrom(Request $request, Connection $connection): Response
    {
        $start = $request->request->get('start');
        $end = $request->request->get('end');
        $limit = $request->request->get('limit');
        $offset = $request->request->get('offset');
        //var_dump($offset);
        $poke = $connection->fetchAllAssociative("SELECT * FROM poke where date_time >= '$start' AND date_time <= '$end' LIMIT $limit OFFSET $offset");
        $lastPage = $connection->fetchAllAssociative("SELECT * FROM poke where date_time >= '$start' AND date_time <= '$end'");
        if(!$poke){
            return $this->json(['warning' => 'pasirinktom dienom poke nėra']);
        }

        return $this->json(['data' => $poke, 'page' => $lastPage]);
    }
    /**
     * @Route("/update-poke/{email}")
     */
    public function update(Connection $connection, Request $request, string $email): JsonResponse
    {
        $newEmail = $request->request->get('newEmail');
        $pokes = $connection->fetchAllAssociative("UPDATE poke SET recipient = '$newEmail' where recipient = '$email'");

        return $this->json($pokes);
    }
    /**
     * @Route("/poke-import", methods={"POST"})
     */
    public function pokeImport(ManagerRegistry $doctrine, Request $request): JsonResponse
    {
        $fileName = $request->request->get('file');

        $entityManager = $doctrine->getManager();
        foreach(json_decode($fileName) as $pokes){
            $poke = new Poke();
            $poke->setSender($pokes->from);
            $poke->setRecipient($pokes->to);
            $poke->setDateTime(\DateTime::createFromFormat('Y-m-d', $pokes->date));
            $entityManager->persist($poke);
            $entityManager->flush();
        }
        return $this->json(['success' => 'importas pavyko']);
    }
}