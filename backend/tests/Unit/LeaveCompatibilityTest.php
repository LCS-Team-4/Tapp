<?php

namespace Tests\Unit;

use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Support\LeaveValidator;
use PHPUnit\Framework\TestCase;

class LeaveCompatibilityTest extends TestCase
{
    public function testRouterSupportsPutForAdminLeaveRoutes(): void
    {
        $router = new Router();
        $router->put('/admin/leave-requests/{id}', function (Request $request): Response {
            return Response::json(['id' => (int) $request->param('id')], 200);
        });

        $request = new Request('PUT', '/admin/leave-requests/42', [], [], []);
        $response = $router->dispatch($request);

        $this->assertSame(200, $response->status());

        ob_start();
        $response->send();
        $body = ob_get_clean();

        $this->assertStringContainsString('"id":42', $body);
    }

    public function testSubmitValidatorAcceptsFrontendLeavePayload(): void
    {
        $errors = LeaveValidator::validateSubmit([
            'leave_type' => 'emergency',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-12',
            'reason' => 'Personal matters',
        ]);

        $this->assertSame([], $errors);
    }

    public function testStatusValidatorAcceptsDeclinedStatus(): void
    {
        $errors = LeaveValidator::validateStatusUpdate(['status' => 'declined']);

        $this->assertSame([], $errors);
    }
}
