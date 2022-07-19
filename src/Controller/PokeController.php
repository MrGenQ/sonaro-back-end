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
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
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
    public function count(Connection $connection, Request $request):Response
    {
        $email = $request->request->get('email');
        $count = $connection->fetchAssociative("SELECT COUNT('recipient') as count FROM poke where recipient = '$email'");
        return $this->json([$count]);
    }
    /**
     * @Route("/get-user-pokes", name="get_user_pokes", methods={"POST"})
     */
    public function userPokes(Connection $connection, Request $request):JsonResponse
    {
        $limit = $request->request->get('limit');
        $email = $request->request->get('email');
        $pokes = $connection->fetchAllAssociative("SELECT * FROM poke  where recipient LIKE '$email' LIMIT $limit");
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
        $lastpage = $doctrine
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
        return $this->json(['data' => $data, 'page' => $lastpage]);
    }
    /**
     * @Route("/filter-pokes-by-email", name="filter-pokes-by-email", methods={"POST"})
     */
    public function userByEmail(ManagerRegistry $doctrine, Request $request, Connection $connection): Response
    {
        $email = $request->request->get('email');
        //$poke = $connection->fetchAllAssociative("SELECT * FROM user where first_name LIKE '$email'");
        $recipient = $doctrine
            ->getRepository(User::class)
            ->findBy(array('firstName' => $email));
        if(!$recipient){
            return $this->json(['warning' => "nerastas vartotojas arba reikia vesti tik vartotojo vardą"]);
        }
        return $this->json($recipient);
    }
    /**
     * @Route("/filter-pokes-from", name="filter-pokes-from", methods={"POST"})
     */
    public function pokesFrom(ManagerRegistry $doctrine, Request $request, Connection $connection): Response
    {
        $start = $request->request->get('start');
        $end = $request->request->get('end');
        $poke = $connection->fetchAllAssociative("SELECT * FROM poke where date_time BETWEEN '$start' AND '$end'");
        if(!$poke){
            return $this->json(['warning' => 'pasirinktom dienom poke nėra']);
        }

        return $this->json($poke);
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
        //var_dump($file_array);
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
/*
 $file_array = explode(',', $fileName);
        $serializer = serialize([1, 2]); //  [1, 2]
        //$json = json_decode(file_get_contents($fileName));
        //var_dump($json);
        $encoders = [new XmlEncoder(), new JsonEncoder()];
        $normalizers = [new ObjectNormalizer()];
        $serializer = new Serializer($normalizers, $encoders);
        $jsonContent = $serializer->serialize($file_array, 'json');
 */