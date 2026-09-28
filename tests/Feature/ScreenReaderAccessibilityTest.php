<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AuthenticationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ScreenReaderAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_authenticated_layouts_expose_a_skip_link_and_main_landmark(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('href="#main-content"', false)
            ->assertSee('Skip to main content')
            ->assertSee('id="main-content"', false);

        $user = User::factory()->warehouseStaff()->create();

        $this->actingAs($user, AuthenticationContext::WEB_GUARD)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="#main-content"', false)
            ->assertSee('id="main-content"', false)
            ->assertSee('aria-controls="primary-navigation"', false)
            ->assertSee(':inert="!sidebarOpen"', false)
            ->assertSee('aria-label="Main navigation"', false);
    }

    public function test_shared_form_and_dialog_components_have_accessible_names_and_error_contracts(): void
    {
        view()->share('errors', new ViewErrorBag);
        $field = Blade::render('<x-ui.field name="password" label="Password" type="password" required hint="Use your account password." />');

        $this->assertStringContainsString('for="password"', $field);
        $this->assertStringContainsString('id="password"', $field);
        $this->assertStringContainsString('(required)', $field);
        $this->assertStringContainsString('aria-describedby="password-hint"', $field);
        $this->assertStringContainsString('aria-controls="password"', $field);
        $this->assertStringNotContainsString('tabindex="-1"', $field);

        $modal = Blade::render('<x-ui.modal name="evidence" title="Evidence dialog"><button type="button">Continue</button></x-ui.modal>');

        $this->assertStringContainsString('role="dialog"', $modal);
        $this->assertStringContainsString('aria-modal="true"', $modal);
        $this->assertStringContainsString('aria-labelledby="evidence-title"', $modal);
        $this->assertStringContainsString('id="evidence-title"', $modal);

        $navigation = Blade::render('<x-ui.nav-dropdown id="records" title="Records"><a href="/records">Open records</a></x-ui.nav-dropdown>');
        $this->assertStringContainsString('aria-controls="nav-dropdown-records"', $navigation);
        $this->assertStringContainsString('id="nav-dropdown-records"', $navigation);
    }

    public function test_otp_scanner_notifications_and_security_prompt_expose_concise_live_feedback(): void
    {
        $otp = Blade::render('<div x-data="himsOtpVerification({ length: 6 })"><x-auth.otp-input id="test-otp" /></div>');

        $this->assertStringContainsString('role="group"', $otp);
        $this->assertStringContainsString('aria-label="Verification code"', $otp);
        $this->assertStringContainsString("'Digit ' + (index + 1) + ' of ' + length", $otp);
        $this->assertStringContainsString('aria-live="assertive"', $otp);

        $scanner = Blade::render('<x-ui.camera-scanner id="test-scanner" target-input-id="scan-code" />');

        $this->assertStringContainsString('aria-labelledby="test-scanner-title"', $scanner);
        $this->assertStringContainsString('aria-live="polite"', $scanner);
        $this->assertStringContainsString('Stop camera and close scanner', $scanner);
        $this->assertStringContainsString('Identifier to look up', $scanner);

        $toasts = file_get_contents(resource_path('views/layouts/partials/toast-notifications.blade.php'));
        $devicePrompt = file_get_contents(resource_path('views/layouts/partials/device-approval-modal.blade.php'));

        $this->assertIsString($toasts);
        $this->assertStringContainsString('aria-live="polite"', $toasts);
        $this->assertStringContainsString("['error', 'danger'].includes(toast.type) ? 'alert' : 'status'", $toasts);
        $this->assertSame(1, substr_count($toasts, 'aria-live='));

        $this->assertIsString($devicePrompt);
        $this->assertStringContainsString('aria-labelledby="device-approval-title"', $devicePrompt);
        $this->assertStringContainsString('aria-describedby="device-approval-description"', $devicePrompt);
        $this->assertStringContainsString('No, this is not me', $devicePrompt);
        $this->assertStringContainsString('Yes, approve sign-in once', $devicePrompt);
    }

    public function test_frontend_runtime_traps_modal_focus_restores_focus_and_announces_scan_results(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));
        $scanner = file_get_contents(resource_path('js/scanner.js'));

        $this->assertIsString($app);
        $this->assertStringContainsString('const startAccessibleDialogs = () =>', $app);
        $this->assertStringContainsString("event.key !== 'Tab'", $app);
        $this->assertStringContainsString('state.returnFocus.focus()', $app);
        $this->assertStringContainsString('focusFirstInvalidField()', $app);

        $this->assertIsString($scanner);
        $this->assertStringContainsString('previouslyFocusedElement', $scanner);
        $this->assertStringContainsString('returnFocus.focus()', $scanner);
        $this->assertStringContainsString('Code accepted:', $scanner);
    }

    public function test_custom_controls_and_logistics_dialogs_support_standard_keyboard_actions(): void
    {
        $dashboard = file_get_contents(resource_path('views/dashboard.blade.php'));
        $itemModal = file_get_contents(resource_path('views/inventory/items/partials/create_modal.blade.php'));

        $this->assertIsString($dashboard);
        $this->assertStringContainsString('role="button"', $dashboard);
        $this->assertStringContainsString('x-on:keydown.space.prevent="selectItem(item.item_id)"', $dashboard);

        $this->assertIsString($itemModal);
        $this->assertStringContainsString('@keydown.enter.stop.prevent="clear()"', $itemModal);
        $this->assertStringContainsString('@keydown.space.stop.prevent="clear()"', $itemModal);
        $this->assertStringContainsString('aria-label="Clear selected location"', $itemModal);

        foreach ([
            'inventory/logistics/documents.blade.php',
            'inventory/logistics/iar_index.blade.php',
            'inventory/logistics/iar_show.blade.php',
            'inventory/logistics/shipments.blade.php',
        ] as $view) {
            $markup = file_get_contents(resource_path('views/'.$view));

            $this->assertIsString($markup);
            $this->assertStringContainsString('keydown.escape.window', $markup);
        }

        $tabs = file_get_contents(resource_path('views/inventory/warehouse_tasks/show.blade.php'));
        $dropdown = file_get_contents(resource_path('views/components/dropdown.blade.php'));
        $navDropdown = file_get_contents(resource_path('views/components/ui/nav-dropdown.blade.php'));

        $this->assertIsString($tabs);
        $this->assertStringContainsString('keydown.arrow-right.prevent', $tabs);
        $this->assertStringContainsString('keydown.arrow-left.prevent', $tabs);
        $this->assertIsString($dropdown);
        $this->assertStringContainsString('keydown.escape.stop', $dropdown);
        $this->assertStringContainsString('$refs.trigger.querySelector', $dropdown);
        $this->assertIsString($navDropdown);
        $this->assertStringContainsString('keydown.escape.stop', $navDropdown);
        $this->assertStringContainsString('$refs.trigger.focus()', $navDropdown);
    }
}
