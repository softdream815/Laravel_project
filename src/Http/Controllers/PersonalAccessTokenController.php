<?php

namespace Laravel\Passport\Http\Controllers;

use Laravel\Passport\Passport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Laravel\Passport\PersonalAccessTokenResult;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;

class PersonalAccessTokenController
{
    /**
     * The validation factory implementation.
     *
     * @var ValidationFactory
     */
    protected $validation;

    /**
     * Create a controller instance.
     *
     * @param  ValidationFactory  $validation
     * @return void
     */
    public function __construct(ValidationFactory $validation)
    {
        $this->validation = $validation;
    }

    /**
     * Get all of the clients for the authenticated user.
     *
     * @param  Request  $request
     * @return Response
     */
    public function forUser(Request $request)
    {
        return $request->user()->tokens->load('client')->filter(function ($token) {
            return $token->client->personal_access_client;
        })->values();
    }

    /**
     * Create a new personal access token for the user.
     *
     * @param  Request  $request
     * @return PersonalAccessTokenResult
     */
    public function store(Request $request)
    {
        $this->validation->make($request->all(), [
            'name' => 'required|max:255',
            'scopes' => 'required|array|in:'.implode(',', Passport::scopeIds()),
        ])->validate();

        return $request->user()->createToken(
            $request->name, $request->scopes
        );
    }

    /**
     * Delete the given token.
     *
     * @param  Request  $request
     * @param  string  $tokenId
     * @return Response
     */
    public function destroy(Request $request, $tokenId)
    {
        if (is_null($token = $request->user()->tokens->find($tokenId))) {
            return new Response('', 404);
        }

        $token->revoke();
    }
}
