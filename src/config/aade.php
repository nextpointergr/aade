<?php

return [
    'uri' => env(
        'AADE_URI',
        'https://www1.gsis.gr/webtax2/wsgsis/RgWsPublic/RgWsPublicPort'
    ),
    'username' => env('AADE_USERNAME'),
    'password' => env('AADE_PASSWORD'),
    'called_by' => env('AADE_CALLED_BY'),
];