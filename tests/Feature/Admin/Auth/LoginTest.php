<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Illuminate\Http\Response;

it('fails when admin does not exist', function () {
    $response = $this->postJson('/api/v1/admin/auth/login', [
        'phone' => '09120000000',
        'password' => 'wrongpass',
        'device_name' => 'PestTest',
    ]);

    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonStructure(['errors' => ['phone']]);
});

//it('fails when password is incorrect', function () {
//    Admin::factory()->create([
//        'phone' => '09120000000',
//        'password' => bcrypt('secret'),
//        'is_active' => true,
//    ]);
//
//    $response = $this->postJson('/api/v1/admin/auth/login', [
//        'phone' => '09120000000',
//        'password' => 'wrongpass',
//        'device_name' => 'PestTest',
//    ]);
//
//    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
//        ->assertJsonStructure(['errors' => ['phone']]);
//});
//
//it('fails when account is disabled', function () {
//    Admin::factory()->create([
//        'phone' => '09120000000',
//        'password' => bcrypt('secret'),
//        'is_active' => false,
//    ]);
//
//    $response = $this->postJson('/api/v1/admin/auth/login', [
//        'phone' => '09120000000',
//        'password' => 'secret',
//        'device_name' => 'PestTest',
//    ]);
//
//    $response->assertStatus(Response::HTTP_FORBIDDEN)
//        ->assertJsonPath('message', trans('messages.your_account_is_disabled'));
//});
//
//it('logs in successfully and returns token', function () {
//    Admin::factory()->create([
//        'phone' => '09120000000',
//        'password' => bcrypt('secret'),
//        'is_active' => true,
//    ]);
//
//    $response = $this->postJson('/api/v1/admin/auth/login', [
//        'phone' => '09120000000',
//        'password' => 'secret',
//        'device_name' => 'PestTest',
//    ]);
//
//    $response->assertStatus(Response::HTTP_CREATED)
//        ->assertJsonStructure(['token', 'admin', 'permissions']);
//});
//
//it('logs out successfully', function () {
//    $admin = Admin::factory()->create([
//        'is_active' => true,
//    ]);
//
//    Sanctum::actingAs($admin, [], 'admin-api');
//
//    $response = $this->postJson('/api/v1/admin/auth/logout');
//
//    $response->assertStatus(Response::HTTP_NO_CONTENT);
//});
