<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Constants\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use LogicException;

class SupportTicketController extends Controller
{
    public function __construct(
        private readonly SupportTicketService $tickets,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeTickets();

        $query = SupportTicket::query()
            ->with([
                'creator',
                'lastRepliedAdmin:id,full_name,avatar',
                'operators.admin:id,full_name,avatar',
            ])
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('code')) {
            $query->where('code', 'like', '%' . $request->input('code') . '%');
        }

        if ($request->filled('creator_type')) {
            $query->where('creator_type', $request->input('creator_type'));
        }

        if ($request->filled('admin_id')) {
            $query->whereHas('operators', fn ($q) => $q->where('admin_id', $request->integer('admin_id')));
        }

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($builder) use ($q) {
                $builder->where('subject', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            });
        }

        $page = $query->paginate($request->input('count') ?? 50);

        $page->getCollection()->transform(function (SupportTicket $ticket) {
            return $this->transformTicketSummary($ticket);
        });

        return response()->json(['data' => $page]);
    }

    public function show(SupportTicket $ticket): JsonResponse
    {
        $this->authorizeTickets();

        $ticket->load([
            'creator',
            'messages.sender',
            'lastRepliedAdmin:id,full_name,avatar',
            'operators.admin:id,full_name,avatar',
        ]);

        return response()->json(['data' => $this->transformTicketDetail($ticket)]);
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorizeTickets();

        $data = $request->validate([
            'body' => 'required|string|min:1|max:5000',
            'image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
        ], [
            'body.required' => 'متن پیام الزامی است.',
            'image.max' => 'حجم تصویر نباید بیشتر از ۲ مگابایت باشد.',
        ]);

        try {
            $updated = $this->tickets->reply(
                ticket: $ticket,
                sender: Auth::guard('admin-api')->user(),
                body: $data['body'],
                image: $request->file('image'),
            );
        } catch (LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'پاسخ ارسال شد.',
            'data' => $this->transformTicketDetail($updated),
        ]);
    }

    public function operatorStats(Request $request): JsonResponse
    {
        $this->authorizeTickets();

        $stats = Admin::query()
            ->select(['admins.id', 'admins.full_name', 'admins.avatar'])
            ->join('support_ticket_operators as sto', 'sto.admin_id', '=', 'admins.id')
            ->leftJoin('support_tickets as st', 'st.id', '=', 'sto.support_ticket_id')
            ->groupBy('admins.id', 'admins.full_name', 'admins.avatar')
            ->selectRaw('COUNT(DISTINCT sto.support_ticket_id) as answered_tickets_count')
            ->selectRaw('SUM(sto.replies_count) as replies_count')
            ->selectRaw('COUNT(DISTINCT CASE WHEN st.rating IS NOT NULL THEN st.id END) as rated_tickets_count')
            ->selectRaw('AVG(st.rating) as average_rating')
            ->orderByDesc('answered_tickets_count')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'full_name' => $row->full_name,
                    'avatar' => $row->avatar,
                    'answered_tickets_count' => (int) $row->answered_tickets_count,
                    'replies_count' => (int) $row->replies_count,
                    'rated_tickets_count' => (int) $row->rated_tickets_count,
                    'average_rating' => $row->average_rating !== null
                        ? round((float) $row->average_rating, 2)
                        : null,
                ];
            });

        return response()->json(['data' => $stats]);
    }

    private function authorizeTickets(): void
    {
        $admin = Auth::guard('admin-api')->user();
        if (!$admin || !$admin->isAbleTo('manage-support-tickets')) {
            abort(403, 'دسترسی مجاز نیست.');
        }
    }

    private function transformTicketSummary(SupportTicket $ticket): array
    {
        $creator = $ticket->creator;
        $creatorName = null;
        if ($creator) {
            $creatorName = $creator->full_name ?? $creator->name ?? $creator->phone ?? null;
        }

        return [
            'id' => $ticket->id,
            'code' => $ticket->code,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'status_label' => SupportTicketStatus::label($ticket->status),
            'rating' => $ticket->rating,
            'creator_type' => $ticket->creator_type,
            'creator' => $creator ? [
                'id' => $creator->id,
                'name' => $creatorName,
                'phone' => $creator->phone ?? null,
            ] : null,
            'last_replied_admin' => $ticket->lastRepliedAdmin
                ? [
                    'id' => $ticket->lastRepliedAdmin->id,
                    'full_name' => $ticket->lastRepliedAdmin->full_name,
                ]
                : null,
            'operators' => $ticket->operators->map(fn ($op) => [
                'admin_id' => $op->admin_id,
                'full_name' => $op->admin?->full_name,
                'replies_count' => $op->replies_count,
            ]),
            'messages_count' => $ticket->messages_count ?? $ticket->messages()->count(),
            'last_message_at' => $ticket->last_message_at,
            'closed_at' => $ticket->closed_at,
            'created_at' => $ticket->created_at,
            'is_closed' => $ticket->isClosed(),
        ];
    }

    private function transformTicketDetail(SupportTicket $ticket): array
    {
        $summary = $this->transformTicketSummary($ticket);

        $messages = $ticket->messages->map(function ($message) {
            $sender = $message->sender;
            $senderName = null;
            if ($sender) {
                $senderName = $sender->full_name ?? $sender->name ?? $sender->phone ?? null;
            }

            return [
                'id' => $message->id,
                'body' => $message->body,
                'image_path' => $message->image_path,
                'sender_type' => $message->sender_type,
                'sender' => $sender ? [
                    'id' => $sender->id,
                    'name' => $senderName,
                    'avatar' => $sender->avatar ?? null,
                ] : null,
                'created_at' => $message->created_at,
            ];
        });

        return array_merge($summary, [
            'messages' => $messages,
        ]);
    }
}
