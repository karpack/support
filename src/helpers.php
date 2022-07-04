<?php

use Illuminate\Container\Container;

if (!function_exists('container')) {
    /**
     * Returns the application container, if exists or creates a new one and returns it.
     *
     * @return \Illuminate\Container\Container
     */
    function container()
    {
        return Container::getInstance();
    }
}