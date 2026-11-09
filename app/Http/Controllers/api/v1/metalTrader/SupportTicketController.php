<?php

namespace App\Http\Controllers\api\v1\metalTrader;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\v1\Concerns\HandlesClientSupportTickets;
use App\Services\SupportTicketService;

class SupportTicketController extends Controller
{
    use HandlesClientSupportTickets;

    public function __construct(
        private readonly SupportTicketService $tickets,
    ) {
    }

    protected function ticketService(): SupportTicketService
    {
        return $this->tickets;
    }
}
