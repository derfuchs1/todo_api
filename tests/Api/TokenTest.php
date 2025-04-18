<?php

namespace Api;

use App\Factory\UserFactory;
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
        ])->toArray();

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $this->assertNotEmpty($response['token']);
    }

    public function testLogout()
    {
        $token = $this->getUserToken();

        $client = static::createClient();

        $client->request('POST', '/logout', [
            'json' => [
                'refresh_token' => $token['refresh_token']
            ],
            'headers' => [
                'Authorization' => 'Bearer ' . $token['token']
            ]
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains([
            'code' => 200,
            'message' => 'The supplied refresh_token has been invalidated.'
        ]);
    }

    public function testRefreshToken()
    {
        $token = $this->getUserToken();

        $client = static::createClient();
        $response = $client->request('POST', '/token/refresh', [
            'json' => [
                'refresh_token' => $token['refresh_token']
            ]
        ])->toArray();

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        $this->assertNotEmpty($response['token']);
        $this->assertNotEquals($response['token'], $token['token']);
    }
}
