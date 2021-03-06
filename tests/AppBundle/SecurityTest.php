<?php

namespace tests\AppBundle;

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Description of SecurityTest
 *
 * @author Alsciende <alsciende@icloud.com>
 */
class SecurityTest extends WebTestCase
{
    protected function getAuthenticatedClient (string $username = 'user', string $password = 'user'): Client
    {
        return static::createClient(array(), array(
            'PHP_AUTH_USER' => $username,
            'PHP_AUTH_PW' => $password,
        ));
    }

    protected function getAnonymousClient (): Client
    {
        return static::createClient();
    }

    public function testHomepage ()
    {
        $client = $this->getAnonymousClient();
        $client->request('GET', "/");
        $this->assertEquals(
            Response::HTTP_OK,
            $client->getResponse()->getStatusCode()
        );
    }

    public function testGetProfileDenied()
    {
        $client = $this->getAnonymousClient();
        $client->request('GET', "/profile/");
        $this->assertEquals(
            Response::HTTP_FOUND,
            $client->getResponse()->getStatusCode()
        );
        $this->assertEquals(
            '/login',
            $client->getResponse()->headers->get('location')
        );
    }

    public function testGetProfile()
    {
        $client = $this->getAuthenticatedClient();
        $client->request('GET', '/profile/');
        $this->assertEquals(
            Response::HTTP_OK,
            $client->getResponse()->getStatusCode()
        );
    }

    public function testGetChangePassword()
    {
        $client = $this->getAuthenticatedClient();
        $client->request('GET', '/profile/change-password');
        $this->assertEquals(
            Response::HTTP_OK,
            $client->getResponse()->getStatusCode()
        );
    }

    public function testGetRegister()
    {
        $client = $this->getAnonymousClient();
        $client->request('GET', '/register/');
        $this->assertEquals(
            Response::HTTP_OK,
            $client->getResponse()->getStatusCode()
        );
    }

    public function testLogout()
    {
        $client = $this->getAuthenticatedClient();
        $client->request('GET', '/logout');
        $this->assertEquals(
            Response::HTTP_FOUND,
            $client->getResponse()->getStatusCode()
        );
        $this->assertNotNull(
            $client->getResponse()->headers->get('location')
        );
    }

    public function testBasicOauth()
    {
        $client = $this->getAuthenticatedClient();
        $crawler = $client->request(
            'GET',
            '/oauth/v2/auth',
            [
                'client_id' => '1_test',
                'response_type' => 'code',
                'redirect_uri' => 'http://httpbin.org/get',
            ]
        );
        $this->assertEquals(
            Response::HTTP_OK,
            $client->getResponse()->getStatusCode()
        );

        $form = $crawler->selectButton('accepted')->form();
        $client->submit($form);

        $this->assertEquals(
            Response::HTTP_FOUND,
            $client->getResponse()->getStatusCode()
        );
        $this->assertEquals(
            0,
            strpos($client->getResponse()->headers->get('location'), 'http://httpbin.org/get')
        );
    }

    public function testGetUsersDenied()
    {
        $client = $this->getAuthenticatedClient();
        $client->request('GET', '/users');
        $this->assertEquals(
            Response::HTTP_FORBIDDEN,
            $client->getResponse()->getStatusCode()
        );
    }

    public function testGetUsers()
    {
        $client = $this->getAuthenticatedClient('admin', 'admin');
        $client->request('GET', '/users');
        $this->assertEquals(
            Response::HTTP_OK,
            $client->getResponse()->getStatusCode()
        );
        $users = json_decode($client->getResponse()->getContent(), FALSE);
        $this->assertGreaterThan(
            0,
            count($users)
        );
        $return = [];
        foreach($users as $user) {
            $return[$user->username] = $user;
        }
        return $return;
    }

    /**
     * @depends testGetUsers
     */
    public function testGetUser($users)
    {
        $client = $this->getAuthenticatedClient($users['admin']->username, $users['admin']->username);
        $client->request('GET', '/users/' . $users['user']->id);
        $this->assertEquals(
            Response::HTTP_OK,
            $client->getResponse()->getStatusCode()
        );
        $data = json_decode($client->getResponse()->getContent(), FALSE);
        $this->assertEquals(
            $users['user']->id,
            $data->id
        );
    }

    /**
     * @depends testGetUsers
     */
    public function testGetUserDenied($users)
    {
        $client = $this->getAuthenticatedClient($users['user']->username, $users['user']->username);
        $client->request('GET', '/users/' . $users['user']->id);
        $this->assertEquals(
            Response::HTTP_FORBIDDEN,
            $client->getResponse()->getStatusCode()
        );
    }

    /**
     * @depends testGetUsers
     */
    public function testGetUserMe($users)
    {
        $client = $this->getAuthenticatedClient($users['user']->username, $users['user']->username);
        $client->request('GET', '/users/me');
        $this->assertEquals(
            Response::HTTP_OK,
            $client->getResponse()->getStatusCode()
        );
        $data = json_decode($client->getResponse()->getContent(), FALSE);
        $this->assertEquals(
            $users['user']->id,
            $data->id
        );
    }
}