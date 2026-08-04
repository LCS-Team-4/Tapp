<?php

namespace App\Http;

class Response
{
    private array $body;
    private int $status;

    private function __construct(array $body, int $status)
    {
        $this->body = $body;
        $this->status = $status;
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(['data' => $data], $status);
    }

    public static function error(string $message, int $status = 400, ?string $code = null): self
    {
        $error = ['message' => $message];

        if ($code !== null) {
            $error['code'] = $code;
        }

        return new self(['error' => $error], $status);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json');
        echo json_encode($this->body);
    }
}
