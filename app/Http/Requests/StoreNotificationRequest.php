<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient_id' => ['required', 'integer'],
            'channel' => ['required', 'string', 'in:mail,sms,push'],
            'content' => ['required', 'string', 'min:1', 'max:1000'],
            'priority' => ['required', 'string', 'in:high,normal,low'],
            'recipient_address' => ['required_if:channel,sms,push', 'nullable', 'string', 'max:20'],
        ];
    }
}
