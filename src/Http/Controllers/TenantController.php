<?php

declare(strict_types=1);

namespace Quvel\Tenant\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Quvel\Tenant\Models\Tenant;
use Quvel\Tenant\Services\TenantPresetService;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantPresetService $presetService
    ) {}

    /**
     * Get available tenant presets.
     */
    public function presets(): JsonResponse
    {
        return response()->json([
            'presets' => $this->presetService->getAvailablePresets(),
        ]);
    }

    /**
     * Get form fields for a specific preset.
     */
    public function presetFields(string $preset): JsonResponse
    {
        $fields = $this->presetService->getPresetFields($preset);

        if (!$fields) {
            return response()->json(['error' => 'Preset not found'], 404);
        }

        return response()->json([
            'preset' => $preset,
            'fields' => $fields,
        ]);
    }

    /**
     * Create a new tenant using a preset.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'identifier' => 'required|string|max:255|unique:tenants,identifier',
            'preset' => ['required', 'string', Rule::in($this->presetService->getPresetNames())],
            'config' => 'array',
        ]);

        try {
            $tenant = $this->presetService->createTenantWithPreset(
                name: $validated['name'],
                identifier: $validated['identifier'],
                preset: $validated['preset'],
                config: $validated['config'] ?? []
            );

            return response()->json([
                'success' => true,
                'tenant' => [
                    'id' => $tenant->id,
                    'public_id' => $tenant->public_id,
                    'name' => $tenant->name,
                    'identifier' => $tenant->identifier,
                    'preset' => $validated['preset'],
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
    public function index(): JsonResponse
    {
        $tenants = Tenant::select(['id', 'public_id', 'name', 'identifier', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'tenants' => $tenants,
        ]);
    }
}