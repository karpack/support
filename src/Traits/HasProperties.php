<?php

namespace Karpack\Support\Traits;

use Illuminate\Support\Arr;

trait HasProperties
{
    use HasAdditionalProperties;

    /**
     * Cache of loaded SystemProperty models.
     * 
     * @var \Illuminate\Support\Collection
     */
    protected $cachedProperties;

    /**
     * Returns the property model class name. This model class holds the data in 
     * key value pair columns.
     * 
     * @return string
     */
    protected abstract function propertyModelClass();

    /**
     * Returns the properties that can be set for this entity.
     * 
     * @return array
     */
    protected abstract function properties();

    /**
     * Loads the properties (given as parameter or loaded from relation) as attributes
     * on this model. This facilitates the current model, direct access to the property values 
     * using the property name.
     * 
     * After adding all the properties as attribute to this model, we will hide the relation
     * from the model.
     * 
     * @param array|null $properties
     * @return $this
     */
    public function loadProperties($properties = null)
    {
        $properties = $properties ?: $this->getProperties();

        foreach ($properties ?: array() as $property) {
            $this->setRawAdditionalProperty($property->property, $property->property_value);
        }

        return $this;
    }

    /**
     * Returns a property model for the given key if one exists or returns undefined.
     * 
     * @param string $propertyKey
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function getProperty($propertyKey)
    {
        return collect($this->getProperties())->first(function ($property) use ($propertyKey) {
            return $property->property === $propertyKey;
        });
    }

    /**
     * Loads all the property models of this entity.
     * 
     * @return \Illuminate\Support\Collection
     */
    private function getProperties()
    {
        if (isset($this->cachedProperties)) {
            return $this->cachedProperties;
        }
        $modelName = $this->propertyModelClass();

        return $this->cachedProperties = $modelName::all();
    }

    /**
     * Saves the given properties. If a property model exists for the key, then that model is updated, 
     * otherwise a new property model is created. If the second argument `$allowAllProps` is not set to 
     * true, only the properties returned in the `$this->properties()` array will be saved. By default, 
     * this argument is set to false.
     * 
     * @param array $properties 
     * @param bool $allowAllProps
     */
    public function saveProperties(array $properties, $allowAllProps = false)
    {
        $allowedProperties = $this->properties() ?: [];

        foreach ($properties ?: [] as $key => $value) {
            // Update only if the property key exists in the model properties list 
            // or if the allowAllProps flag is set.
            if (!in_array($key, $allowedProperties) && !$allowAllProps) {
                continue;
            }
            $propertyModel = $this->getProperty($key) ?: $this->createProperty($key);
            $mutatedValue = $this->mutatedPropertyValue($propertyModel->property, $value);

            $propertyModel->property_value = $mutatedValue;

            if ($propertyModel->save()) {
                if ($propertyModel->wasRecentlyCreated) {
                    $this->cachedProperties->add($propertyModel);
                }
                $this->setRawAdditionalProperty($key, $mutatedValue);
            }
        }
        return $this;
    }

    /**
     * Creates a new property model for the given key and returns it.
     * 
     * @param string $propertyKey
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function createProperty($propertyKey)
    {
        $propertyModel = $this->propertyModelClass();

        $propertyModel = new $propertyModel;
        $propertyModel->property = $propertyKey;

        return $propertyModel;
    }

    /**
     * Deletes a property of the given key.
     * 
     * @param string $propertyKey
     * @return bool
     */
    public function deleteProperty($propertyKey)
    {
        $model = $this->getProperty($propertyKey);

        if (is_null($model)) {
            return true;
        }

        if ($result = $model->delete()) {
            $index = $this->cachedProperties->search(function ($propertyModel) use ($propertyKey) {
                return $propertyModel->property === $propertyKey;
            });

            // Delete the property model from the cached collection, if the model delete was
            // successfull. Also remove it from the additional_properties array
            if ($index !== false) {
                $this->cachedProperties->forget($index);
            }
            Arr::forget($this->additionalProperties, $propertyKey);
        }
        return $result;
    }
}