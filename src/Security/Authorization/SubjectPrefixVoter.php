<?php

namespace App\Security\Authorization;

use App\Security\Authorization\SubjectPrefixVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * SubjectPrefixVoter is Symfony Voter subclass
 */
class SubjectPrefixVoter extends Voter
{
    /**
     * Subject prefixes
     * @var string[]
     */
    protected $subjectPrefixes = array();

    /**
     * Get singleton instance
     * @staticvar type $instance
     * @return SubjectPrefixVoter
     */
    static public function instance()
    {
        static $instance = null;
        if (!$instance) {
            $instance = new static();
        }
        return $instance;
    }

    /**
     * Add a subject prefix and its allowed role(s)
     * @param string $subjectPrefix
     * @param string|array $roles
     * @return SubjectPrefixVoter
     */
    public function addSubjectPrefix($subjectPrefix, $roles)
    {
        if (!is_array($roles)) {
            $roles = array($roles);
        }
        if (is_array($subjectPrefix)) {
            foreach ($subjectPrefix as $oneSubjectPrefix) {
                $this->addSubjectPrefix($oneSubjectPrefix, $roles);
            }
        } else {
            if (isset($this->subjectPrefixes[$subjectPrefix])) {
                $this->subjectPrefixes[$subjectPrefix] = array_values(array_unique(array_merge(
                            $this->subjectPrefixes[$subjectPrefix], $roles)));
            } else {
                $this->subjectPrefixes[$subjectPrefix] = $roles;
            }
        }
        return $this;
    }

    /**
     * Determines if the attribute and subject are supported by this voter.
     *
     * @param string $attribute An attribute
     * @param mixed  $subject   The subject to secure, e.g. an object the user wants to access or any other PHP type
     *
     * @return bool True if the attribute and subject are supported, false otherwise
     */
    protected function supports($attribute, $subject)
    {
        if (is_array($attribute)) {
            foreach ($attribute as $attr) {
                if ($this->supports($attr, $subject)) {
                    return true;
                }
            }
        } else {
            return (substr($attribute, 0, 6) == 'prefix');
        }
    }

    /**
     * Perform a single access check operation on a given attribute, subject and token.
     *
     * @param string         $attribute
     * @param mixed          $subject
     * @param TokenInterface $token
     *
     * @return bool
     */
    protected function voteOnAttribute($attribute, $subject,
                                       TokenInterface $token)
    {
        $granted = true;
        $uroles = array();
        foreach ($token->getRoles() as $role) {
            $uroles[] = $role->getRole();
        }
        foreach ($this->subjectPrefixes as $prefix => $proles) {
            if (substr($subject, 0, strlen($prefix)) != $prefix) {
                continue;
            }
            $granted = false;
            if (count(array_intersect($uroles, $proles)) > 0) {
                return true;
            }
        }
        return $granted;
    }

    public function getRolesForSubjectPrefix($prefix)
    {
        return (isset($this->subjectPrefixes[$prefix]) ?
            $this->subjectPrefixes[$prefix] : []);
    }
}