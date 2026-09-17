<?php
return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'api#getTrack', 'url' => '/api/track', 'verb' => 'GET'],
        ['name' => 'api#stream', 'url' => '/api/stream/{fileid}', 'verb' => 'GET'],
        ['name' => 'api#addComment', 'url' => '/api/comment', 'verb' => 'POST'],
        ['name' => 'api#updateComment', 'url' => '/api/comment/{id}', 'verb' => 'PUT'],
        ['name' => 'api#deleteComment', 'url' => '/api/comment/{id}', 'verb' => 'DELETE'],
        ['name' => 'api#setCommentStatus', 'url' => '/api/comment/{id}/status', 'verb' => 'POST'],
        ['name' => 'api#updateMetadata', 'url' => '/api/metadata', 'verb' => 'POST'],
        ['name' => 'api#setTrackStatus', 'url' => '/api/track/status', 'verb' => 'POST'],
        ['name' => 'project#get', 'url' => '/api/project', 'verb' => 'GET'],
        ['name' => 'project#reorder', 'url' => '/api/project/reorder', 'verb' => 'POST'],
        ['name' => 'settings#get', 'url' => '/api/settings', 'verb' => 'GET'],
        ['name' => 'settings#save', 'url' => '/api/settings', 'verb' => 'POST'],
        ['name' => 'dashboard#get', 'url' => '/api/dashboard', 'verb' => 'GET'],
    ],
];
