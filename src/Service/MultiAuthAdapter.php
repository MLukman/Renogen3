<?php

namespace App\Service;

use App\Entity\AuthDriver;
use App\Entity\User;
use App\Entity\UserCredential;
use Doctrine\Common\Collections\ArrayCollection;
use MLukman\MultiAuthBundle\DriverInstance;
use MLukman\MultiAuthBundle\Identity\MultiAuthUserCredentialInterface;
use MLukman\MultiAuthBundle\Identity\MultiAuthUserInterface;
use MLukman\MultiAuthBundle\MultiAuthAdapterInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Traversable;

class MultiAuthAdapter implements MultiAuthAdapterInterface
{
    private $ds;
    private $session;

    public function __construct(DataStore $ds, SessionInterface $session)
    {
        $this->ds = $ds;
        $this->session = $session;
    }

    public function loadDriverInstance(string $driver_id): ?DriverInstance
    {
        return $this->ds->queryOne(AuthDriver::class, $driver_id);
    }

    public function loadAllDriverInstances(): Traversable
    {
        return new ArrayCollection($this->ds->queryMany(AuthDriver::class));
    }

    public function loadAllUserCredentialsForDriverId(string $driver_id): Traversable
    {
        return new ArrayCollection($this->ds->queryMany(UserCredential::class, array(
                'driver_id' => $driver_id)));
    }

    public function saveUser(MultiAuthUserInterface $user): void
    {
        $this->ds->commit($user);
    }

    public function loadUserByUsername(string $username): ?MultiAuthUserInterface
    {
        return $this->ds->queryOne(User::class, ['username' => $username]);
    }

    public function logUserSuccessfulLogin(MultiAuthUserCredentialInterface $user_credential)
    {
        $user = $this->ds->queryOne(User::class, $user_credential->getUser()->getUsername());
        if ($user->last_login) {
            $welcome = sprintf('Welcome back, %s. Your last login was on %s.', $user->getName(), $user->last_login->format('d/m/Y h:i A'));
        } else {
            $welcome = sprintf('Welcome to Renogen, %s.', $user->getName());
        }
        $this->session->getFlashBag()->add('persistent', $welcome);
        $user->last_login = new \DateTime();
        $this->saveUser($user);
    }

    public function isUsernameBlocked(string $username): bool
    {
        return !empty($this->ds->queryOne(User::class, ['username' => $username])->blocked);
    }

    public function getSecurityUser(MultiAuthUserInterface $user): ?UserInterface
    {
        return $user;
    }

    public function saveUserCredential(MultiAuthUserCredentialInterface $user_credential): void
    {
        $this->ds->commit($user_credential);
    }
}