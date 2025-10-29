<?php

if (!function_exists('app_path')) {
    /**
     * Get the path to the app of the install.
     *
     * @param  string  $path
     * @return string
     *
     * @Feature add function app_path($path = '')
     */
    function app_path($path = '')
    {
        return app()->basePath().'/app'.($path ? '/'.$path : $path);
    }
}
