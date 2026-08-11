<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cash on Delivery
    |--------------------------------------------------------------------------
    |
    | COD is not a confirmed RunIX workflow yet. The `orders` schema
    | supports merchant_amount/cod_amount/fee_payer regardless, but while
    | this is false the application forces merchant_amount and cod_amount
    | to 0 server-side (never trusting client input for them) and hides
    | the COD fields from the Orders UI. Flip this on once COD is
    | confirmed — no schema changes will be needed.
    |
    */

    'cod_enabled' => env('RUNIX_COD_ENABLED', false),

];
