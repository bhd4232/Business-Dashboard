<?php

namespace Tests\Feature;

use App\Models\MobileCrashReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileCrashReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unauthenticated_request_can_upload_a_crash_report(): void
    {
        // No actingAs() -- a crash can happen before login, so the Android
        // app has no session/CSRF token when it uploads this. The route
        // itself must accept it anonymously.
        $this->postJson(route('mobile-crash-reports.store'), [
            'exception_class' => 'java.lang.IllegalStateException',
            'message' => 'Default FirebaseApp is not initialized in this process.',
            'stack_trace' => "java.lang.IllegalStateException: ...\n\tat com.google.firebase.FirebaseApp.getInstance(FirebaseApp.java:1)",
            'app_version_name' => '1.0',
            'app_version_code' => 1,
            'os_version' => '14',
            'device_manufacturer' => 'Xiaomi',
            'device_model' => 'Redmi Note 12',
            'occurred_at' => '2026-08-20T01:13:00Z',
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'received');

        $report = MobileCrashReport::query()->sole();

        $this->assertSame('java.lang.IllegalStateException', $report->exception_class);
        $this->assertSame('Xiaomi', $report->device_manufacturer);
        $this->assertSame('Redmi Note 12', $report->device_model);
        $this->assertNotNull($report->ip_address);
    }

    public function test_exception_class_and_stack_trace_are_required(): void
    {
        $this->postJson(route('mobile-crash-reports.store'), [
            'message' => 'Something went wrong.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['exception_class', 'stack_trace']);

        $this->assertSame(0, MobileCrashReport::query()->count());
    }

    public function test_oversized_fields_are_rejected(): void
    {
        $this->postJson(route('mobile-crash-reports.store'), [
            'exception_class' => str_repeat('a', 300),
            'stack_trace' => str_repeat('a', 20001),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['exception_class', 'stack_trace']);
    }

    public function test_repeated_uploads_are_rate_limited(): void
    {
        $payload = [
            'exception_class' => 'java.lang.RuntimeException',
            'stack_trace' => 'java.lang.RuntimeException: boom',
        ];

        for ($i = 0; $i < 20; $i++) {
            $this->postJson(route('mobile-crash-reports.store'), $payload)->assertCreated();
        }

        $this->postJson(route('mobile-crash-reports.store'), $payload)
            ->assertStatus(429);
    }

    public function test_only_a_super_admin_can_view_crash_reports_in_the_admin_panel(): void
    {
        MobileCrashReport::query()->create([
            'exception_class' => 'java.lang.IllegalStateException',
            'stack_trace' => 'java.lang.IllegalStateException: boom',
        ]);

        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);

        $this->actingAs($superAdmin)
            ->get('/admin/settings/mobile-crash-reports')
            ->assertOk();

        $this->actingAs($manager)
            ->get('/admin/settings/mobile-crash-reports')
            ->assertForbidden();
    }
}
