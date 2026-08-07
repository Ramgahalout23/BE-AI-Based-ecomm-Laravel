<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Unpaid Order Auto-Cancel Window
    |--------------------------------------------------------------------------
    |
    | PENDING orders (abandoned online-payment checkouts) are automatically
    | cancelled by `orders:cancel-unpaid` once they are older than this many
    | minutes. This file is the code-level fallback: admins override both the
    | toggle and the window from Admin → Settings (`autoCancelUnpaidEnabled` /
    | `autoCancelUnpaidMinutes`, DB-backed), and the command, the order-detail
    | API deadline (`autoCancelMinutes` / `autoCancelAt`), and the scheduler all
    | read those settings first — falling back here when unset.
    |
    */

    'auto_cancel_minutes' => (int) env('ORDERS_AUTO_CANCEL_MINUTES', 45),

];
