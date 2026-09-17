<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Marketing site contact details
    |--------------------------------------------------------------------------
    |
    | These are intentionally empty by default: the contact page shows a
    | clear "not configured yet" placeholder rather than invented details.
    | Set them in .env before going live.
    |
    */

    'contact' => [
        'sales_email' => env('CONTACT_SALES_EMAIL'),
        'support_email' => env('CONTACT_SUPPORT_EMAIL'),
        'phone' => env('CONTACT_PHONE'),
        'address' => env('CONTACT_ADDRESS'),
    ],

];
