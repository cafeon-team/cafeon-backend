<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer', 'exists:post_categories,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'summary' => ['nullable', 'string', 'max:500'],
            'content' => ['sometimes', 'string'],
            'thumbnail_url' => ['nullable', 'url', 'max:500'],
            'status' => ['sometimes', Rule::in(['DRAFT', 'PUBLISHED', 'SCHEDULED'])],
            'scheduled_at' => ['nullable', 'date', 'after:now', Rule::requiredIf($this->input('status') === 'SCHEDULED')],
            'tag_ids' => ['sometimes', 'array', 'max:20'],
            'tag_ids.*' => ['integer', 'distinct', 'exists:tags,id'],
            'images' => ['sometimes', 'array', 'max:20'],
            'images.*.image_url' => ['required', 'url', 'max:500'],
            'images.*.alt_text' => ['nullable', 'string', 'max:255'],
            'images.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
