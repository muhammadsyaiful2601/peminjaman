<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role = 'assistant'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_asisten_dapat_mengelola_data_mahasiswa_dan_mencarinya(): void
    {
        Sanctum::actingAs($this->staff());

        $create = $this->postJson('/api/students', [
            'student_id' => '2211082001',
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '081234567890',
        ]);

        $create->assertCreated()
            ->assertJsonPath('student.student_id', '2211082001')
            ->assertJsonPath('student.name', 'Budi Santoso');

        $student = Student::firstOrFail();

        $this->getJson('/api/students?search=2211082001')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.email', 'budi@example.com');

        $this->putJson('/api/students/' . $student->id, [
            'student_id' => '2211082001',
            'name' => 'Budi Santoso Updated',
            'email' => 'budi.updated@example.com',
            'phone' => null,
        ])->assertOk()->assertJsonPath('student.name', 'Budi Santoso Updated');

        $this->deleteJson('/api/students/' . $student->id)
            ->assertOk()
            ->assertJsonPath('message', 'Data mahasiswa berhasil dihapus.');

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }

    public function test_endpoint_mahasiswa_memerlukan_login(): void
    {
        $this->getJson('/api/students')->assertUnauthorized();
    }

    public function test_peminjam_hanya_dapat_mengelola_data_mahasiswa_dengan_role_petugas(): void
    {
        Sanctum::actingAs($this->staff('borrower'));

        $this->postJson('/api/students', [
            'student_id' => '2211082001',
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
        ])->assertForbidden();
    }
}
