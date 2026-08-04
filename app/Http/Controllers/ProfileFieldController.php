<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MUserInfoCategory;
use App\Models\MUserInfoField;

class ProfileFieldController extends Controller
{
    /** GET /profile-fields — list all categories with fields */
    public function index()
    {
        $categories = MUserInfoCategory::with(['fields' => function ($q) {
            $q->orderBy('sortorder');
        }])->orderBy('sortorder')->get();

        return $this->success($categories);
    }

    /** GET /profile-fields/signup — fields visible on registration form */
    public function signupFields()
    {
        $fields = MUserInfoField::where('signup', true)
            ->where('visible', true)
            ->orderBy('sortorder')
            ->get();

        return $this->success($fields);
    }

    /** POST /profile-fields/categories — create a category */
    public function createCategory(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'sortorder' => 'sometimes|integer',
        ]);

        $category = MUserInfoCategory::create($data);
        return $this->success($category, 201);
    }

    /** POST /profile-fields/categories/:id/fields — create a field in a category */
    public function createField(Request $request, string $categoryId)
    {
        $data = $request->validate([
            'shortname' => 'required|string|max:100|unique:m_user_info_field,shortname',
            'name' => 'required|string|max:200',
            'datatype' => 'required|string|in:text,textarea,checkbox,menu,date',
            'description' => 'nullable|string',
            'defaultdata' => 'nullable|string',
            'param1' => 'nullable|string|',
            'sortorder' => 'sometimes|integer',
            'required' => 'sometimes|boolean',
            'locked' => 'sometimes|boolean',
            'visible' => 'sometimes|boolean',
            'signup' => 'sometimes|boolean',
        ]);

        $data['category_id'] = $categoryId;
        $field = MUserInfoField::create($data);

        return $this->success($field, 201);
    }

    /** PATCH /profile-fields/fields/:id — update a field */
    public function updateField(Request $request, string $fieldId)
    {
        $field = MUserInfoField::findOrFail($fieldId);

        $data = $request->validate([
            'name' => 'sometimes|string|max:200',
            'datatype' => 'sometimes|string|in:text,textarea,checkbox,menu,date',
            'description' => 'nullable|string',
            'defaultdata' => 'nullable|string',
            'param1' => 'nullable|string',
            'sortorder' => 'sometimes|integer',
            'required' => 'sometimes|boolean',
            'locked' => 'sometimes|boolean',
            'visible' => 'sometimes|boolean',
            'signup' => 'sometimes|boolean',
        ]);

        $field->update($data);
        return $this->success($field);
    }

    /** DELETE /profile-fields/fields/:id — delete a field */
    public function deleteField(string $fieldId)
    {
        MUserInfoField::findOrFail($fieldId)->delete();
        return $this->success(null);
    }

    /** DELETE /profile-fields/categories/:id — delete a category */
    public function deleteCategory(string $categoryId)
    {
        MUserInfoCategory::findOrFail($categoryId)->delete();
        return $this->success(null);
    }
}
