<?php

namespace Tests\Feature;

use App\Models\IpcReportDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpcReportDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/masters/report-documents')->assertRedirect('/login');
    }

    public function test_migration_seeds_the_real_report_documents(): void
    {
        $this->assertDatabaseCount('ipc_report_documents', 4);
        $this->assertDatabaseHas('ipc_report_documents', ['report_key' => 'startup', 'doc_number' => 'FR.QAC.182.03', 'revision' => '03']);
        $this->assertDatabaseHas('ipc_report_documents', ['report_key' => 'filling', 'doc_number' => 'FR.QAC.192.02', 'revision' => '02']);
        $this->assertDatabaseHas('ipc_report_documents', ['report_key' => 'packing', 'doc_number' => 'FR.QAC.182.03', 'revision' => '03']);
        $this->assertDatabaseHas('ipc_report_documents', ['report_key' => 'filling_packing_fg', 'doc_number' => 'FR.QAC.193.00', 'revision' => '0']);
    }

    public function test_admin_can_list_report_documents(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/masters/report-documents')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('masters/report-documents/index')->has('reportDocuments', 4));
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/masters/report-documents')
            ->assertForbidden();
    }

    public function test_admin_can_update_doc_number_revision_title_and_effective_date(): void
    {
        $document = IpcReportDocument::forKey(IpcReportDocument::KEY_FILLING_PACKING_FG);

        $this->actingAs(User::factory()->admin()->create())
            ->put("/masters/report-documents/{$document->id}", [
                'doc_number' => 'FR.QAC.193.01',
                'revision' => '1',
                'title' => 'Laporan Filling & Packing (Updated)',
                'effective_date' => '2026-11-01',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ipc_report_documents', [
            'id' => $document->id,
            'doc_number' => 'FR.QAC.193.01',
            'revision' => '1',
            'title' => 'Laporan Filling & Packing (Updated)',
            'effective_date' => '2026-11-01',
        ]);
    }

    public function test_update_requires_doc_number_revision_and_title(): void
    {
        $document = IpcReportDocument::forKey(IpcReportDocument::KEY_FILLING);

        $this->actingAs(User::factory()->admin()->create())
            ->put("/masters/report-documents/{$document->id}", ['doc_number' => '', 'revision' => '', 'title' => ''])
            ->assertSessionHasErrors(['doc_number', 'revision', 'title']);

        $this->assertDatabaseHas('ipc_report_documents', ['id' => $document->id, 'doc_number' => 'FR.QAC.192.02']);
    }
}
