@extends('errors.partials.hoso-frame', [
    'code' => 403,
    'title' => 'Access denied',
    // Abort messages are written by us (e.g. an inactive module); the generic policy text is not helpful.
    'message' => filled($exception?->getMessage()) && $exception->getMessage() !== 'This action is unauthorized.'
        ? $exception->getMessage()
        : 'You do not have permission to view this page. If you believe this is a mistake, contact your administrator.',
])
