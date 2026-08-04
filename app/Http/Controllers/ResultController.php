<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Result;
use App\Models\Course;

class ResultController extends Controller
{
    public function index(Request $request)
    {
        $query = Result::with(['student', 'course']);

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }
        if ($request->has('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        $results = $query->latest()->paginate($request->get('limit', 20));
        return $this->paginated($results);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id' => 'required|exists:users,id',
            'course_id' => 'required|exists:courses,id',
            'ca_marks' => 'sometimes|integer|min:0',
            'exam_marks' => 'sometimes|integer|min:0',
            'academic_year' => 'required|string|max:9',
            'semester' => 'required|in:I,II',
        ]);

        $ca = $data['ca_marks'] ?? 0;
        $exam = $data['exam_marks'] ?? 0;
        $total = $ca + $exam;
        $grade = $this->calculateGrade($total);

        $result = Result::create(array_merge($data, [
            'ca_marks' => $ca,
            'exam_marks' => $exam,
            'total' => $total,
            'grade' => $grade,
        ]));

        $this->audit('create_result', 'Result', $result->id, ['total' => $total, 'grade' => $grade]);

        return $this->success($result, 201);
    }

    public function update(Request $request, string $id)
    {
        $result = Result::findOrFail($id);

        $data = $request->validate([
            'ca_marks' => 'sometimes|integer|min:0',
            'exam_marks' => 'sometimes|integer|min:0',
            'academic_year' => 'sometimes|string|max:9',
            'semester' => 'sometimes|in:I,II',
        ]);

        $ca = $data['ca_marks'] ?? $result->ca_marks;
        $exam = $data['exam_marks'] ?? $result->exam_marks;
        $total = $ca + $exam;
        $grade = $this->calculateGrade($total);

        $result->update(array_merge($data, [
            'ca_marks' => $ca,
            'exam_marks' => $exam,
            'total' => $total,
            'grade' => $grade,
        ]));

        $this->audit('update_result', 'Result', $result->id, ['total' => $total, 'grade' => $grade]);

        return $this->success($result);
    }

    private function calculateGrade(int $total): string
    {
        return match(true) {
            $total >= 80 => 'A',
            $total >= 70 => 'B',
            $total >= 60 => 'C',
            $total >= 50 => 'D',
            default => 'F',
        };
    }
}
