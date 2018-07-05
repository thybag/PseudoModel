<?php
namespace thybag\PseudoModel;

use Illuminate\Support\ServiceProvider;
use thybag\PseudoModel\Models\PseudoModel;

/**
 * PseudoModelProvider
 *
 */
class PseudoModelProvider extends ServiceProvider
{
    public function boot()
    {
        // Register for events
        PseudoModel::setEventDispatcher($this->app['events']);
    }
}
