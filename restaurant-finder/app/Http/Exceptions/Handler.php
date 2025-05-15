public function register(): void
{
    $this->reportable(function (Throwable $e) {
        // Custom error reporting
    });
    
    $this->renderable(function (Throwable $e, Request $request) {
        if ($request->is('api/*')) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->getCode() ?: 500
            ], 500);
        }
    });
}