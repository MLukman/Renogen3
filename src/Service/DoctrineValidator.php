<?php

namespace App\Service;

use App\Validation\ValidationException;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineValidator
{
    /**
     * @var EntityManagerInterface
     */
    protected $em = null;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function validate(&$entity, array $validation_rules,
                             array $initErrors = array())
    {
        $errors = $initErrors;
        foreach ($validation_rules as $field => $rules) {
            if ($rules instanceof \App\Validation\Rules) {
                $rules = $rules->getAll();
            }
            $value = $entity->$field;
            $errors[$field] = (isset($errors[$field]) ? $errors[$field] : []) + static::validateValue($value, $rules);
            $entity->$field = $value;

            // validation: unique
            if (!empty($entity->$field) && isset($rules['unique']) && $rules['unique']) {
                $criteria = Criteria::create()->where(new Comparison($field, '=', $entity->$field));
                if (is_string($rules['unique'])) {
                    // require uniqueness among all records with same value of a particular column
                    $rules['unique'] = array($rules['unique']);
                }
                $unique_messages = array();
                $skip = false;
                if (is_array($rules['unique'])) {
                    // require uniqueness among all records with same value of particular list of columns
                    foreach ($rules['unique'] as $among_key => $among_value) {
                        if (is_int($among_key)) {
                            $criteria = $criteria->andWhere(new Comparison($among_value, '=', $entity->$among_value));
                            $unique_messages[] = $among_value;
                        } elseif ($entity->$among_key == $among_value) {
                            $criteria = $criteria->andWhere(new Comparison($among_key, '=', $among_value));
                            $unique_messages[] = "($among_key = $among_value)";
                        } else {
                            $skip = true;
                        }
                    }
                }
                if (!$skip) {
                    $list = $this->em->getRepository(get_class($entity))->matching($criteria);
                    foreach ($list as $item) {
                        if ($item != $entity) {
                            $error = 'Value must be unique';
                            if (!empty($unique_messages)) {
                                $error .= ' for each '.implode(' + ', $unique_messages);
                            }
                            $errors[$field][] = $error;
                            break;
                        }
                    }
                }
            }

            // validation: callback
            if (!empty($entity->$field) && isset($rules['callbacks']) && is_array($rules['callbacks'])) {
                foreach ($rules['callbacks'] as $callback) {
                    try {
                        $callback($entity);
                    } catch (\RuntimeException $ex) {
                        $errors[$field][] = $ex->getMessage();
                    }
                }
            }
        }

        // for each field remove $errors if no error
        foreach ($validation_rules as $field => $rules) {
            if (empty($errors[$field])) {
                unset($errors[$field]);
            }
        }
        return $errors;
    }

    static public function validateValue(&$value, array $rules)
    {
        $errors = array();

        if (isset($rules['trim']) && $rules['trim'] && is_string($value)) {
            $value = trim($value);
        }

        // pre-validation: truncate string to given length
        if (isset($rules['truncate']) && $rules['truncate'] > 0 && strlen($value)
            > $rules['truncate']) {
            $value = substr($value, 0, $rules['truncate'] - 6).'…';
        }

        // validation: value is not null/empty, otherwise set default or raise error
        if (empty($value)) {
            if (isset($rules['default']) && !empty($rules['default'])) {
                $value = $rules['default'];
            } elseif (isset($rules['required']) && $rules['required']) {
                $errors[] = 'Required';
            }
        }

        // further validations
        if (!empty($value)) {

            // validation: max string length
            if (isset($rules['maxlen']) && strlen($value) > $rules['maxlen']) {
                $errors[] = "Max {$rules['maxlen']} chars";
            }

            // validation: string conforms to pattern
            if (isset($rules['preg_match'])) {
                if (is_array($rules['preg_match'])) {
                    $pattern = $rules['preg_match'][0];
                    $errmsg = $rules['preg_match'][1];
                } else {
                    $pattern = $rules['preg_match'];
                    $errmsg = "Wrong format";
                }
                $preg_match = preg_match($pattern, $value);
                if ($preg_match === 0) {
                    $errors[] = $errmsg;
                }
            }

            // validation: string is one of the valid values
            if (isset($rules['validvalues']) && is_array($rules['validvalues']) && !in_array($value, $rules['validvalues'])) {
                $errors[] = "Invalid value";
            }

            // validation: string is not one of invalid values
            if (isset($rules['invalidvalues']) && is_array($rules['invalidvalues'])
                && in_array($value, $rules['invalidvalues'])) {
                $errors[] = "'{$value}' is an invalid value";
            }

            // validation: minimum value
            if (isset($rules['minvalue']) && $value < $rules['minvalue']) {
                $errors[] = "Min value is {$rules['minvalue']}";
            }

            // validation: maximum value
            if (isset($rules['maxvalue']) && $value > $rules['maxvalue']) {
                $errors[] = "Max value is {$rules['maxvalue']}";
            }

            // validation: url
            if (isset($rules['url']) && $rules['url']) {
                $value = trim($value);
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    $errors[] = "Must be a valid URL";
                }
            }

            // validation: email
            if (isset($rules['email']) && $rules['email']) {
                $value = trim($value);
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Must be a valid email address";
                }
            }

            // validation: ip
            if (isset($rules['ip']) && $rules['ip'] && !filter_var($value, FILTER_VALIDATE_IP)) {
                $errors[] = "Must be a valid IP address";
            }

            // validation: future
            if (isset($rules['future']) && $rules['future'] >= 0 && $value instanceof \DateTime) {
                $hours = $rules['future'];
                if ($value < date_create("+$hours hour")) {
                    $errors[] = "Must be a future date".($hours > 0 ? " (>= now + $hours hours)"
                            : '');
                }
            }
        }

        return $errors;
    }

    public function validateThrowingException(&$entity, array $validation_rules,
                                              array $initErrors = array())
    {
        $errors = $this->validate($entity, $validation_rules, $initErrors);
        if (!empty($errors)) {
            throw new ValidationException($entity, $errors);
        }
        return array();
    }
}