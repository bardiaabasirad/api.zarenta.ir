<?php

namespace App\Http\Controllers\api\v1\Concerns;

use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;

trait HandlesClientSupportTickets
{
    abstract protected function ticketService(): SupportTicketService;

    public function index(Request $request): JsonResponse
    {
        $creator = $request->user();

        $items = SupportTicket::query()
            ->with([
                'lastRepliedAdmin:id,full_name,avatar',
                'operators.admin:id,full_name,avatar',
            ])
            ->where('creator_type', $creator->getMorphClass())
            ->where('creator_id', $creator->getKey())
            ->when($request->filled('status') && $request->input('status') !== 'all', function ($q) use ($request) {
                $q->where('status', $request->input('status'));
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($request->input('count') ?? 20);

        return response()->json(['data' => $items]);
    }

    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->ticketService()->assertCreator($ticket, $request->user());

        $ticket->load([
            'messages.sender',
            'lastRepliedAdmin:id,full_name,avatar',
            'operators.admin:id,full_name,avatar',
        ]);

        return response()->json(['data' => $this->transformTicket($ticket)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => 'required|string|min:3|max:255',
            'body' => 'required|string|min:3|max:5000',
            'image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
        ], [
            'subject.required' => 'موضوع تیکت الزامی است.',
            'body.required' => 'متن پیام الزامی است.',
            'image.max' => 'حجم تصویر نباید بیشتر از ۲ مگابایت باشد.',
            'image.mimes' => 'فرمت تصویر باید jpg، png یا webp باشد.',
        ]);

        $ticket = $this->ticketService()->create(
            creator: $request->user(),
            subject: $data['subject'],
            body: $data['body'],
            image: $request->file('image'),
        );

        return response()->json([
            'message' => 'تیکت با موفقیت ثبت شد.',
            'data' => $this->transformTicket($ticket),
        ], 201);
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->ticketService()->assertCreator($ticket, $request->user());

        $data = $request->validate([
            'body' => 'required|string|min:1|max:5000',
            'image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
        ], [
            'body.required' => 'متن پیام الزامی است.',
            'image.max' => 'حجم تصویر نباید بیشتر از ۲ مگابایت باشد.',
            'image.mimes' => 'فرمت تصویر باید jpg، png یا webp باشد.',
        ]);

        try {
            $updated = $this->ticketService()->reply(
                ticket: $ticket,
                sender: $request->user(),
                body: $data['body'],
                image: $request->file('image'),
            );
        } catch (LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'پیام ارسال شد.',
            'data' => $this->transformTicket($updated),
        ]);
    }

    public function close(Request $request, SupportTicket $ticket): JsonResponse
    {
        $data = $request->validate([
            'rating' => 'nullable|integer|min:1|max:5',
        ], [
            'rating.min' => 'امتیاز باید حداقل ۱ باشد.',
            'rating.max' => 'امتیاز نباید بیشتر از ۵ باشد.',
        ]);

        try {
            $updated = $this->ticketService()->closeByCreator(
                ticket: $ticket,
                creator: $request->user(),
                rating: array_key_exists('rating', $data) ? ($data['rating'] !== null ? (int) $data['rating'] : null) : null,
            );
        } catch (LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'تیکت بسته شد.',
            'data' => $this->transformTicket($updated),
        ]);
    }

    protected function transformTicket(SupportTicket $ticket): array
    {
        $ticket->loadMissing([
            'messages.sender',
            'lastRepliedAdmin:id,full_name,avatar',
            'operators.admin:id,full_name,avatar',
        ]);

        $messages = $ticket->messages->map(function ($message) {
            $sender = $message->sender;
            $senderName = null;
            $senderRole = $message->sender_type;

            if ($sender) {
                $senderName = $sender->full_name
                    ?? $sender->name
                    ?? $sender->phone
                    ?? null;
            }

            return [
                'id' => $message->id,
                'body' => $message->body,
                'image_path' => $message->image_path,
                'sender_type' => $message->sender_type,
                'sender_role' => $senderRole,
                'sender' => $sender ? [
                    'id' => $sender->id,
                    'name' => $senderName,
                    'avatar' => $sender->avatar ?? null,
                ] : null,
                'created_at' => $message->created_at,
            ];
        });

        $operators = $ticket->operators->map(function ($op) {
            return [
                'admin_id' => $op->admin_id,
                'full_name' => $op->admin?->full_name,
                'avatar' => $op->admin?->avatar,
                'replies_count' => $op->replies_count,
                'first_replied_at' => $op->first_replied_at,
                'last_replied_at' => $op->last_replied_at,
            ];
        });

        return [
            'id' => $ticket->id,
            'code' => $ticket->code,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'rating' => $ticket->rating,
            'rated_at' => $ticket->rated_at,
            'closed_at' => $ticket->closed_at,
            'last_message_at' => $ticket->last_message_at,
            'created_at' => $ticket->created_at,
            'updated_at' => $ticket->updated_at,
            'last_replied_admin' => $ticket->lastRepliedAdmin
                ? [
                    'id' => $ticket->lastRepliedAdmin->id,
                    'full_name' => $ticket->lastRepliedAdmin->full_name,
                    'avatar' => $ticket->lastRepliedAdmin->avatar,
                ]
                : null,
            'operators' => $operators,
            'messages' => $messages,
            'is_closed' => $ticket->isClosed(),
        ];
    }
}
