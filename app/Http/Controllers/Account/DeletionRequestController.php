<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Application\Account\AuthInput;
use App\Application\Account\DeletionRequestService;
use Illuminate\Http\Request;

final readonly class DeletionRequestController
{
    public function __construct(private DeletionRequestService $requests) {}

    public function show(Request $request): mixed
    {
        AuthInput::parse($request->all(), []);

        return response()->json(['request' => $this->requests->current($request->user())]);
    }

    public function store(Request $request): mixed
    {
        $result = $this->requests->change($request->user(), $request->all(), false);

        return response()->json(['request' => $result['request']], $result['created'] ? 201 : 200);
    }

    public function cancel(Request $request): mixed
    {
        $result = $this->requests->change($request->user(), $request->all(), true);

        return response()->json(['request' => $result['request']]);
    }
}
