<?php

namespace App\Controller;

use App\Entity\Poke;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
/**
 * @Route("/api", name="api_")
 */
class PokeController extends AbstractController
{
    /**
     * @Route("/pokes", name="all_pokes")
     */
    /*
     POST Metodu
     Grąžina visus poke iš duomenų bazės
     */
    public function pokes(ManagerRegistry $doctrine):Response
    {
        $pokes = $doctrine
            ->getRepository(Poke::class)
            ->findAll();

        return $this->json($pokes);
    }
    /**
     * @Route("/get-pokes", name="get_pokes", methods={"POST"})
     */
    /*
     POST Metodu
     Grąžina kiek vartotojas turi poke
     */
    public function pokeCount(ManagerRegistry $doctrine, Connection $connection, Request $request):Response
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
    /*
     POST Metodu
     Grąžina pasirinkto vartojo poke iš duomenų bazės
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
    /*
     POST Metodu
     Grąžina pasirinkto puslapio poke iš duomenų bazės
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
    /*
     POST Metodu
     Grąžina vartotoją pagal pateiktą vardą
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
    /*
     POST Metodu
     Grąžina visus poke kurie atitinka nuostatytas datas (nuo iki)
     */
    public function pokesFrom(Request $request, Connection $connection): Response
    {
        $start = $request->request->get('start');
        $end = $request->request->get('end');
        $limit = $request->request->get('limit');
        $offset = $request->request->get('offset');
        $poke = $connection->fetchAllAssociative("SELECT * FROM poke where date_time >= '$start' AND date_time <= '$end' LIMIT $limit OFFSET $offset");
        $lastPage = $connection->fetchAllAssociative("SELECT * FROM poke where date_time >= '$start' AND date_time <= '$end'");
        if(!$poke){
            return $this->json(['warning' => 'Pasirinktomis dienomis poke nėra']);
        }

        return $this->json(['data' => $poke, 'page' => $lastPage]);
    }
    /**
     * @Route("/update-poke/{email}")
     */
    /*
     POST Metodu
     Atnaujina poke kai vartotojas pasikeičia savo email duomenis
     */
    public function updatePoke(Connection $connection, Request $request, string $email): JsonResponse
    {
        $newEmail = $request->request->get('newEmail');
        $pokes = $connection->fetchAllAssociative("UPDATE poke SET recipient = '$newEmail' where recipient = '$email'");

        return $this->json($pokes);
    }
    /**
     * @Route("/poke-import", methods={"POST"})
     */
    /*
     POST Metodu
     importuoja poke is json duomenų ir sukuria juos duomenų bazėje
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
        return $this->json(['success' => 'Importas pavyko']);
    }
    /**
     * @Route("/send-email", methods={"POST"})
     */
    /*
     POST Metodu
     Išsiunčia el. laišką kai kuris nors vartotojas yra baksnojamas
     */
    public function sendEmail(MailerInterface $mailer, Request $request, ManagerRegistry $doctrine): Response
    {
        $poker = $doctrine
            ->getRepository(Poke::class)
            ->findOneBy(array('sender' => $request->request->get('sender')));
        $user = $doctrine
            ->getRepository(User::class)
            ->findOneBy(array('email' => $poker->getSender()));
        $email = (new Email())
            ->from($request->request->get('sender'))
            ->to($request->request->get('recipient'))

            ->subject('Time for Symfony Mailer!')
            ->text('Sending emails is fun again!')
            ->html("
                    <h4>Sveiki,</h4>
                    <p>{$user->getFirstName()} {$user->getLastName()} pokina tave</p>
                   ");

        $mailer->send($email);

        return $this->json(['success' => 'Laiškas išsiųstas ' .$request->request->get('recipient') .' el. paštu']);
    }
    /**
     * @Route("/poke-user", name="new_poke", methods={"POST"})
     */
    /*
     POST Metodu
     Sukuria naują poke, neleidžia vartotojui bakstelt savęs (grąžina error)
     */
    public function newPoke(ManagerRegistry $doctrine, Request $request):Response
    {
        $sender = $request->request->get('sender');
        $recipient = $request->request->get('recipient');
        if($sender === $recipient){
            return $this->json(['error' => 'Negalima bakstelti savęs']);
        }
        $entityManager = $doctrine->getManager();
        $poke = new Poke();
        $poke->setSender($sender);
        $poke->setRecipient($recipient);
        $entityManager->persist($poke);
        $entityManager->flush();

        return $this->json(['success' => 'Sėkmingai bakstelta', 'data' => $poke->getId()]);
    }
}
