<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;

/** Customers: own tickets only. Staff: via department membership + tickets.view (never through the customer routes' ownership shortcut). */
class TicketPolicy
{
    public function view(User $user, Ticket $ticket): bool
    {
        return $ticket->user_id === $user->id;
    }

    public function staffView(User $user, Ticket $ticket): bool
    {
        return TicketService::staffCanAccess($user, $ticket);
    }
}
