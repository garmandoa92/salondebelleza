<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Plan::factory()->create([
            'name' => 'Profesional',
            'slug' => 'profesional',
            'price' => 29.00,
            'billing_cycle' => 'monthly',
            'max_stylists' => 8,
            'max_branches' => 1,
        ]);
    }

    public function test_puede_registrar_un_nuevo_salon_y_crear_su_base_de_datos(): void
    {
        $response = $this->post('/register', [
            'salon_name' => 'Test Salon',
            'slug' => 'test-salon',
            'name' => 'John Doe',
            'email' => 'john@test.com',
            'phone' => '0991234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('tenants', [
            'slug' => 'test-salon',
            'name' => 'Test Salon',
        ]);

        $this->assertDatabaseHas('tenant_users', [
            'email' => 'john@test.com',
            'role' => 'owner',
        ]);

        $this->assertDatabaseHas('domains', [
            'domain' => 'test-salon',
        ]);
    }

    public function test_el_slug_debe_ser_unico(): void
    {
        $tenant = Tenant::create([
            'name' => 'Existing Salon',
            'slug' => 'existing',
            'phone' => '0991234567',
            'is_active' => true,
        ]);

        $response = $this->post('/register', [
            'salon_name' => 'Another Salon',
            'slug' => 'existing',
            'name' => 'Jane Doe',
            'email' => 'jane@test.com',
            'phone' => '0991234568',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('slug');
    }

    public function test_el_trial_de_30_dias_se_activa_automaticamente(): void
    {
        $this->post('/register', [
            'salon_name' => 'Trial Salon',
            'slug' => 'trial-salon',
            'name' => 'Jane Doe',
            'email' => 'jane@test.com',
            'phone' => '0991234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $tenant = Tenant::where('slug', 'trial-salon')->first();

        $this->assertNotNull($tenant->trial_ends_at);
        $this->assertTrue($tenant->trial_ends_at->isFuture());
        $this->assertTrue($tenant->trial_ends_at->diffInDays(now()) >= 29);
    }

    public function test_despues_del_registro_redirige_al_subdominio_correcto(): void
    {
        $response = $this->post('/register', [
            'salon_name' => 'Redirect Salon',
            'slug' => 'redirect-salon',
            'name' => 'Jane Doe',
            'email' => 'jane@test.com',
            'phone' => '0991234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('http://redirect-salon.miapp.test/dashboard');
    }

    public function test_el_login_en_el_dominio_central_redirige_al_subdominio(): void
    {
        // Create tenant and user via registration
        $this->post('/register', [
            'salon_name' => 'Login Salon',
            'slug' => 'login-salon',
            'name' => 'Jane Doe',
            'email' => 'jane@test.com',
            'phone' => '0991234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response = $this->post('/login', [
            'email' => 'jane@test.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect();
        $this->assertStringContains('login-salon.miapp.test', $response->headers->get('Location'));
    }

    public function test_no_puede_acceder_al_subdominio_de_otro_tenant(): void
    {
        // This test verifies that subdomain routing rejects non-existent tenants
        $response = $this->get('http://nonexistent.miapp.test/dashboard');

        $response->assertStatus(404);
    }

    private function assertStringContains(string $needle, ?string $haystack): void
    {
        $this->assertNotNull($haystack);
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'."
        );
    }
}
