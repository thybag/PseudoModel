<?php
namespace thybag\PseudoModel\Models;

use ArrayAccess;
use JsonSerializable;
use Illuminate\Support\Str;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Arrayable;
use thybag\PseudoModel\Exceptions\PersistException;
use Illuminate\Database\Eloquent\Concerns\HasEvents;
use Illuminate\Database\Eloquent\Concerns\HasAttributes;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUniqueIds;
use Illuminate\Database\Eloquent\Concerns\HidesAttributes;
use Illuminate\Database\Eloquent\Concerns\GuardsAttributes;
use Illuminate\Database\Eloquent\Concerns\HasRelationships;
use Illuminate\Contracts\Broadcasting\HasBroadcastChannel;
use Illuminate\Contracts\Support\CanBeEscapedWhenCastToString;

/**
 * Class PseudoModels
 *
 * A Non-Eloquent Model base
 */
abstract class PseudoModel implements ArrayAccess, Arrayable, Jsonable, JsonSerializable, HasBroadcastChannel, CanBeEscapedWhenCastToString
{
    use HasEvents;
    use HasAttributes;
    use HidesAttributes;
    use GuardsAttributes;
    use HasRelationships;
    use HasTimestamps;
    use HasUniqueIds;

    /**
     * The array of trait initializers that will be called on each new instance.
     *
     * @var array
     */
    protected static $traitInitializers = [];
    /**
     * The array of booted models.
     *
     * @var array
     */
    protected static $booted = [];
    protected static $dispatcher;

    protected $exists = false;

    /**
     * Indicates if an exception should be thrown when trying to access a missing attribute on a retrieved model.
     *
     * @var bool
     */
    protected static $modelsShouldPreventAccessingMissingAttributes = false;

    /**
     * Indicates that the object's string representation should be escaped when __toString is invoked.
     *
     * @var bool
     */
    protected $escapeWhenCastingToString = false;

    public $wasRecentlyCreated = false;

    /**
     * Indicates whether lazy loading should be restricted on all models.
     *
     * @var bool
     */
    protected static $modelsShouldPreventLazyLoading = false;

    /**
     * The callback that is responsible for handling lazy loading violations.
     *
     * @var callable|null
     */
    protected static $lazyLoadingViolationCallback;

    /**
     * Indicates if an exception should be thrown instead of silently discarding non-fillable attributes.
     *
     * @var bool
     */
    protected static $modelsShouldPreventSilentlyDiscardingAttributes = false;

    /**
     * The callback that is responsible for handling discarded attribute violations.
     *
     * @var callable|null
     */
    protected static $discardedAttributeViolationCallback;


    /**
     * The callback that is responsible for handling missing attribute violations.
     *
     * @var callable|null
     */
    protected static $missingAttributeViolationCallback;

    /**
     * Indicates if broadcasting is currently enabled.
     *
     * @var bool
     */
    protected static $isBroadcasting = true;



    /**
     * Setup
     * @param array $attributes [description]
     */
    public function __construct($attributes = array())
    {
        $this->bootIfNotBooted();

        $this->initializeTraits();

        $this->syncOriginal();

        $this->fill($attributes);
    }

    /**
     * Check if the model needs to be booted and if so, do it.
     *
     * @return void
     */
    protected function bootIfNotBooted()
    {
        if (! isset(static::$booted[static::class])) {
            static::$booted[static::class] = true;

            $this->fireModelEvent('booting', false);
            static::booting();
            static::boot();
            static::booted();

            $this->fireModelEvent('booted', false);
        }
    }

