# PseudoModel [![Build Status](https://travis-ci.org/thybag/PseudoModel.svg?branch=master)](https://travis-ci.org/thybag/PseudoModel)

PseudoModel is an eloquent-like base model for laravel, aiming to mirror the majority of the laravels basic model functionality. 

Using PseudoModel you can represent and interact with none-database based data in much the same way you would any normal Model, hopefully simplifying the code you need the write. In the past I've used this approach for mapping on to LDAP as well as 3rd party API's.

In order to provide the standard eloquent eventing behaviors, you can use the `persist` method to define your save logic. The method will change depending on whether the change is create, update or delete.


```
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

```

This library is still in progress so not yet recommended for use in production.
