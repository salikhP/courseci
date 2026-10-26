<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class OpenApiDocumentationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_exported_contract_matches_runtime_documentation(): void
    {
        Gate::define('viewApiDocs', fn (?User $user): bool => true);
        $exportedContract = json_decode(
            (string) file_get_contents(base_path('docs/openapi.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->getJson('/docs/api.json')
            ->assertOk()
            ->assertExactJson($exportedContract);
    }
}
