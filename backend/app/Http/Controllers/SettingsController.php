<?php

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\SettingsRepository;

class SettingsController
{
    public function __construct(private readonly SettingsRepository $repository = new SettingsRepository())
    {
    }

    // GET /api/admin/settings — returns the current system settings.
    public function get(Request $request): Response
    {
        $settings = $this->repository->get();

        return Response::json($this->shape($settings));
    }

    // PUT /api/admin/settings — updates system settings (admin only).
    public function update(Request $request): Response
    {
        $user = $request->user();
        if ($user === null || $user['role'] !== 'admin') {
            return Response::error('Forbidden', 403);
        }

        $payload = $request->input() ?? [];

        // Validate working hours format (HH:MM or HH:MM:SS)
        foreach (['working_hours_start', 'working_hours_end'] as $field) {
            if (isset($payload[$field]) && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string) $payload[$field])) {
                return Response::error("Invalid {$field} format. Use HH:MM.", 400);
            }
        }

        // Validate late threshold is a positive integer
        if (isset($payload['late_threshold_minutes'])) {
            $threshold = (int) $payload['late_threshold_minutes'];
            if ($threshold < 0 || $threshold > 1440) {
                return Response::error('late_threshold_minutes must be between 0 and 1440', 400);
            }
            $payload['late_threshold_minutes'] = $threshold;
        }

        // Validate company name is not empty
        if (isset($payload['company_name']) && trim((string) $payload['company_name']) === '') {
            return Response::error('company_name cannot be empty', 400);
        }

        $settings = $this->repository->update($payload);

        return Response::json($this->shape($settings));
    }



    // Shapes the raw DB row into the API response format.
    private function shape(array $settings): array
    {
        return [
            'company_name' => $settings['company_name'] ?? 'TAPP Botanical Co.',
            'working_hours_start' => substr((string) ($settings['working_hours_start'] ?? '08:00:00'), 0, 5),
            'working_hours_end' => substr((string) ($settings['working_hours_end'] ?? '17:00:00'), 0, 5),
            'late_threshold_minutes' => (int) ($settings['late_threshold_minutes'] ?? 10),
            'qr_clock_in_enabled' => (bool) ($settings['qr_clock_in_enabled'] ?? true),
            'google_sheets_sync_enabled' => (bool) ($settings['google_sheets_sync_enabled'] ?? false),
        ];
    }
}