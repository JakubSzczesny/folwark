<?php

declare(strict_types=1);

namespace App\Setup;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UserCreator
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @return list<string> komunikaty błędów (pusta lista = konto założone)
     */
    public function create(string $email, string $fullName, string $password, UserRole $role): array
    {
        if (mb_strlen($password) < 10) {
            return ['Hasło musi mieć co najmniej 10 znaków.'];
        }

        $user = (new User())->setEmail($email)->setFullName($fullName)->setRole($role);
        $violations = $this->validator->validate($user);
        if (\count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getPropertyPath().': '.$violation->getMessage();
            }

            return $errors;
        }

        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        return [];
    }
}
