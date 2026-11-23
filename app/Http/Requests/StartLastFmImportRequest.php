<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a user-requested Last.fm import range and optional item limit.
 */
class StartLastFmImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    /**
     * The private dashboard is the only caller of this request.
     *
     * @return bool Always true for the private application.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    /**
     * Return the accepted import range and limit rules.
     *
     * @return array<string, list<mixed>> The validation rules.
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date', 'before_or_equal:to'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'limit' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
