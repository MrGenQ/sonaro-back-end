<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\DBAL\Connection;
use Symfony\Component\String\ByteString;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\RequestStack;
/**
 * @Route("/api", name="api_")
 */
class UserController extends AbstractController
{
    /**
     * @Route("/user", name="app_user", methods={"POST"})
     */
    /*
     POST Metodu
     Grąžina puslapiuotus vartotojus iš duomenų bazės
     */
    public function getUsers(ManagerRegistry $doctrine, Request $request): Response
    {
        $users = $doctrine
            ->getRepository(User::class)
            ->findBy(array(), limit: $request->request->get('limit'), offset: $request->request->get('offset'));
        $lastPage = $doctrine
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


        return $this->json(['data' => $data, 'page' => $lastPage]);
    }
    /**
     * @Route("/register", name="register", methods={"POST"})
     */
    /*
     POST Metodu
     Užregistruoja naują vartotoją jeigu nerandama validacijos klaidų
     */
    public function register(ManagerRegistry $doctrine, Request $request): JsonResponse
    {
        $entityManager = $doctrine->getManager();
        /*Custom validacija registracijos pateiktiems duomenims validuoti,
          Svarbu, el paštas turi būti unikalus,
          Visi laukeliai būtini */
        $validateEmail = $doctrine
            ->getRepository(User::class)
            ->findOneBy(array('email' => $request->request->get('email')));
        $validateUsername = $doctrine
            ->getRepository(User::class)
            ->findOneBy(array('username' => $request->request->get('username')));

        $validate = (object) array(
            'none' => true
        );
        if($validateUsername){
            $validate->username = 'Šis vartotojo vardas jau yra užimtas';
            $validate->none = false;
        }
        if($request->request->get('username') === ''){
            $validate->firstName = 'Vartotojo vardas būtinas';
            $validate->none = false;
        }
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
        $user->setUsername($request->request->get('username'));

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json(['success' => 'Vartotojas ' .$user->getUsername() .' sėkmingai užregistruotas', 'errors' => '']);
    }
    /**
     * @Route("/login", name="login", methods={"POST"})
     */
    /*
     POST Metodu
     Prisijungimo funkcija, bei naujos php sesijos kurimas ir user informacijos patalpinimas
     */
    public function login(Connection $connection, Request $request, ManagerRegistry $doctrine): Response
    {
        $session = new Session();
        $session->start();
        $username = $request->request->get('username');
        $password = $request->request->get('password');

        $user = $connection->fetchAssociative("SELECT * FROM user where username = '$username' AND password = '$password'");
        $session->set('user', $user);
        if (!$user) {

            return $this->json(['error' => 'Blogi prisijungimo duomenys','username' => 'Vartotojas neegzistuoja', 'password' => 'Neteisingas slaptažodis', 'user' => $session->get('user')]);
        }
        $data = [
            'id' => $user['id'],
            'email' => $user['email'],
            'firstName' => $user['first_name'],
            'lastName' => $user['last_name'],
            'username' => $user['username'],
        ];
        return $this->json(['success' => 'Sėkmingai prisijungta', 'data' => $data]);
    }
    /**
     * @Route("/logout", methods={"POST"})
     */
    /*
     POST Metodu
     Atsijungimo funkcija, bei iš php sesijos ištrina user informaciją
     */
    public function logout(RequestStack $requestStack, Request $request): JsonResponse
    {
        $session = $requestStack->getSession();
        $session->clear();
        return $this->json(['success' => 'Sėkmingai atsijungta', 'data' => ""]);
    }
    /**
     * @Route("/user-by-email", name="user-by-email", methods={"POST"})
     */
    /*
     POST Metodu
     Atsiustą el. pašto adresą paverčia vardu ir pavarde
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
    /*
     POST Metodu
     Grąžina vartotoją kuris atitinka pateiktą vardą iš frontend
     */
    public function userSearch(ManagerRegistry $doctrine, Request $request): Response
    {
        $name = $request->request->get('name');
        $users = $doctrine
            ->getRepository(User::class)
            ->findBy(array('firstName' => $name), limit: $request->request->get('limit'), offset: $request->request->get('offset'));
        /* variable $lastPage naudojama kad butu galima žinoti kelintas puslapis paginate bus paskutinis */
        $lastPage = $doctrine
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
        return $this->json(['data' => $data, 'page' => $lastPage]);
    }
    /**
     * @Route("/update-user/{id}")
     */
    /*
     POST Metodu
     Atnaujina vartotojo duomenis pagal suvestus duomenis fronend
     Validuoja gautus duomenis, jeigu validacijos nepraeina grąžina error
     */
    public function updateUser(ManagerRegistry $doctrine, Request $request, int $id): JsonResponse
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
            'username' => $user->getUsername(),
        ], 'errors' => '']);
    }
    /**
     * @Route("/user-import", methods={"POST"})
     */
    /*
     POST Metodu
     Importuoja vartotojus iš CSV duomenų gautų iš frontend
     Validuoja ar pateikti email neegzistuoja duomenų bazėje
     */
    public function userImport(ManagerRegistry $doctrine, Request $request, RequestStack $requestStack): JsonResponse
    {
        $responseObj = json_decode($request->getContent(), true);
        $entityManager = $doctrine->getManager();
        /*
        Custom validacija patikrinti ar csv faile yra tokių pačių el paštų kaip duomenų bazėje
        */
        foreach($responseObj as $userInfo){
            $validate = $doctrine
                ->getRepository(User::class)
                ->findOneBy(array('email' => $userInfo['email']));
            if($validate){
                return $this->json(['errors' => 'Importas negalimas, El. paštai turi būti unikalus']);
            }
        }
        foreach($responseObj as $userInfo){
            $user = new User();
            $user->setFirstName($userInfo['first_name']);
            $user->setLastName($userInfo['last_name']);
            $user->setEmail($userInfo['email']);
            $user->setPassword(ByteString::fromRandom(10)->toString());
            $user->setUsername($userInfo['first_name'] . rand(100, 999));
            $entityManager->persist($user);
            $entityManager->flush();

        }
        return $this->json(['success' => 'Vartotojų importas sėkmingas']);
    }

}