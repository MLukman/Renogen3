<?php

namespace App\Validation;

class Rules implements \ArrayAccess
{
    protected $rules;

    static public function new()
    {
        return new self();
    }

    public function getAll(): array
    {
        return $this->rules;
    }

    public function required(): self
    {
        $this->rules['required'] = 1;
        return $this;
    }

    public function trim(): self
    {
        $this->rules['trim'] = 1;
        return $this;
    }

    public function unique($among = null): self
    {
        $this->rules['unique'] = $among ?: 1;
        return $this;
    }

    public function maxlen(int $m): self
    {
        $this->rules['maxlen'] = $m;
        return $this;
    }

    public function truncate(int $m): self
    {
        $this->rules['truncate'] = $m;
        return $this;
    }

    public function pregmatch($regex, $errmsg = "Wrong format"): self
    {
        $this->rules['preg_match'] = [$regex, $errmsg];
        return $this;
    }

    public function validvalues(array $values): self
    {
        $this->rules['validvalues'] = $values;
        return $this;
    }

    public function invalidvalues(array $values): self
    {
        $this->rules['invalidvalues'] = $values;
        return $this;
    }

    public function minvalue(float $m): self
    {
        $this->rules['minvalue'] = $m;
        return $this;
    }

    public function maxvalue(float $m): self
    {
        $this->rules['maxvalue'] = $m;
        return $this;
    }

    public function url(): self
    {
        $this->rules['url'] = 1;
        return $this;
    }

    public function email(): self
    {
        $this->rules['email'] = 1;
        return $this;
    }

    /**
     * Must be valid IP address
     * @return \self
     */
    public function ip(): self
    {
        $this->rules['ip'] = 1;
        return $this;
    }

    /**
     * Applies to DateTime only. Must be future date >= now + $hours.
     * @param int $hours Force date must be at least this many hours after now.
     * @return $this
     */
    public function future(int $hours = 0)
    {
        $this->rules['future'] = $hours;
        return $this;
    }

    public function callback(\Closure $callback)
    {
        if (!isset($this->rules['callbacks'])) {
            $this->rules['callbacks'] = [];
        }
        $this->rules['callbacks'][] = $callback;
        return $this;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->rules[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->rules[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        $this->rules[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->rules[$offset]);
    }
}