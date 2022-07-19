<?php

namespace App\Controller;

use App\Entity\Poke;
use App\Repository\UserRepository;
use phpDocumentor\Reflection\Type;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\DBAL\Connection;
use Knp\Component\Pager\PaginatorInterface;
/**
 * @Route("/api", name="api_")
 */
class UserController extends AbstractController
{
    #[Route('/user', name: 'app_user')]
    public function index(ManagerRegistry $doctrine): Response
    {
        $users = $doctrine
            ->getRepository(User::class)
            ->findAll();

        $data = [];

        foreach ($users as $user) {
            $data[] = [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
            ];
        }


        return $this->json($data);
    }

    #[Route('/register', name: 'new_user', methods: "POST")]
    public function new(ManagerRegistry $doctrine, Request $request): JsonResponse
    {
        $entityManager = $doctrine->getManager();
        /*Custom validacija registracijos pateiktiems duomenims validuoti,
          Svarbu, el paštas turi būti unikalus,
          Visi laukeliai būtini */
        $validateEmail = $doctrine
            ->getRepository(User::class)
            ->findOneBy(array('email' => $request->request->get('email')));

        $validate = (object) array(
            'none' => true
        );
        if($request->request->get('firstName') === ''){
            $validate->firstName = 'Vardas būtinas';
            $validate->none = false;
        }
        if($request->request->get('lastName') === ''){
            $validate->lastName = 'Pavardė būtina';
            $validate->none = false;
        }
        if($request->request->get('email') === ''){
            $validate->email = 'El. paštas būtinas';
            $validate->none = false;
        }
        if($validateEmail){
            $validate->email_exists = 'Šis el. paštas jau yra užimtas';
            $validate->none = false;
        }
        if($request->request->get('password') === ''){
            $validate->password = 'Slaptažodis būtinas';
            $validate->none = false;
        }
        if($request->request->get('password_confirm') === ''){
            $validate->password_confirm = 'Slaptažodis būtinas';
            $validate->none = false;
        }
        if($request->request->get('password') !== $request->request->get('password_confirm')){
            $validate->password_not_equal = 'Slaptažodiai nesutampa';
            $validate->none = false;
        }
        if($validate->none !== true){
            return $this->json(['errors' => $validate]);
        }
        $user = new User();
        $user->setEmail($request->request->get('email'));
        $user->setPassword($request->request->get('password'));
        $user->setFirstName($request->request->get('firstName'));
        $user->setLastName($request->request->get('lastName'));

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json(['success' => 'Vartotojas ' .$user->getEmail() .' sėkmingai užregistruotas', 'errors' => '']);
    }
    /**
     * @Route("/login", name="login", methods={"POST"})
     */
    public function login(Connection $connection, Request $request, ManagerRegistry $doctrine): Response
    {
        $email = $request->request->get('email');
        $password = $request->request->get('password');
        $user = $connection->fetchAssociative("SELECT * FROM user where email = '$email' AND password = '$password'");
        if (!$user) {

            return $this->json(['error' => 'Blogi prisijungimo duomenys','email' => 'El. paštas neegzistuoja', 'password' => 'Neteisingas slaptažodis', 'user' => $user]);
        }
        $data = [
            'id' => $user['id'],
            'email' => $user['email'],
            'firstName' => $user['first_name'],
            'lastName' => $user['last_name'],
        ];
        return $this->json(['success' => 'Sėkmingai prisijungta', 'data' => $data]);
    }
    /**
     * @Route("/user-by-email", name="user-by-email", methods={"POST"})
     */
    public function userByEmail(ManagerRegistry $doctrine, Request $request): Response
    {
        $email = $request->request->get('email');

        $user = $doctrine
            ->getRepository(User::class)
            ->findOneBy(array('email' => $email));
        return $this->json(['firstName' => $user->getFirstName(), 'lastName' => $user->getLastName()]);
    }
    /**
     * @Route("/user-search", name="user-search", methods={"POST"})
     */
    public function userSearch(ManagerRegistry $doctrine, Request $request): Response
    {
        $name = $request->request->get('name');
        $users = $doctrine
            ->getRepository(User::class)
            ->findBy(array('firstName' => $name), limit: $request->request->get('limit'), offset: $request->request->get('offset'));
        $lastpage = $doctrine
            ->getRepository(User::class)
            ->findBy(array('firstName' => $name));
        $data = [];
        foreach ($users as $user) {
            $data[] = [
                'id' => $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail(),
            ];
        }
        return $this->json(['data' => $data, 'page' => $lastpage]);
    }
    /**
     * @Route("/update-user/{id}")
     */
    public function update(ManagerRegistry $doctrine, Request $request, int $id): JsonResponse
    {
        $entityManager = $doctrine->getManager();
        /*Custom validacija vartotojo redagavimo pateiktiems duomenims validuoti,
          Svarbu, el paštas turi būti unikalus, bet gali būti toks pat kaip prieš tai,
          Visi laukeliai gali būti nepakeisti, bet visus laukelius būtina įvesti */
        $validateEmailFree = $doctrine
            ->getRepository(User::class)
            ->findOneBy(array('email' => $request->request->get('email')));
        $validateYourEmail = $doctrine
            ->getRepository(User::class)
            ->findOneBy(array('email' => $request->request->get('oldEmail')));
        //return $this->json($validateYourEmail->email);

        $validate = (object) array(
            'none' => true
        );
        if($request->request->get('firstName') === ''){
            $validate->firstName = 'Vardas būtinas';
            $validate->none = false;
        }
        if($request->request->get('lastName') === ''){
            $validate->lastName = 'Pavardė būtina';
            $validate->none = false;
        }
        if($request->request->get('email') === ''){
            $validate->email = 'El. paštas būtinas';
            $validate->none = false;
        }

        if($validateEmailFree && !($validateEmailFree === $validateYourEmail)){
            $validate->email_exists = 'Šis el. paštas jau yra užimtas';
            $validate->none = false;
        }
        if($request->request->get('password') === ''){
            $validate->password = 'Slaptažodis būtinas';
            $validate->none = false;
        }
        if($request->request->get('password_confirm') === ''){
            $validate->password_confirm = 'Slaptažodis būtinas';
            $validate->none = false;
        }
        if($request->request->get('password') !== $request->request->get('password_confirm')){
            $validate->password_not_equal = 'Slaptažodiai nesutampa';
            $validate->none = false;
        }
        if($validate->none !== true){
            return $this->json(['errors' => $validate]);
        }
        $user = $entityManager->getRepository(User::class)->find($id);
        $user->setFirstName($request->request->get('firstName'));
        $user->setLastName($request->request->get('lastName'));
        $user->setEmail($request->request->get('email'));
        $user->setPassword($request->request->get('password'));
        $entityManager->flush();

        return $this->json(['success' => 'Vartotojas ' .$user->getEmail() .' sėkmingai atnaujintas', 'data' => [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
        ], 'errors' => '']);
    }
}
