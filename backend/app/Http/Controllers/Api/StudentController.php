<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        $students = Student::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('student_id', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $students,
            'meta' => [
                'total' => $students->count(),
                'search' => $search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $student = Student::create($this->validatedData($request));

        return response()->json([
            'message' => 'Data mahasiswa berhasil ditambahkan.',
            'student' => $student,
        ], 201);
    }

    public function update(Request $request, Student $student)
    {
        $student->update($this->validatedData($request, $student));

        return response()->json([
            'message' => 'Data mahasiswa berhasil diperbarui.',
            'student' => $student->refresh(),
        ]);
    }

    public function destroy(Student $student)
    {
        $student->delete();

        return response()->json(['message' => 'Data mahasiswa berhasil dihapus.']);
    }

    private function validatedData(Request $request, ?Student $student = null): array
    {
        $studentIdRule = 'unique:students,student_id';
        $emailRule = 'unique:students,email';

        if ($student) {
            $studentIdRule .= ',' . $student->id;
            $emailRule .= ',' . $student->id;
        }

        return $request->validate([
            'student_id' => ['required', 'string', 'max:50', $studentIdRule],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', $emailRule],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);
    }
}
