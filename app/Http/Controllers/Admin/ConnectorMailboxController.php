<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateConnectorMailboxesRequest;
use App\Models\Connector;
use App\Models\ConnectorUserMailbox;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Maps local users to the mailbox address a Connector should sync their
 * calendar with — one admin-configured Connector (a single app
 * registration/service account) can then sync many users' mailboxes.
 * Same "matrix form, bulk save" shape as EntityVisibilityController.
 */
class ConnectorMailboxController extends Controller
{
    public function edit(Connector $connector): View
    {
        $users = User::orderBy('name')->get();
        $currentMailboxes = ConnectorUserMailbox::where('connector_id', $connector->id)->pluck('mailbox_email', 'user_id');

        return view('admin.connectors.mailboxes', [
            'connector' => $connector,
            'users' => $users,
            'currentMailboxes' => $currentMailboxes,
        ]);
    }

    public function update(UpdateConnectorMailboxesRequest $request, Connector $connector): RedirectResponse
    {
        foreach ($request->input('mailboxes', []) as $userId => $mailboxEmail) {
            $mailboxEmail = trim((string) $mailboxEmail);

            if ($mailboxEmail === '') {
                ConnectorUserMailbox::where('connector_id', $connector->id)->where('user_id', $userId)->delete();

                continue;
            }

            ConnectorUserMailbox::updateOrCreate(
                ['connector_id' => $connector->id, 'user_id' => $userId],
                ['mailbox_email' => $mailboxEmail]
            );
        }

        return redirect()->route('admin.connectors.mailboxes.edit', $connector)->with('status', 'connector-mailboxes-updated');
    }
}
