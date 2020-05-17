<?php

namespace Bond;

class Application
{
}

// Testing, not sure yet

// use Illuminate\Container\Container;

// class Application extends Container
// {
//     public function __construct($basePath = null)
//     {
//         // if ($basePath) {
//         //     $this->setBasePath($basePath);
//         // }

//         static::setInstance($this);
//         $this->instance('app', $this);

//         // $this->instance('config', new Config());

//         $this->singleton('config', \Bond\Config::class);

//         // foreach ([
//         //     'app' => [self::class, \Psr\Container\ContainerInterface::class],

//         //     'config' => [\Bond\Config::class],

//         //     // 'view'                 => [\Illuminate\View\Factory::class, \Illuminate\Contracts\View\Factory::class],
//         // ] as $key => $aliases) {
//         //     foreach ($aliases as $alias) {
//         //         $this->alias($key, $alias);
//         //     }
//         // }



//     }
// }
