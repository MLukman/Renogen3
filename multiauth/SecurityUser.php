<?php

namespace MLukman\MultiAuthBundle;

use MLukman\MultiAuthBundle\Identity\MultiAuthUserCredentialInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class SecurityUser implements UserInterface
{
    /** @var MultiAuthUserCredentialInterface */
    private $user_credential;

    public function __construct(MultiAuthUserCredentialInterface $user_credential)
    {
        $this->user_credential = $user_credential;
    }

    public function eraseCredentials()
    {
        $this->user_credential->setCredentialValue(null);
    }

    public function getPassword()
    {
        return $this->user_credential->getCredentialValue();
    }

    public function getRoles()
    {
        return $this->user_credential->getUser()->getRoles();
    }

    public function getSalt()
    {
        return null;
    }

    public function getUsername(): string
    {
        return $this->user_credential->getUser()->getUsername();
    }
}