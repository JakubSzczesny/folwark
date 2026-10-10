<?php

declare(strict_types=1);

namespace App\Setup;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UserCreator
{
    public const int MIN_PASSWORD_LENGTH = 10;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @throws \InvalidArgumentException z czytelnym komunikatem, gdy danych nie da się użyć
     */
    public function create(string $email, string $fullName, string $password): User
    {
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new \InvalidArgumentException(\sprintf('Hasło musi mieć co najmniej %d znaków.', self::MIN_PASSWORD_LENGTH));
        }

        $user = (new User())->setEmail($email)->setFullName($fullName);
        $violations = $this->validator->validate($user);
        if (\count($violations) > 0) {
            $messages = [];
            foreach ($violations as $violation) {
                $messages[] = (string) $violation->getMessage();
            }
            throw new \InvalidArgumentException(implode(' ', $messages));
        }

        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
