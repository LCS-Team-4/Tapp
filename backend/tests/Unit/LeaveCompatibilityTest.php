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

    public function testSubmitValidatorAcceptsRequestTypePayload(): void
    {
        $errors = LeaveValidator::validateSubmit([
            'request_type' => 'annual',
            'start_date' => '2026-08-15',
            'end_date' => '2026-08-17',
            'reason' => 'Vacation',
        ]);

        $this->assertSame([], $errors);
    }

    public function testStatusValidatorAcceptsDeclinedStatus(): void
    {
        $errors = LeaveValidator::validateStatusUpdate(['status' => 'declined']);

        $this->assertSame([], $errors);
    }

    public function testSubmitValidatorAcceptsStudyLeave(): void
    {
        $errors = LeaveValidator::validateSubmit([
            'leave_type' => 'stu_leave',
            'start_date' => '2026-08-20',
            'end_date' => '2026-08-22',
            'reason' => 'Exam preparation',
        ]);

        $this->assertSame([], $errors);
    }

    public function testSubmitValidatorAcceptsFamilyResponsibilityLeave(): void
    {
        $errors = LeaveValidator::validateSubmit([
            'leave_type' => 'fr_leave',
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-26',
            'reason' => 'Family emergency',
        ]);

        $this->assertSame([], $errors);
    }
}
  