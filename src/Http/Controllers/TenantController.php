<?php

declare(strict_types=1);

namespace Quvel\Tenant\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Quvel\Tenant\Actions\Admin\GetConfigFields;
use Quvel\Tenant\Actions\Admin\GetPresetFields;
use Quvel\Tenant\Actions\Admin\GetPresets;
use Quvel\Tenant\Actions\Admin\ListTenants;
use Quvel\Tenant\Actions\Admin\StoreTenant;
use Quvel\Tenant\Actions\Admin\UpdateTenant;
use Quvel\Tenant\Models\Tenant;

class TenantController extends Controller
{

    /**
     * Get all available configuration fields.
     */
    public function configFields(GetConfigFields $action): JsonResponse
    {
        return response()->json([
            'fields' => $action(),
        ]);
    }

    /**
     * Get available tenant presets.
     */
    public function presets(GetPresets $action): JsonResponse
    {
        return response()->json([
            'presets' => $action(),
        ]);
    }

    /**
     * Get form fields for a specific preset.
     */
    public function presetFields(string $preset, GetPresetFields $action): JsonResponse
    {
        $fields = $action($preset);

        if (!$fields) {
            return response()->json(['error' => 'Preset not found'], 404);
        }

        return response()->json([
            'preset' => $preset,
            'fields' => $fields,
        ]);
    }

    /**
     * Create a new tenant.
     */
    public function store(Request $request, StoreTenant $action): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'identifier' => 'required|string|max:255|unique:tenants,identifier',
            'config' => 'array',
        ]);

        try {
            $tenant = $action(
                name: $validated['name'],
                identifier: $validated['identifier'],
                config: $validated['config'] ?? []
            );

            return response()->json([
                'success' => true,
                'tenant' => [
                    'id' => $tenant->id,
                    'public_id' => $tenant->public_id,
                    'name' => $tenant->name,
                    'identifier' => $tenant->identifier,
                ],
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Show the tenant management UI.
     */
    public function ui()
    {
        return view('tenant::admin.ui');
    }

    /**
     * List all tenants.
     */
    public function index(ListTenants $action): JsonResponse
    {
        return response()->json([
            'tenants' => $action(),
        ]);
    }

    /**
     * Update an existing tenant.
     */
    public function update(Request $request, Tenant $tenant, UpdateTenant $action): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'identifier' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'config' => 'sometimes|array',
        ]);

        try {
            $updatedTenant = $action($tenant, $validated);

            return response()->json([
                'success' => true,
                'tenant' => [
                    'id' => $updatedTenant->id,
                    'public_id' => $updatedTenant->public_id,
                    'name' => $updatedTenant->name,
                    'identifier' => $updatedTenant->identifier,
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}