<?php

namespace BitDreamIT\MikoPBX\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OriginateCallRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'from'      => 'required|string|max:20',
            'to'        => 'required|string|max:20',
            'caller_id' => 'nullable|string|max:50',
            'timeout'   => 'nullable|integer|min:5000|max:120000',
            'variables' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'from.required' => 'Source extension is required.',
            'to.required'   => 'Destination number is required.',
        ];
    }
}

class TransferCallRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'channel'   => 'required|string',
            'extension' => 'required|string|max:20',
            'type'      => 'nullable|in:blind,attended',
        ];
    }
}

class CreateCampaignRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'           => 'required|string|max:255',
            'numbers'        => 'required|array|min:1',
            'numbers.*'      => 'required|string|max:20',
            'audio_file'     => 'required|string',
            'max_channels'   => 'nullable|integer|min:1|max:10',
            'type'           => 'nullable|in:broadcast,ivr_survey,predictive',
            'ivr_options'    => 'nullable|array',
            'scheduled_at'   => 'nullable|date|after:now',
        ];
    }
}

class BuildIVRRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'                    => 'required|string|max:100',
            'greeting'                => 'required|string',
            'timeout'                 => 'nullable|integer|min:5|max:60',
            'max_invalid'             => 'nullable|integer|min:1|max:5',
            'keypresses'              => 'required|array',
            'keypresses.*.action'     => 'required|in:transfer,queue,playback,hangup,voicemail,ivr',
            'keypresses.*.value'      => 'nullable|string',
            'timeout_action'          => 'nullable|in:repeat,hangup,transfer,voicemail',
            'invalid_action'          => 'nullable|in:repeat,hangup',
            'push_to_mikopbx'         => 'nullable|boolean',
        ];
    }
}

class AnalyticsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date_from'  => 'nullable|date',
            'date_to'    => 'nullable|date|after_or_equal:date_from',
            'extension'  => 'nullable|string|max:20',
            'sla_seconds'=> 'nullable|integer|min:5|max:120',
        ];
    }
}

class BlacklistRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'number'     => 'required|string|max:20',
            'reason'     => 'nullable|string|max:255',
            'expires_at' => 'nullable|date|after:now',
        ];
    }
}
