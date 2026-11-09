<?php

use App\Constants\SupportTicketStatus;
use App\Models\Admin;
use App\Models\User;
use App\Services\SupportTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Database driver unavailable: ' . $e->getMessage());
    }

    Storage::fake();
});

it('creates a ticket for a user and stores an optional compressed image', function () {
    $user = User::factory()->create();
    $service = app(SupportTicketService::class);

    $file = UploadedFile::fake()->image('issue.jpg', 2000, 1500)->size(1500);

    $ticket = $service->create($user, 'مشکل پرداخت', 'توضیح مشکل', $file);

    expect($ticket->code)->toStartWith('ST-')
        ->and($ticket->status)->toBe(SupportTicketStatus::OPEN)
        ->and($ticket->creator_type)->toBe('user')
        ->and($ticket->messages)->toHaveCount(1)
        ->and($ticket->messages->first()->image_path)->not->toBeNull();

    Storage::assertExists($ticket->messages->first()->image_path);
});

it('tracks admin replies and closes with optional rating', function () {
    $user = User::factory()->create();
    $admin = Admin::query()->create([
        'full_name' => 'اپراتور تست',
        'phone' => '9120000000',
        'national_code' => '0010000000',
        'password' => bcrypt('password'),
        'status' => 'active',
    ]);
    $service = app(SupportTicketService::class);

    $ticket = $service->create($user, 'موضوع', 'متن اولیه');
    $updated = $service->reply($ticket, $admin, 'پاسخ پشتیبانی');

    expect($updated->status)->toBe(SupportTicketStatus::ANSWERED)
        ->and($updated->last_replied_admin_id)->toBe($admin->id)
        ->and($updated->operators)->toHaveCount(1);

    $closed = $service->closeByCreator($updated, $user, 5);

    expect($closed->status)->toBe(SupportTicketStatus::CLOSED)
        ->and($closed->rating)->toBe(5)
        ->and($closed->isClosed())->toBeTrue();
});

it('auto closes tickets idle for more than 7 days', function () {
    $user = User::factory()->create();
    $service = app(SupportTicketService::class);

    $ticket = $service->create($user, 'قدیمی', 'پیام');
    $ticket->update(['last_message_at' => now()->subDays(8)]);

    $count = $service->autoCloseStale(7);

    expect($count)->toBeGreaterThanOrEqual(1)
        ->and($ticket->fresh()->status)->toBe(SupportTicketStatus::AUTO_CLOSED);
});
