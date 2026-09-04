<?php

use App\Models\MetalTrader;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Client Channels
|--------------------------------------------------------------------------
*/

// Private channels for clients
Broadcast::channel('balance-{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
}, ['guards' => ['metal-trader-api']]);

Broadcast::channel('rates-{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
}, ['guards' => ['metal-trader-api']]);

Broadcast::channel('order-{orderId}', function ($user, $orderId) {
    return \App\Models\MetalOrder::where('tracking_code', $orderId)
        ->where('created_type', (new MetalTrader())->getMorphClass())
        ->where('created_id', $user->id)
        ->exists();
}, ['guards' => ['metal-trader-api']]);

Broadcast::channel('client-order-{clientId}', function ($user, $clientId) {
    return (int) $user->id === (int) $clientId;
}, ['guards' => ['metal-trader-api']]);

Broadcast::channel('dealing-group-{groupId}', function ($metalTrader, $groupId) {
    return \App\Models\DealingGroup::where('id', $groupId)->exists();
//    return $metalTrader->dealing_group_id === $groupId;
}, ['guards' => ['metal-trader-api']]);

Broadcast::channel('metal-trader-{metalTraderId}', function ($metalTrader, $metalTraderId) {
    return (int) $metalTrader->id === (int) $metalTraderId;
}, ['guards' => ['metal-trader-api']]);

// Presence Channels for clients
Broadcast::channel('online-clients', function ($user) {
    if ($user) {
        // ثبت کاربر به عنوان آنلاین
        return [
            'id' => $user->id,
            'type' => (new MetalTrader())->getMorphClass(),
            'name' => $user->name ?? 'Unknown',
            'connected_at' => now()->toIso8601String(),
        ];
    }
    return false;
}, ['guards' => ['metal-trader-api']]);

/*
|--------------------------------------------------------------------------
| User Channels
|--------------------------------------------------------------------------
*/

// Private channels for users
Broadcast::channel('user-bullion-order-{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
}, ['guards' => ['user-api']]);

Broadcast::channel('bullion-order-{orderId}', function ($user, $orderId) {
    return \App\Models\MetalOrder::where('tracking_code', $orderId)
        ->where('created_type', (new User())->getMorphClass())
        ->where('created_id', $user->id)
        ->exists();
}, ['guards' => ['user-api']]);

Broadcast::channel('user-{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
}, ['guards' => ['user-api']]);

Broadcast::channel('user-balance-{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
}, ['guards' => ['user-api']]);

Broadcast::channel('user-rates-{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
}, ['guards' => ['user-api']]);

// Presence Channels for users
Broadcast::channel('online-users', function ($user) {
    if ($user) {
        // ثبت کاربر به عنوان آنلاین
        return [
            'id' => $user->id,
            'type' => (new User())->getMorphClass(),
            'name' => $user->name ?? 'Unknown',
            'connected_at' => now()->toIso8601String(),
        ];
    }
    return false;
}, ['guards' => ['user-api']]);

/*
|--------------------------------------------------------------------------
| Admin Channels
|--------------------------------------------------------------------------
*/

// Private channels for admins
Broadcast::channel('notifications-admin-{adminId}', function ($user, $adminId) {
    return (int) $user->id === (int) $adminId;
}, ['guards' => ['admin-api']]);
