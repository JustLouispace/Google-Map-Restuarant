protected $middlewareGroups = [
    'api' => [
        // Other middleware...
        \App\Http\Middleware\CacheControl::class,
    ],
];