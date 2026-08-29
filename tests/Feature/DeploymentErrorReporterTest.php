<?php

namespace Tests\Feature;

use App\Models\DeploymentError;
use App\Models\User;
use App\Services\DeploymentErrorReporter;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DeploymentErrorReporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // DeploymentErrorAlert links to DeploymentErrorResource's "view"
        // page, which needs a current panel to generate a URL from.
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_it_persists_a_deployment_error_and_notifies_active_super_admins(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $error = app(DeploymentErrorReporter::class)->report(
            'migration',
            new RuntimeException('column "reseller_customer_id" does not exist'),
            ['command' => 'migrate --force --isolated'],
        );

        $this->assertInstanceOf(DeploymentError::class, $error);
        $this->assertDatabaseHas('deployment_errors', [
            'id' => $error->getKey(),
            'source' => 'migration',
            'message' => 'column "reseller_customer_id" does not exist',
        ]);
        $this->assertStringContainsString('RuntimeException', $error->details);
        $this->assertStringContainsString('Stack trace:', $error->details);
        $this->assertSame(['command' => 'migrate --force --isolated'], $error->context);

        $this->assertSame(1, $superAdmin->fresh()->notifications()->count());

        $notification = $superAdmin->fresh()->notifications()->first();
        $this->assertSame('deployment-error', $notification->data['kind']);
        $this->assertSame($error->getKey(), $notification->data['deployment_error_id']);
        $this->assertSame('migration', $notification->data['source']);
        $this->assertStringContainsString('migration', $notification->data['title']);
    }

    public function test_the_notification_carries_a_working_copy_action_and_a_view_action(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $error = app(DeploymentErrorReporter::class)->report('migration', new RuntimeException('boom'));

        $actions = $superAdmin->fresh()->notifications()->first()->data['actions'];
        $this->assertCount(2, $actions);

        $copyAction = $actions[0];
        $this->assertSame('Copy Error Log', $copyAction['label']);
        $this->assertStringContainsString('navigator.clipboard.writeText', $copyAction['alpineClickHandler']);
        $this->assertStringContainsString('boom', $copyAction['alpineClickHandler']);

        $viewAction = $actions[1];
        $this->assertSame('View Full Log', $viewAction['label']);
        $this->assertStringContainsString((string) $error->getKey(), $viewAction['url']);
    }

    public function test_it_never_notifies_an_inactive_super_admin_or_a_regular_user(): void
    {
        $inactiveSuperAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => false]);
        $regularUser = User::factory()->create(['role' => 'manager', 'is_active' => true]);

        app(DeploymentErrorReporter::class)->report('migration', new RuntimeException('boom'));

        $this->assertSame(0, $inactiveSuperAdmin->fresh()->notifications()->count());
        $this->assertSame(0, $regularUser->fresh()->notifications()->count());
    }

    public function test_multiple_reports_produce_one_notification_each_so_the_bell_shows_one_copy_button_per_error(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        app(DeploymentErrorReporter::class)->report('migration', new RuntimeException('first failure'));
        app(DeploymentErrorReporter::class)->report('migration', new RuntimeException('second failure'));

        $this->assertSame(2, $superAdmin->fresh()->notifications()->count());
        $this->assertDatabaseCount('deployment_errors', 2);
    }
}
