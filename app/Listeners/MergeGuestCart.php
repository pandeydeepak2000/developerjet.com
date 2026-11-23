<?php

namespace App\Listeners;

use App\Services\CartService;
use Illuminate\Auth\Events\Login;

class MergeGuestCart
{
    public function __construct(private CartService $carts) {}

    public function handle(Login $event): void
    {
        $this->carts->mergeGuestInto($event->user);
    }
}
