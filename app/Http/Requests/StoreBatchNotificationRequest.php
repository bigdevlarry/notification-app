<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBatchNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notifications'                    => ['required', 'array', 'min:1', 'max:1000'],
            'notifications.*.recipient_id'       => ['required', 'integer', 'exists:users,id'],
            'notifications.*.channel'            => ['required', 'string', 'in:mail,sms,push'],
            'notifications.*.content'            => ['required', 'string', 'min:1', 'max:1000'],
            'notifications.*.priority'           => ['required', 'string', 'in:high,normal,low'],
            'notifications.*.recipient_address'  => ['required_if:notifications.*.channel,sms,push', 'nullable', 'string', 'max:20'],
        ];
    }
}
