<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Program;
use App\Models\Subject;
use App\Models\Department;

class ProgramController extends Controller
{
    public function index()
    {
        $programs = Program::with(['department', 'modes'])->latest()->get();
        return $this->success($programs);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:20|unique:programs,code',
            'name' => 'required|string|max:150',
            'department_id' => 'required|exists:departments,id',
            'duration_years' => 'required|integer|min:1',
            'description' => 'sometimes|string',
            'modes' => 'sometimes|array',
            'modes.*' => 'in:ODL,DAY,BRD',
        ]);

        $program = Program::create($data);

        if (!empty($data['modes'])) {
            foreach ($data['modes'] as $mode) {
                $program->modes()->create(['mode' => $mode]);
            }
        }

        return $this->success($program->load(['department', 'modes']), 201);
    }

    public function update(Request $request, string $id)
    {
        $program = Program::findOrFail($id);

        $data = $request->validate([
            'code' => 'sometimes|string|max:20|unique:programs,code,' . $id,
            'name' => 'sometimes|string|max:150',
            'department_id' => 'sometimes|exists:departments,id',
            'duration_years' => 'sometimes|integer|min:1',
            'description' => 'sometimes|string',
            'is_active' => 'sometimes|boolean',
            'modes' => 'sometimes|array',
            'modes.*' => 'in:ODL,DAY,BRD',
        ]);

        $program->update($data);

        if (isset($data['modes'])) {
            $program->modes()->delete();
            foreach ($data['modes'] as $mode) {
                $program->modes()->create(['mode' => $mode]);
            }
        }

        return $this->success($program->load(['department', 'modes']));
    }

    public function destroy(string $id)
    {
        $program = Program::findOrFail($id);
        $program->delete();
        return $this->success(null);
    }
}

class SubjectController extends Controller
{
    public function index()
    {
        $subjects = Subject::with(['department'])->latest()->get();
        return $this->success($subjects);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:10|unique:subjects,code',
            'name' => 'required|string|max:120',
            'department_id' => 'sometimes|exists:departments,id',
        ]);

        $subject = Subject::create($data);
        return $this->success($subject, 201);
    }

    public function update(Request $request, string $id)
    {
        $subject = Subject::findOrFail($id);

        $data = $request->validate([
            'code' => 'sometimes|string|max:10|unique:subjects,code,' . $id,
            'name' => 'sometimes|string|max:120',
            'department_id' => 'sometimes|exists:departments,id',
        ]);

        $subject->update($data);
        return $this->success($subject);
    }

    public function destroy(string $id)
    {
        $subject = Subject::findOrFail($id);
        $subject->delete();
        return $this->success(null);
    }
}

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::with(['hod', 'programs', 'subjects'])->latest()->get();
        return $this->success($departments);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:10|unique:departments,code',
            'name' => 'required|string|max:120',
            'hod_id' => 'sometimes|exists:users,id',
        ]);

        $department = Department::create($data);
        return $this->success($department, 201);
    }

    public function update(Request $request, string $id)
    {
        $department = Department::findOrFail($id);

        $data = $request->validate([
            'code' => 'sometimes|string|max:10|unique:departments,code,' . $id,
            'name' => 'sometimes|string|max:120',
            'hod_id' => 'sometimes|exists:users,id',
        ]);

        $department->update($data);
        return $this->success($department);
    }

    public function destroy(string $id)
    {
        $department = Department::findOrFail($id);
        $department->delete();
        return $this->success(null);
    }
}
