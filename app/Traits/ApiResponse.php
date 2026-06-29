<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

trait ApiResponse
{
    protected function success(
        mixed $data = null,
        string $message = 'Success.',
        int $status = 200
    ): JsonResponse {

        if ($data instanceof ResourceCollection) {

            $response = $data->response()->getData(true);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => $response['data'],
                'links'   => $response['links'] ?? null,
                'meta'    => $response['meta'] ?? null,
            ], $status);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    protected function error(
        string $message,
        int $status = 400,
        mixed $errors = null
    ): JsonResponse {

        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }
}
