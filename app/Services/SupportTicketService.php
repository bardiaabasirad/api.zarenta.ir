<?php

namespace App\Services;

use App\Constants\SupportTicketStatus;
use App\Models\Admin;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\SupportTicketOperator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use LogicException;

class SupportTicketService
{
    public function create(Model $creator, string $subject, string $body, ?UploadedFile $image = null): SupportTicket
    {
        return DB::transaction(function () use ($creator, $subject, $body, $image) {
            $now = now();

            $ticket = SupportTicket::query()->create([
                'code' => $this->generateCode(),
                'subject' => $subject,
                'status' => SupportTicketStatus::OPEN,
                'creator_type' => $creator->getMorphClass(),
                'creator_id' => $creator->getKey(),
                'last_message_at' => $now,
            ]);

            $this->storeMessage($ticket, $creator, $body, $image);

            return $ticket->fresh(['messages', 'lastRepliedAdmin:id,full_name,avatar', 'operators.admin:id,full_name,avatar']);
        });
    }

    public function reply(SupportTicket $ticket, Model $sender, string $body, ?UploadedFile $image = null): SupportTicket
    {
        if ($ticket->isClosed()) {
            throw new LogicException('این تیکت بسته شده و امکان ارسال پیام وجود ندارد.');
        }

        return DB::transaction(function () use ($ticket, $sender, $body, $image) {
            $message = $this->storeMessage($ticket, $sender, $body, $image);
            $now = $message->created_at ?? now();

            $updates = ['last_message_at' => $now];

            if ($sender instanceof Admin) {
                $updates['status'] = SupportTicketStatus::ANSWERED;
                $updates['last_replied_admin_id'] = $sender->id;
                $this->touchOperator($ticket, $sender, $now);
            } else {
                $updates['status'] = SupportTicketStatus::OPEN;
            }

            $ticket->update($updates);

            return $ticket->fresh([
                'messages.sender',
                'lastRepliedAdmin:id,full_name,avatar',
                'operators.admin:id,full_name,avatar',
                'creator',
            ]);
        });
    }

    public function closeByCreator(SupportTicket $ticket, Model $creator, ?int $rating = null): SupportTicket
    {
        $this->assertCreator($ticket, $creator);

        if ($ticket->isClosed()) {
            throw new LogicException('این تیکت قبلاً بسته شده است.');
        }

        if ($rating !== null && ($rating < 1 || $rating > 5)) {
            throw new LogicException('امتیاز باید بین ۱ تا ۵ باشد.');
        }

        $ticket->update([
            'status' => SupportTicketStatus::CLOSED,
            'closed_at' => now(),
            'closed_by_type' => $creator->getMorphClass(),
            'closed_by_id' => $creator->getKey(),
            'rating' => $rating,
            'rated_at' => $rating !== null ? now() : null,
        ]);

        return $ticket->fresh([
            'messages.sender',
            'lastRepliedAdmin:id,full_name,avatar',
            'operators.admin:id,full_name,avatar',
        ]);
    }

    public function autoCloseStale(int $days = 7): int
    {
        $threshold = now()->subDays($days);
        $count = 0;

        SupportTicket::query()
            ->whereIn('status', SupportTicketStatus::openStatuses())
            ->where(function ($q) use ($threshold) {
                $q->where('last_message_at', '<', $threshold)
                    ->orWhere(function ($q2) use ($threshold) {
                        $q2->whereNull('last_message_at')
                            ->where('created_at', '<', $threshold);
                    });
            })
            ->orderBy('id')
            ->chunkById(100, function ($tickets) use (&$count) {
                foreach ($tickets as $ticket) {
                    $ticket->update([
                        'status' => SupportTicketStatus::AUTO_CLOSED,
                        'closed_at' => now(),
                        'closed_by_type' => 'system',
                        'closed_by_id' => null,
                    ]);
                    $count++;
                }
            });

        return $count;
    }

    public function assertCreator(SupportTicket $ticket, Model $creator): void
    {
        if (
            $ticket->creator_type !== $creator->getMorphClass()
            || (int) $ticket->creator_id !== (int) $creator->getKey()
        ) {
            abort(403, 'دسترسی به این تیکت ندارید.');
        }
    }

    public function compressAndStoreImage(UploadedFile $file): string
    {
        $directory = 'support-tickets/' . now()->format('Y/m');
        $filename = Str::uuid()->toString() . '.webp';
        $path = $directory . '/' . $filename;

        $image = Image::read($file->getRealPath());
        $image->scaleDown(1600, 1600);

        $encoded = $image->toWebp(quality: 78);
        Storage::put($path, (string) $encoded);

        return $path;
    }

    private function storeMessage(
        SupportTicket $ticket,
        Model $sender,
        string $body,
        ?UploadedFile $image = null
    ): SupportTicketMessage {
        $imagePath = null;
        if ($image) {
            $imagePath = $this->compressAndStoreImage($image);
        }

        return SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'body' => $body,
            'image_path' => $imagePath,
            'sender_type' => $sender->getMorphClass(),
            'sender_id' => $sender->getKey(),
        ]);
    }

    private function touchOperator(SupportTicket $ticket, Admin $admin, $now): void
    {
        $operator = SupportTicketOperator::query()->firstOrNew([
            'support_ticket_id' => $ticket->id,
            'admin_id' => $admin->id,
        ]);

        if (!$operator->exists) {
            $operator->first_replied_at = $now;
            $operator->replies_count = 0;
        }

        $operator->last_replied_at = $now;
        $operator->replies_count = ((int) $operator->replies_count) + 1;
        $operator->save();
    }

    private function generateCode(): string
    {
        do {
            $code = 'ST-' . strtoupper(Str::random(8));
        } while (SupportTicket::query()->where('code', $code)->exists());

        return $code;
    }
}
