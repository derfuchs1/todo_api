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

    protected function getUserToken(): string
    {
        /**
         * @var User $standardUser
         */
        $standardUser = UserStory::get('standardUser');

        return $this->getToken($standardUser->getEmail(), $standardUser->getPlainPassword());
    }

    protected function getAdminToken(): string {
        /**
         * @var User $adminUser
         */
        $adminUser = UserStory::get('adminUser');

        return $this->getToken($adminUser->getEmail(), $adminUser->getPlainPassword());
    }

    protected function getToken(string $email, $password): string
    {
        $client = static::createClient();
        $response = $client->request('POST', '/login', [
            'json' => [
                'email' => $email,
                'password' => $password
            ]
        ]);

        $this->assertResponseIsSuccessful();

        return $response->toArray()['token'];
    }
}
