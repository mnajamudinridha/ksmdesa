<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The master admin panel must only answer on the apex domain; tenant
 * subdomains share the same vhost (wildcard alias) and must not reach it.
 */
class DomainRoutingTest extends TestCase
{
    public function test_admin_login_is_served_on_the_master_domain(): void
    {
        $this->get('http://ksmdesa.test/admin/login')->assertOk();
    }

    public function test_admin_dashboard_redirects_guests_to_login(): void
    {
        $this->get('http://ksmdesa.test/admin')
            ->assertRedirect('http://ksmdesa.test/admin/login');
    }

    public function test_admin_panel_is_not_reachable_from_a_tenant_subdomain(): void
    {
        $this->get('http://makmurjaya.ksmdesa.test/admin/login')->assertNotFound();
    }

    public function test_health_endpoint_answers_on_any_host(): void
    {
        $this->get('http://ksmdesa.test/up')->assertOk();
        $this->get('http://makmurjaya.ksmdesa.test/up')->assertOk();
    }
}
