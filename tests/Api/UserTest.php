<?php

namespace App\Tests\Api;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Story\UserStory;
use App\Tests\AbstractApiTestCase;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


class UserTest extends AbstractApiTestCase
{
    public function testCreateUser()
    {
        $password = 'test1234';

        $client = self::createClient();

        $response = $client->request('POST', '/users', [
            'json' => [
                'email' => 'user@example.com',
                'password' => $password
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json'
            ]
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $this->assertJsonContains([
            '@context' => '/contexts/User',
            '@type' => 'User',
            'email' => 'user@example.com',
        ]);

        $responseArr = $response->toArray();

        $this->assertIsInt($responseArr['id']);

        $this->assertMatchesRegularExpression('~^/users/\d+$~', $responseArr['@id']);
        $this->assertMatchesResourceItemJsonSchema(User::class);
    }

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

    public function testDuplicateUser() {
        $email = 'user@example.com';

        UserFactory::createOne([
            'email' => $email,
            'plainPassword' => 'test1234'
        ]);

        $client = static::createClient();
        $client->request('POST', '/users', [
            'json' => [
                'email' => $email,
                'password' => 'test12345'
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json'
            ]
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');

        $this->assertJsonContains([
            '@context' => '/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            'title' => 'An error occurred',
            'description' => 'email: This value is already used.',
            'type' => '/validation_errors/' . UniqueEntity::NOT_UNIQUE_ERROR,
        ]);
    }

    public function testGetCollection()
    {
        $token = $this->getUserToken();

        $client = static::createClient();
        $client->request('GET', '/users', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token
            ]
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testGetCollectionAdmin() {
        $token = $this->getAdminToken();

        $client = static::createClient();
        $response = $client->request('GET', '/users', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token
            ]
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/contexts/User',
            '@type' => 'Collection',
            'totalItems' => 2,
        ]);
        $this->assertCount(2, $response->toArray()['member']);
        $this->assertMatchesResourceCollectionJsonSchema(User::class);
    }

    public function testDeleteUser() {
        $user = UserFactory::createOne();
        $userId = $user->getId();

        $token = $this->getToken($user->getEmail(), $user->getPlainPassword());

        $iri = $this->findIriBy(User::class, ['id' => $userId]);

        $client = static::createClient();
        $client->request('DELETE', $iri, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token
            ]
        ]);

        $this->assertResponseStatusCodeSame(204);

        $container = self::getContainer();
        $doctrine = $container->get('doctrine');

        $repository = $doctrine->getRepository(User::class);
        $this->assertNull($repository->find($userId));
    }

    public function testDeleteForeignUser()
    {
        $token = $this->getUserToken();
        $adminUser = UserStory::get('adminUser');

        $iri = $this->findIriBy(User::class, ['id' => $adminUser->getId()]);

        $client = static::createClient();
        $client->request('DELETE', $iri, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token
            ]
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/contexts/Error',
            '@id' => '/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Access Denied.',
        ]);
    }

    public function testPutUser()
    {
        $token = $this->getUserToken();

        $standardUser = UserStory::get('standardUser');
        $userId = $standardUser->getId();

        $iri = $this->findIriBy(User::class, ['id' => $userId]);

        $client = static::createClient();
        $response = $client->request('PUT', $iri, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/ld+json'
            ],
            'json' => [
                'email' => 'user@example.com',
                'password' => 'test1234'
            ]
        ])->toArray();

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/contexts/User',
            '@type' => 'User',
            'email' => 'user@example.com',
        ]);


        $this->assertEquals($userId, $response['id']);
        $this->assertMatchesRegularExpression('~^/users/\d+$~', $response['@id']);
        $this->assertMatchesResourceItemJsonSchema(User::class);
    }

    public function testPutForeignUser() {
        $token = $this->getUserToken();

        $adminUser = UserStory::get('adminUser');
        $iri = $this->findIriBy(User::class, ['id' => $adminUser->getId()]);

        $client = static::createClient();
        $response = $client->request('PUT', $iri, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/ld+json'
            ],
            'json' => [
                'email' => 'user@example.com',
                'password' => 'test1234'
            ]
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/contexts/Error',
            '@id' => '/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Access Denied.',
        ]);
    }

    public function testPatch()
    {
        $token = $this->getUserToken();

        $standardUser = UserStory::get('standardUser');
        $userId = $standardUser->getId();

        $iri = $this->findIriBy(User::class, ['id' => $userId]);

        $client = static::createClient();
        $response = $client->request('PATCH', $iri, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/merge-patch+json'
            ],
            'json' => [
                'email' => 'user@example.com',
                'password' => 'test1234'
            ]
        ])->toArray();

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/contexts/User',
            '@type' => 'User',
            'email' => 'user@example.com',
        ]);


        $this->assertEquals($userId, $response['id']);
        $this->assertMatchesRegularExpression('~^/users/\d+$~', $response['@id']);
        $this->assertMatchesResourceItemJsonSchema(User::class);
    }

    public function testPatchForeignUser()
    {
        $token = $this->getUserToken();

        $adminUser = UserStory::get('adminUser');
        $iri = $this->findIriBy(User::class, ['id' => $adminUser->getId()]);

        $client = static::createClient();
        $response = $client->request('PATCH', $iri, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/merge-patch+json'
            ],
            'json' => [
                'email' => 'user@example.com',
                'password' => 'test1234'
            ]
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/contexts/Error',
            '@id' => '/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Access Denied.',
        ]);
    }
}
