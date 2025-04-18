<?php

namespace App\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\User;
use App\Story\UserStory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class AbstractApiTestCase extends ApiTestCase
{
    use ResetDatabase, Factories;

    protected function getUserToken(): array
    {
        /**
         * @var User $standardUser
         */
        $standardUser = UserStory::get('standardUser');

        return $this->getToken($standardUser->getEmail(), $standardUser->getPlainPassword());
    }

    protected function getAdminToken(): array {
        /**
         * @var User $adminUser
         */
        $adminUser = UserStory::get('adminUser');

        return $this->getToken($adminUser->getEmail(), $adminUser->getPlainPassword());
    }

    protected function getToken(string $email, $password): array
    {
        $client = static::createClient();
        $response = $client->request('POST', '/login', [
            'json' => [
                'email' => $email,
                'password' => $password
            ]
        ]);

        $cookieJar = $client->getCookieJar();
        $refreshToken = $cookieJar->get('refresh_token')->getValue();

        return array_merge($response->toArray(), ['refresh_token' => $refreshToken]);
    }
}
