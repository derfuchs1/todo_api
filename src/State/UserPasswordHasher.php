<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

readonly class UserPasswordHasher implements ProcessorInterface
{

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof User) {
            throw new \LogicException('Expected User object');
        }

        if ($data->getPlainPassword()) {
            $hashedPassword = $this->passwordHasher->hashPassword($data, $data->getPlainPassword());
            $data->setPassword($hashedPassword);
            $data->eraseCredentials();
        }

        $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