    /**
     * Perform any actions required before the model boots.
     *
     * @return void
     */
    protected static function booting()
    {
        //
    }

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        static::bootTraits();
    }

    /**
     * Boot all of the bootable traits on the model.
     *
     * @return void
     */
    protected static function bootTraits()
    {
        $class = static::class;
        $booted = [];
        static::$traitInitializers[$class] = [];
        foreach (class_uses_recursive($class) as $trait) {
            $method = 'boot' . class_basename($trait);
            if (method_exists($class, $method) && !in_array($method, $booted)) {
                forward_static_call([$class, $method]);
                $booted[] = $method;
            }
            $method = 'initialize' . class_basename($trait);
            if (method_exists($class, $method)) {
                static::$traitInitializers[$class][] = $method;
                static::$traitInitializers[$class] = array_unique(
                    static::$traitInitializers[$class]
                );
            }
        }
    }

    public static function make(array $attributes = [])
    {
        return static::instance($attributes);
    }

    /**
     * Perform any actions required after the model boots.
     *
     * @return void
     */
    protected static function booted()
    {
        //
    }

    /**
     * Clear the list of booted models so they will be re-booted.
     *
     * @return void
     */
    public static function clearBootedModels()
    {
        static::$booted = [];
    }

    public static function create(array $attributes = [])
    {
        $new = static::instance($attributes);
        $new->save();
        return $new;
    }

    /**
     * Initialize any initializable traits on the model.
     *
     * @return void
     */
    protected function initializeTraits()
    {
        foreach (static::$traitInitializers[static::class] as $method) {
            $this->{$method}();
        }
    }

    /**
     * Create instance of model
     *
     * @param (array) $attributes
     * @return model
     */
    public static function instance($attributes = array())
    {
        return new static($attributes);
    }

    /**
     * Dynamically retrieve attributes on the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Determine if the given attribute exists.
     *
     * @param  mixed  $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return ! is_null($this->getAttribute($offset));
    }

    /**
     * Get the value for a given offset.
     *
     * @param  mixed  $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->getAttribute($offset);
    }

    /**
     * Set the value for a given offset.
     *
     * @param  mixed  $offset
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * Unset the value for a given offset.
     *
     * @param  mixed  $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->attributes[$offset], $this->relations[$offset]);
    }

    /**
     * Dynamically set attributes on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Dynamically check if attributes is set on the model.
     *
     * @param  string  $key
     * @return bool
     */
    public function __isset($key)
    {
        return $this->offsetExists($key);
    }


    /**
     * return attributes as array
     * @return array attributes
     */
    public function toArray($mutated = false): array
    {
        if (!$mutated) {
            return $this->attributes;
        } else {
            $attributes = array_keys($this->attributes);
            $attributes = array_diff($attributes, $this->hidden);
            $values = [];
            foreach ($attributes as $k) {
                $values[$k] = $this->getAttribute($k);
            }
            return $values;
        }
    }

    /**
     * Convert the model instance to JSON.
     *
     * @param  int  $options
     * @return string
     *
     * @throws \Illuminate\Database\Eloquent\JsonEncodingException
     */
    public function toJson($options = 0)
    {
        $json = json_encode($this->jsonSerialize(), $options);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw JsonEncodingException::forModel($this, json_last_error_msg());
        }

        return $json;
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

 /**
     * Indicate that models should prevent lazy loading, silently discarding attributes, and accessing missing attributes.
     *
     * @param  bool  $shouldBeStrict
     * @return void
     */
    public static function shouldBeStrict(bool $shouldBeStrict = true)
    {
        static::preventLazyLoading($shouldBeStrict);
        static::preventSilentlyDiscardingAttributes($shouldBeStrict);
        static::preventAccessingMissingAttributes($shouldBeStrict);
    }

    /**
     * Prevent model relationships from being lazy loaded.
     *
     * @param  bool  $value
     * @return void
     */
    public static function preventLazyLoading($value = true)
    {
        static::$modelsShouldPreventLazyLoading = $value;
    }

    /**
     * Register a callback that is responsible for handling lazy loading violations.
     *
     * @param  callable|null  $callback
     * @return void
     */
    public static function handleLazyLoadingViolationUsing(?callable $callback)
    {
        static::$lazyLoadingViolationCallback = $callback;
    }

    /**
     * Prevent non-fillable attributes from being silently discarded.
     *
     * @param  bool  $value
     * @return void
     */
    public static function preventSilentlyDiscardingAttributes($value = true)
    {
        static::$modelsShouldPreventSilentlyDiscardingAttributes = $value;
    }

    /**
     * Register a callback that is responsible for handling discarded attribute violations.
     *
     * @param  callable|null  $callback
     * @return void
     */
    public static function handleDiscardedAttributeViolationUsing(?callable $callback)
    {
        static::$discardedAttributeViolationCallback = $callback;
    }

    /**
     * Register a callback that is responsible for handling missing attribute violations.
     *
     * @param  callable|null  $callback
     * @return void
     */
    public static function handleMissingAttributeViolationUsing(?callable $callback)
    {
        static::$missingAttributeViolationCallback = $callback;
    }

    /**
     * Execute a callback without broadcasting any model events for all model types.
     *
     * @param  callable  $callback
     * @return mixed
     */
    public static function withoutBroadcasting(callable $callback)
    {
        $isBroadcasting = static::$isBroadcasting;

        static::$isBroadcasting = false;

        try {
            return $callback();
        } finally {
            static::$isBroadcasting = $isBroadcasting;
        }
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array  $attributes
     * @return $this
     *
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     */
    public function fill(array $attributes)
    {
        $totallyGuarded = $this->totallyGuarded();

        foreach ($this->fillableFromArray($attributes) as $key => $value) {
            // The developers may choose to place some attributes in the "fillable" array
            // which means only those attributes may be set through mass assignment to
            // the model, and all others will just get ignored for security reasons.
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            } elseif ($totallyGuarded) {
                throw new MassAssignmentException(sprintf(
                    'Add [%s] to fillable property to allow mass assignment on [%s].',
                    $key,
                    get_class($this)
                ));
            }
        }

        return $this;
    }

    /**
     * Fill the model with an array of attributes. Force mass assignment.
     *
     * @param  array  $attributes
     * @return $this
     */
    public function forceFill(array $attributes)
    {
        return static::unguarded(function () use ($attributes) {
            return $this->fill($attributes);
        });
    }


    public function save(array $options = []): bool
    {
        $this->mergeAttributesFromCachedCasts();

        // If the "saving" event returns false we'll bail out of the save and return
        // false, indicating that the save failed. This provides a chance for any
        // listeners to cancel save operations if validations fail or whatever.
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        // Nothing changed? no save
        if (!$this->isDirty()) {
            return true;
        }

        $action = $this->exists ? 'update' : 'create';
        if ($action == 'update') {
            if ($this->fireModelEvent('updating') === false) {
                return false;
            }
            if ($this->fireModelEvent('updated') === false) {
                return false;
            }
        } else {
            if ($this->fireModelEvent('creating') === false) {
                return false;
            }
            if ($this->fireModelEvent('created') === false) {
                return false;
            }
        }
        $saved = $this->persist($action, $options);

        // If the model is successfully saved, we need to do a few more things once
        // that is done. We will call the "saved" method here to run any actions
        // we need to happen after a model gets successfully saved right here.
        if ($saved) {
            $this->finishSave($options);
            
        }

        return $saved;
    }

    /**
     * Save the model to the database without raising any events.
     *
     * @param  array  $options
     * @return bool
     */
    public function saveQuietly(array $options = [])
    {
        return static::withoutEvents(fn () => $this->save($options));
    }

    public function saveOrFail(array $options = [])
    {
        if (!$this->save($options)) {
            throw new PersistException("Unable to persist model.");
        };
    }

    /**
     * Perform any actions that are necessary after the model is saved.
     *
     * @param  array  $options
     * @return void
     */
    protected function finishSave(array $options)
    {
        $this->fireModelEvent('saved', false);
        // This normally hapopoens as part of `performInsert`
        $this->exists = true;
        $this->syncOriginal();
    }

        /**
     * Update the model in the database.
     *
     * @param  array  $attributes
     * @param  array  $options
     * @return bool
     */
    public function update(array $attributes = [], array $options = [])
    {
        if (! $this->exists) {
            return false;
        }

        return $this->fill($attributes)->save($options);
    }

    /**
     * Update the model in the database within a transaction.
     *
     * @param  array  $attributes
     * @param  array  $options
     * @return bool
     *
     * @throws \Throwable
     */
    public function updateOrFail(array $attributes = [], array $options = [])
    {
        if (! $this->exists) {
            return false;
        }

        return $this->fill($attributes)->saveOrFail($options);
    }

    /**
     * Update the model in the database without raising any events.
     *
     * @param  array  $attributes
     * @param  array  $options
     * @return bool
     */
    public function updateQuietly(array $attributes = [], array $options = [])
    {
        if (! $this->exists) {
            return false;
        }

        return $this->fill($attributes)->saveQuietly($options);
    }


    /**
     * Delete the model from the database.
     *
     * @return bool|null
     *
     * @throws \Exception
     */
    public function delete()
    {

        // If the model doesn't exist, there is nothing to delete so we'll just return
        // immediately and not do anything else. Otherwise, we will continue with a
        // deletion process on the model, firing the proper events, and so forth.
        if (! $this->exists) {
            return;
        }

        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        $deleted = $this->persist('delete');

        // Once the model has been deleted, we will fire off the deleted event so that
        // the developers may hook into post-delete operations. We will then return
        // a boolean true as the delete is presumably successful on the database.
        $this->fireModelEvent('deleted', false);

        return $deleted;
    }



    /**
     * Create a new instance of the given model.
     *
     * @param  array  $attributes
     * @param  bool  $exists
     * @return static
     */
    public function newInstance($attributes = [], $exists = false)
    {
        // This method just provides a convenient way for us to generate fresh model
        // instances of this current model. It is particularly useful during the
        // hydration of new objects via the Eloquent query builder instances.
        $model = new static((array) $attributes);

        $model->exists = $exists;

        return $model;
    }

    /**
     * Create a new Eloquent Collection instance.
     *
     * @param  array  $models
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function newCollection(array $models = [])
    {
        return new Collection($models);
    }

    /**
     * Clone the model into a new, non-existing instance.
     *
     * @param  array|null  $except
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function replicate(array $except = null)
    {
        $attributes = Arr::except(
            $this->attributes,
            $except
        );

        return tap(new static, function ($instance) use ($attributes) {
            $instance->setRawAttributes($attributes);

            $instance->setRelations($this->relations);
        });
    }

    /**
     * Get the attributes that should be converted to dates.
     * This is just here because Laravel might call it. Laravel only uses it for created-at/updated-at
     * timestamps which are not relevant for a Pseudomodel.
     *
     * @return array
     */
    public function getDates()
    {
        return [];
    }

     /**
      * Override getIncrementing as we don't have db keys
      * @return false
      */
    public function getIncrementing()
    {
        return false;
    }

    /**
     * Persist model changes. Called on save & delete.
     *
     * @param  string $action  create|update|delete
     * @param  array  $options
     * @return boolean true|false
     */
    protected function persist($action, array $options = []): bool
    {
        return true;
    }

    /**
     * Determine if accessing missing attributes is disabled.
     *
     * @return bool
     */
    public static function preventsAccessingMissingAttributes()
    {
        return false;
    }


    /**
     * Prevent accessing missing attributes on retrieved models.
     *
     * @param  bool  $value
     * @return void
     */
    public static function preventAccessingMissingAttributes($value = true)
    {
        static::$modelsShouldPreventAccessingMissingAttributes = $value;
    }

    /**
     * Get the broadcast channel route definition that is associated with the given entity.
     *
     * @return string
     */
    public function broadcastChannelRoute()
    {
        return str_replace('\\', '.', get_class($this)).'.{'.Str::camel(class_basename($this)).'}';
    }

    /**
     * Get the broadcast channel name that is associated with the given entity.
     *
     * @return string
     */
    public function broadcastChannel()
    {
        return str_replace('\\', '.', get_class($this)).'.'.$this->getKey();
    }

    /**
     * Convert the model to its string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->escapeWhenCastingToString
            ? e($this->toJson())
            : $this->toJson();
    }

    /**
     * Indicate that the object's string representation should be escaped when __toString is invoked.
     *
     * @param  bool  $escape
     * @return $this
     */
    public function escapeWhenCastingToString($escape = true)
    {
        $this->escapeWhenCastingToString = $escape;

        return $this;
    }
}
