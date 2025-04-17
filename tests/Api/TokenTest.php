<?php

namespace Api;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Story\UserStory;
use App\Tests\AbstractApiTestCase;

class TokenTest extends AbstractApiTestCase
{
    public function testLogin()
    {
        $email = 'user@example.com';
        $password = 'test1234';

        UserFactory::createOne([
            'email' => $email,
            'plainPassword' => $password
        ]);

        $client = static::createClient();

        $response = $client->request('POST', '/login', [
            'json' => [
                'email' => $email,
                'password' => $password
            ]
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $this->assertNotEmpty($response->toArray()['token']);
    }

    public function testLogout()
    {
        $token = $this->getUserToken();

        $client = static::createClient();

        $client->request('POST', '/logout', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token
            ]
        ]);

        $standardUser = UserStory::get('standardUser');

        $iri = $this->findIriBy(User::class, ['id' => $standardUser->getId()]);

        $client->request('GET', $iri, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token
            ]
        ]);

        $this->assertResponseStatusCodeSame(401);
        $this->assertResponseHeaderSame('content-type', 'application/json');
        $this->assertJsonContains([
            'code' => 401,
            'message' => 'Invalid JWT Token'
        ]);
    }
}
