<?php

namespace Karpack\Support\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasAdditionalProperties
{
    /**
     * The model's additional properties.
     *
     * @var array
     */
    protected $additionalProperties = [];

    /**
     * Set an additional attribute to the model. If there is a mutator set for the property,
     * then the value is mutated before setting.
     *
     * @param string $key
     * @param string $value
     * @return $this
     */
    public function setAdditionalProperty($key, $value)
    {
        $this->additionalProperties[$key] = $this->mutatedPropertyValue($key, $value);

        return $this;
    }

    /**
     * Set an additional attribute to the model without any mutation and checks.
     *
     * @param string $key
     * @param string $value
     * @return mixed
     */
    public function setRawAdditionalProperty($key, $value)
    {
        $this->additionalProperties[$key] = $value;

        return $this;
    }

    /**
     * Returns the mutated value for the property, if a mutation exists.
     * 
     * @param string $property
     * @param string $value
     * @return string
     */
    public function mutatedPropertyValue($property, $value)
    {
        if ($this->hasSetPropertyMutator($property)) {
            $value = $this->setMutatedPropertyValue($property, $value);
        }
        return $value;
    }

    /**
     * Determine if a set mutator exists for a property.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasSetPropertyMutator($key)
    {
        return method_exists($this, 'set' . Str::studly($key) . 'Property');
    }

    /**
     * Set the value of an attribute using its mutator.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function setMutatedPropertyValue($key, $value)
    {
        return $this->{'set' . Str::studly($key) . 'Property'}($value);
    }

    /**
     * Get an additional attribute from the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAdditionalProperty($key)
    {
        if (
            $key && array_key_exists($key, $this->additionalProperties) &&
            isset($this->additionalProperties[$key])
        ) {
            return $this->additionalProperties[$key];
        }
    }

    /**
     * Get an attribute array of all arrayable attributes.
     *
     * @return array
     */
    protected function getArrayableAttributes()
    {
        if ($this instanceof Model) {
            return $this->getArrayableItems(
                array_merge($this->getAttributes(), $this->additionalProperties)
            );
        }
        return $this->additionalProperties;
    }

    /**
     * Dynamically retrieve attributes on the model.
     * 
     * Overrides the __get magic method on the base model. Here we check for the presence
     * of a value on `$additionalProperties` first and returns it, if one is found. Proper 
     * `getMutator` operations are carried out before returning the value. If no property 
     * is found then the parent magic method is called.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        $value = $this->getAdditionalProperty($key);

        if (isset($value)) {
            // If the attribute has a get mutator, we will call that then return what
            // it returns as the value, which is useful for transforming values on
            // retrieval from the model to a form that is more useful for usage.
            if ($this instanceof Model && $this->hasGetMutator($key)) {
                return $this->mutateAttribute($key, $value);
            }
            return $value;
        }
        return parent::__get($key);
    }
}
