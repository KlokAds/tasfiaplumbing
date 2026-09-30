@include('errors.brand', [
    'code' => 404,
    'title' => "We can't find that page",
    'message' => 'The page may have moved or no longer exists. Try searching, or pick one of the sections below.',
    'search' => true,
    'retry' => false,
])
