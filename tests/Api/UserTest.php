<?php

namespace App\Tests\Api;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Story\UserStory;
use App\Tests\AbstractApiTestCase;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;


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

    public function testPasswordLengthValidation()
    {
        $client = static::createClient();
        $response = $client->request('POST', '/users', [
            'json' => [
                'email' => 'user@example.com',
                'password' => '123'
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json'
            ]
        ])->toArray(false);

        $this->assertResponseStatusCodeSame(422);
        $this->assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');

        $violation = $response['violations'][0];
        $this->assertEquals('password', $violation['propertyPath']);
        $this->assertEquals(Length::TOO_SHORT_ERROR, $violation['code']);
    }

    public function testEmailValidation()
    {
        $client = static::createClient();
        $response = $client->request('POST', '/users', [
            'json' => [
                'email' => 'user',
                'password' => 'test1234'
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json'
            ]
        ])->toArray(false);

        $this->assertResponseStatusCodeSame(422);
        $this->assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');

        $violation = $response['violations'][0];
        $this->assertEquals('email', $violation['propertyPath']);
        $this->assertEquals(Email::INVALID_FORMAT_ERROR, $violation['code']);

        $client = static::createClient();
        $response = $client->request('POST', '/users', [
            'json' => [
                'email' => '',
                'password' => 'test'
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json'
            ]
        ])->toArray(false);

        $violation = $response['violations'][0];
        $this->assertEquals('email', $violation['propertyPath']);
        $this->assertEquals(NotBlank::IS_BLANK_ERROR, $violation['code']);
    }

    public function testGetCollection()
    {
        $token = $this->getUserToken()['token'];

        $client = static::createClient();
        $client->request('GET', '/users', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token
            ]
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testGetCollectionAdmin() {
        $token = $this->getAdminToken()['token'];

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

        $token = $this->getToken($user->getEmail(), $user->getPlainPassword())['token'];

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
        $token = $this->getUserToken()['token'];
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
        $token = $this->getUserToken()['token'];

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
        $token = $this->getUserToken()['token'];

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
        $token = $this->getUserToken()['token'];

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
        $token = $this->getUserToken()['token'];

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
