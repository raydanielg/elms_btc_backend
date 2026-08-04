<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MCertificateTemplate;
use App\Models\MCertificatePage;
use App\Models\MCertificateElement;
use App\Models\MCertificateIssue;
use App\Models\MAward;
use App\Services\CertificateService;

class CertificateController extends Controller
{
    public function __construct(
        private CertificateService $certService
    ) {}

    // ===== Templates (spec §7) =====

    /** GET /certificate-templates — list templates */
    public function indexTemplates(Request $request)
    {
        $query = MCertificateTemplate::withCount('issues');
        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }
        return $this->success($query->orderBy('name')->get());
    }

    /** POST /certificate-templates — create a template */
    public function createTemplate(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'type' => 'required|string|in:COMPLETION,AWARD,TRANSCRIPT,PARTICIPATION',
            'page_size' => 'sometimes|string|in:A4,A5,LETTER',
            'orientation' => 'sometimes|string|in:PORTRAIT,LANDSCAPE',
            'trigger_type' => 'sometimes|string|in:MANUAL,COURSE_COMPLETION,ACTIVITY_COMPLETION,GRADE_THRESHOLD,PROGRAM_COMPLETION,COMPETENCY,COHORT',
            'trigger_config' => 'sometimes|array',
            'requires_fees_cleared' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $template = MCertificateTemplate::create($data);

        // Create default page
        MCertificatePage::create([
            'template_id' => $template->id,
            'page_no' => 1,
            'width' => $data['page_size'] === 'A5' ? 420 : 595,
            'height' => $data['page_size'] === 'A5' ? 595 : 842,
        ]);

        return $this->success($template->load('pages'), 201);
    }

    /** GET /certificate-templates/:id — show template with pages and elements */
    public function showTemplate(string $id)
    {
        $template = MCertificateTemplate::with(['pages.elements'])->findOrFail($id);
        return $this->success($template);
    }

    /** PATCH /certificate-templates/:id — update template */
    public function updateTemplate(Request $request, string $id)
    {
        $template = MCertificateTemplate::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:200',
            'type' => 'sometimes|string|in:COMPLETION,AWARD,TRANSCRIPT,PARTICIPATION',
            'page_size' => 'sometimes|string|in:A4,A5,LETTER',
            'orientation' => 'sometimes|string|in:PORTRAIT,LANDSCAPE',
            'trigger_type' => 'sometimes|string',
            'trigger_config' => 'sometimes|array',
            'requires_fees_cleared' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);
        $template->update($data);
        return $this->success($template->fresh('pages.elements'));
    }

    /** DELETE /certificate-templates/:id */
    public function deleteTemplate(string $id)
    {
        MCertificateTemplate::findOrFail($id)->delete();
        return $this->success(null);
    }

    /** POST /certificate-templates/:id/duplicate — duplicate a template */
    public function duplicateTemplate(string $id)
    {
        $template = MCertificateTemplate::with('pages.elements')->findOrFail($id);

        $newTemplate = MCertificateTemplate::create([
            'name' => $template->name . ' (Copy)',
            'type' => $template->type,
            'page_size' => $template->page_size,
            'orientation' => $template->orientation,
            'trigger_type' => MCertificateTemplate::TRIGGER_MANUAL,
            'requires_fees_cleared' => $template->requires_fees_cleared,
            'is_active' => false,
        ]);

        foreach ($template->pages as $page) {
            $newPage = MCertificatePage::create([
                'template_id' => $newTemplate->id,
                'page_no' => $page->page_no,
                'width' => $page->width,
                'height' => $page->height,
                'background_image_url' => $page->background_image_url,
                'margin' => $page->margin,
            ]);
            foreach ($page->elements as $element) {
                MCertificateElement::create([
                    'page_id' => $newPage->id,
                    'element_type' => $element->element_type,
                    'data' => $element->data,
                    'pos_x' => $element->pos_x,
                    'pos_y' => $element->pos_y,
                    'width' => $element->width,
                    'font' => $element->font,
                    'font_size' => $element->font_size,
                    'colour' => $element->colour,
                    'align' => $element->align,
                    'refpoint' => $element->refpoint,
                    'z_index' => $element->z_index,
                ]);
            }
        }

        return $this->success($newTemplate->load('pages.elements'), 201);
    }

    // ===== Pages & Elements =====

    /** POST /certificate-templates/:id/pages — add a page */
    public function addPage(Request $request, string $templateId)
    {
        $data = $request->validate([
            'width' => 'sometimes|integer',
            'height' => 'sometimes|integer',
            'background_image_url' => 'nullable|string',
            'margin' => 'sometimes|integer',
        ]);

        $pageNo = MCertificatePage::where('template_id', $templateId)->max('page_no') + 1;
        $data['template_id'] = $templateId;
        $data['page_no'] = $pageNo;
        $data['width'] = $data['width'] ?? 595;
        $data['height'] = $data['height'] ?? 842;

        $page = MCertificatePage::create($data);
        return $this->success($page, 201);
    }

    /** POST /pages/:id/elements — add an element to a page */
    public function addElement(Request $request, string $pageId)
    {
        $data = $request->validate([
            'element_type' => 'required|string|in:static_text,recipient_name,program_name,course_name,teaching_subject,grade,date,verification_code,qr_code,signature_image,seal_logo,watermark,serial_number,custom_field',
            'data' => 'sometimes|array',
            'pos_x' => 'sometimes|integer',
            'pos_y' => 'sometimes|integer',
            'width' => 'nullable|integer',
            'font' => 'sometimes|string|max:100',
            'font_size' => 'sometimes|integer',
            'colour' => 'sometimes|string|max:20',
            'align' => 'sometimes|string|in:LEFT,CENTER,RIGHT',
            'refpoint' => 'sometimes|string|in:TOP_LEFT,TOP_CENTER,TOP_RIGHT',
            'z_index' => 'sometimes|integer',
        ]);

        $data['page_id'] = $pageId;
        $element = MCertificateElement::create($data);
        return $this->success($element, 201);
    }

    /** PATCH /elements/:id — update an element */
    public function updateElement(Request $request, string $id)
    {
        $element = MCertificateElement::findOrFail($id);
        $data = $request->validate([
            'element_type' => 'sometimes|string',
            'data' => 'sometimes|array',
            'pos_x' => 'sometimes|integer',
            'pos_y' => 'sometimes|integer',
            'width' => 'nullable|integer',
            'font' => 'sometimes|string|max:100',
            'font_size' => 'sometimes|integer',
            'colour' => 'sometimes|string|max:20',
            'align' => 'sometimes|string|in:LEFT,CENTER,RIGHT',
            'refpoint' => 'sometimes|string|in:TOP_LEFT,TOP_CENTER,TOP_RIGHT',
            'z_index' => 'sometimes|integer',
        ]);
        $element->update($data);
        return $this->success($element->fresh());
    }

    /** DELETE /elements/:id */
    public function deleteElement(string $id)
    {
        MCertificateElement::findOrFail($id)->delete();
        return $this->success(null);
    }

    // ===== Issuing (spec §7) =====

    /** POST /certificates/issue — issue a single certificate */
    public function issue(Request $request)
    {
        $data = $request->validate([
            'template_id' => 'required|uuid|exists:m_certificate_templates,id',
            'user_id' => 'required|uuid|exists:users,id',
            'course_id' => 'nullable|uuid|exists:courses,id',
            'program_id' => 'nullable|uuid|exists:programs,id',
            'grade' => 'nullable|string|max:50',
            'classification' => 'nullable|string|max:50',
        ]);

        $eligibility = $this->certService->checkEligibility(
            $data['user_id'], $data['template_id'], $data['course_id'] ?? null
        );

        if (!$eligibility['eligible']) {
            return $this->error('NOT_ELIGIBLE', $eligibility['reason'], 422);
        }

        $issue = $this->certService->issue(
            $data['template_id'],
            $data['user_id'],
            $request->user()->id,
            $data['course_id'] ?? null,
            $data['program_id'] ?? null,
            $data['grade'] ?? null,
            $data['classification'] ?? null
        );

        return $this->success($issue, 201);
    }

    /** POST /certificates/bulk-issue — bulk issue to cohort/program */
    public function bulkIssue(Request $request)
    {
        $data = $request->validate([
            'template_id' => 'required|uuid|exists:m_certificate_templates,id',
            'cohort_id' => 'nullable|uuid|exists:m_cohorts,id',
            'program_id' => 'nullable|uuid|exists:programs,id',
        ]);

        $result = $this->certService->bulkIssue(
            $data['template_id'],
            $request->user()->id,
            $data['cohort_id'] ?? null,
            $data['program_id'] ?? null
        );

        return $this->success($result);
    }

    /** PATCH /certificates/:id/revoke — revoke a certificate */
    public function revoke(Request $request, string $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $issue = $this->certService->revoke($id, $data['reason']);
        return $this->success($issue);
    }

    /** POST /certificates/:id/reissue — reissue a certificate */
    public function reissue(Request $request, string $id)
    {
        $issue = $this->certService->reissue($id, $request->user()->id);
        return $this->success($issue, 201);
    }

    // ===== Access (spec §7) =====

    /** GET /me/certificates — student's own certificates */
    public function myCertificates(Request $request)
    {
        $issues = MCertificateIssue::where('user_id', $request->user()->id)
            ->with(['template', 'course', 'issuer'])
            ->orderBy('issued_at', 'desc')
            ->get();
        return $this->success($issues);
    }

    /** GET /certificates — list all issued (admin/registrar) */
    public function indexIssues(Request $request)
    {
        $query = MCertificateIssue::with(['user', 'template', 'course']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($templateId = $request->get('template_id')) {
            $query->where('template_id', $templateId);
        }

        $issues = $query->orderBy('issued_at', 'desc')->paginate(20);
        return $this->paginated($issues);
    }

    /** GET /certificates/:id — show a specific issue */
    public function showIssue(string $id)
    {
        $issue = MCertificateIssue::with(['user', 'template', 'course', 'issuer'])->findOrFail($id);
        return $this->success($issue);
    }

    // ===== Transcript (spec §7) =====

    /** POST /transcripts/generate — generate transcript from gradebook */
    public function generateTranscript(Request $request)
    {
        $data = $request->validate([
            'student_id' => 'required|uuid|exists:users,id',
            'academic_year' => 'nullable|string|max:9',
            'semester' => 'nullable|string|max:2',
        ]);

        $transcript = $this->certService->generateTranscript(
            $data['student_id'],
            $data['academic_year'] ?? null,
            $data['semester'] ?? null
        );

        return $this->success($transcript);
    }

    // ===== Public Verification (spec §5) =====

    /** GET /verify/:code — public verification (no auth) */
    public function verify(string $code)
    {
        $result = $this->certService->verify($code);
        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /** GET /certificates/:id/view — view certificate as HTML (printable) */
    public function viewCertificate(string $id)
    {
        $issue = MCertificateIssue::with(['user', 'template.pages.elements', 'course', 'issuer'])->findOrFail($id);

        if ($issue->status === 'REVOKED') {
            return response('<h1>This certificate has been REVOKED</h1><p>Verification code: ' . $issue->code . '</p>', 200, ['Content-Type' => 'text/html']);
        }

        $html = $this->renderCertificateHtml($issue);
        return response($html, 200, ['Content-Type' => 'text/html']);
    }

    /** GET /certificates/:id/download — download certificate as HTML file (opens print dialog) */
    public function downloadPdf(string $id)
    {
        $issue = MCertificateIssue::with(['user', 'template.pages.elements', 'course', 'issuer'])->findOrFail($id);

        if ($issue->status === 'REVOKED') {
            return response('<h1>This certificate has been REVOKED</h1>', 200, ['Content-Type' => 'text/html']);
        }

        $html = $this->renderCertificateHtml($issue, true);
        $filename = 'certificate_' . $issue->code . '.html';
        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function renderCertificateHtml(MCertificateIssue $issue, bool $autoPrint = false): string
    {
        $template = $issue->template;
        $page = $template->pages->first();
        $elements = $page ? $page->elements : collect();
        $user = $issue->user;

        $width = $page->width ?? 842;
        $height = $page->height ?? 595;
        $orientation = $template->orientation === 'PORTRAIT' ? 'portrait' : 'landscape';

        $elementHtml = '';
        foreach ($elements as $el) {
            $text = $this->resolveElementText($el, $issue);
            if (!$text && $el->element_type !== 'qr_code') continue;

            $fontSize = ($el->font_size ?? 14) . 'px';
            $font = $el->font ?? 'Georgia';
            $colour = $el->colour ?? '#333333';
            $align = strtolower($el->align ?? 'CENTER');
            $x = $el->pos_x ?? 0;
            $y = $el->pos_y ?? 0;
            $z = $el->z_index ?? 1;

            if ($el->element_type === 'qr_code') {
                $qrUrl = config('app.url') . '/api/verify/' . $issue->code;
                $elementHtml .= '<div style="position:absolute;left:' . $x . 'px;top:' . $y . 'px;transform:translateX(-50%);z-index:' . $z . ';"><img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=' . urlencode($qrUrl) . '" width="80" height="80" alt="QR Code" /></div>';
            } else {
                $elementHtml .= '<div style="position:absolute;left:' . $x . 'px;top:' . $y . 'px;transform:translateX(-50%);font-family:' . $font . ',serif;font-size:' . $fontSize . ';color:' . $colour . ';text-align:' . $align . ';z-index:' . $z . ';white-space:nowrap;">' . htmlspecialchars($text) . '</div>';
            }
        }

        $printJs = $autoPrint ? '<script>window.onload=function(){window.print()}</script>' : '';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Certificate - ' . htmlspecialchars($user->full_name) . '</title>
        <style>
            @page { size: A4 ' . $orientation . '; margin: 0; }
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { background: #f0f0f0; display: flex; justify-content: center; align-items: center; min-height: 100vh; font-family: Georgia, serif; }
            .cert-page { position: relative; width: ' . $width . 'px; height: ' . $height . 'px; background: #fff; border: 2px solid #1a365d; box-shadow: 0 4px 20px rgba(0,0,0,0.15); overflow: hidden; }
            .cert-border-inner { position: absolute; top: 15px; left: 15px; right: 15px; bottom: 15px; border: 1px solid #c8861a; pointer-events: none; z-index: 0; }
            .cert-corner { position: absolute; width: 40px; height: 40px; border: 3px solid #1a365d; z-index: 0; }
            .cert-corner.tl { top: 8px; left: 8px; border-right: none; border-bottom: none; }
            .cert-corner.tr { top: 8px; right: 8px; border-left: none; border-bottom: none; }
            .cert-corner.bl { bottom: 8px; left: 8px; border-right: none; border-top: none; }
            .cert-corner.br { bottom: 8px; right: 8px; border-left: none; border-top: none; }
            @media print { body { background: #fff; } .cert-page { box-shadow: none; border: none; } }
        </style></head><body>
        <div class="cert-page">
            <div class="cert-border-inner"></div>
            <div class="cert-corner tl"></div>
            <div class="cert-corner tr"></div>
            <div class="cert-corner bl"></div>
            <div class="cert-corner br"></div>
            ' . $elementHtml . '
        </div>
        ' . $printJs . '
        </body></html>';
    }

    private function resolveElementText($el, MCertificateIssue $issue): string
    {
        switch ($el->element_type) {
            case 'static_text':
                return $el->data['text'] ?? '';
            case 'recipient_name':
                return $issue->user->full_name ?? '';
            case 'program_name':
                return $issue->program?->name ?? '';
            case 'course_name':
                return $issue->course?->name ?? '';
            case 'grade':
                return $issue->grade ?? '';
            case 'date':
                return $issue->issued_at ? $issue->issued_at->format('F j, Y') : now()->format('F j, Y');
            case 'verification_code':
                return $issue->code ?? '';
            case 'serial_number':
                return 'Serial No: ' . ($issue->serial_number ?? 'N/A');
            case 'qr_code':
                return 'QR';
            case 'custom_field':
                return $el->data['text'] ?? '';
            default:
                return '';
        }
    }

    // ===== Awards (spec §2.6) =====

    /** GET /awards — list awards */
    public function indexAwards()
    {
        return $this->success(MAward::where('is_active', true)->orderBy('name')->get());
    }

    /** POST /awards — create an award */
    public function createAward(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'criteria' => 'sometimes|array',
            'template_id' => 'nullable|uuid|exists:m_certificate_templates,id',
        ]);

        $award = MAward::create($data);
        return $this->success($award, 201);
    }
}
