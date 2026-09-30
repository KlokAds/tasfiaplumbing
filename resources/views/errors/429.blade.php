@include('errors.brand', [
    'code' => 429,
    'title' => 'Too many requests',
    'message' => 'You tried a few times in a short while. Please wait a minute and try again.',
    'search' => false,
    'retry' => true,
])
