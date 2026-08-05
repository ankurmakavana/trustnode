<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Http\Resources\UserInvitationResource;
use App\Http\Resources\UserResource;
use App\Services\Authentication\InvitationService;

final class InvitationController extends Controller
{
    public function __construct(
        private InvitationService $invitationService
    ) {}

    /**
     * View active invitation parameters by token.
     */
    public function show(string $token): UserInvitationResource
    {
        $invitation = $this->invitationService->getInvitationByToken($token);

        return new UserInvitationResource($invitation);
    }

    /**
     * Accept invitation, claim credentials, and register user profile.
     */
    public function accept(AcceptInvitationRequest $request, string $token): UserResource
    {
        $user = $this->invitationService->acceptInvitation(
            token: $token,
            name: $request->validated('name'),
            password: $request->validated('password'),
            ipAddress: $request->ip() ?? '127.0.0.1'
        );

        return (new UserResource($user))
            ->additional([
                'meta' => [
                    'message' => 'Invitation accepted successfully.',
                ],
            ]);
    }
}
