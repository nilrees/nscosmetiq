<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $accounts = [
            [
                'email' => 'admin.fixture@example.test',
                'name' => 'Test Administrator',
                'role' => 'ROLE_ADMIN',
            ],
            [
                'email' => 'staff.fixture@example.test',
                'name' => 'Test Staff',
                'role' => 'ROLE_STAFF',
            ],
        ];

        // Set this in your terminal before loading fixtures.
        $password = $_ENV['FIXTURE_USER_PASSWORD']
            ?? $_SERVER['FIXTURE_USER_PASSWORD']
            ?? getenv('FIXTURE_USER_PASSWORD')
            ?: null;

        if (!is_string($password) || strlen($password) < 12) {
            throw new \RuntimeException(
                'Set FIXTURE_USER_PASSWORD to a password of at least 12 characters.'
            );
        }

        foreach ($accounts as $account) {
            $user = new User();

            $user->setEmail($account['email']);
            $user->setFullName($account['name']);
            $user->setRoles([$account['role']]);
            $user->setIsActive(true);

            $user->setPassword(
                $this->passwordHasher->hashPassword(
                    $user,
                    $password
                )
            );

            $manager->persist($user);
        }

        $manager->flush();
    }
}
