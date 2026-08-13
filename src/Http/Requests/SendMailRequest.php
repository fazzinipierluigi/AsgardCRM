<?php

namespace Fazzinipierluigi\AsgardCRM\Http\Requests;

use Fazzinipierluigi\AsgardCRM\Models\MailAccount;
use Fazzinipierluigi\AsgardCRM\Models\MailSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ownership of mail_account_id is checked here, in authorize() — same
 * "authorization runs before rules()" reasoning as
 * UpdateMailAccountRequest, since there's no route-bound model to
 * check against on a bare POST /mail/send.
 */
class SendMailRequest extends FormRequest
{
    public function authorize(): bool
    {
        $accountId = $this->input('mail_account_id');

        if (! is_numeric($accountId)) {
            return false;
        }

        $account = MailAccount::find($accountId);

        return $account !== null && $account->user_id === $this->user()?->id;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mail_account_id' => ['required', Rule::exists(MailAccount::class, 'id')],
            'to' => ['required', 'array', 'min:1'],
            'to.*' => ['email'],
            'cc' => ['nullable', 'array'],
            'cc.*' => ['email'],
            'bcc' => ['nullable', 'array'],
            'bcc.*' => ['email'],
            'subject' => ['required', 'string', 'max:998'],
            'body_html' => ['required', 'string'],
            'in_reply_to' => ['nullable', 'string', 'max:998'],
            'references' => ['nullable', 'string', 'max:998'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:'.(MailSetting::current()->max_attachment_size_kb)],
        ];
    }
}
