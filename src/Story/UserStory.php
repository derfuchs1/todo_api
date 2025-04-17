<?php

namespace App\Story;

use App\Factory\UserFactory;
use Zenstruck\Foundry\Story;

final class UserStory extends Story
{
    public function build(): void
    {

        $this->addState(
            'standardUser',
            UserFactory::createOne()
        );

        $this->addState(
            'adminUser',
            UserFactory::createOne([
                'roles' => ['ROLE_ADMIN']
            ])
        );
    }
}
