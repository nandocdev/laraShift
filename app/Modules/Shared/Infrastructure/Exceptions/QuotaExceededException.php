<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QuotaExceededException extends Exception
{
    public function __construct(
        public readonly string $metric,
        string $message = '',
        int $code = 429
    ) {
        $defaultMessage = __('You have exceeded your quota limit for :metric. Please upgrade your plan.', ['metric' => $metric]);
        parent::__construct($message ?: $defaultMessage, $code);
    }

    public function render(Request $request): Response|JsonResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $this->getMessage(),
                'code' => 'quota_exceeded',
                'metric' => $this->metric,
            ], $this->getCode());
        }

        return abort($this->getCode(), $this->getMessage());
    }
}
