<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MCertificateTemplate;
use App\Models\MCertificatePage;
use App\Models\MCertificateElement;

class CertificateTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $this->createCompletionTemplate();
        $this->createAwardTemplate();
        $this->createParticipationTemplate();
    }

    private function createCompletionTemplate(): void
    {
        $template = MCertificateTemplate::firstOrCreate(
            ['name' => 'Diploma Completion Certificate'],
            [
                'type' => 'COMPLETION',
                'page_size' => 'A4',
                'orientation' => 'LANDSCAPE',
                'trigger_type' => 'PROGRAM_COMPLETION',
                'requires_fees_cleared' => true,
                'is_active' => true,
            ]
        );

        $page = MCertificatePage::firstOrCreate(
            ['template_id' => $template->id, 'page_no' => 1],
            ['width' => 842, 'height' => 595, 'margin' => 40]
        );

        $elements = [
            ['element_type' => 'static_text', 'data' => ['text' => 'BUTIMBA TEACHERS COLLEGE'], 'pos_x' => 421, 'pos_y' => 60, 'font' => 'Georgia', 'font_size' => 28, 'colour' => '#1a365d', 'align' => 'CENTER', 'z_index' => 1],
            ['element_type' => 'static_text', 'data' => ['text' => 'Mwanza, Tanzania'], 'pos_x' => 421, 'pos_y' => 95, 'font' => 'Georgia', 'font_size' => 14, 'colour' => '#666666', 'align' => 'CENTER', 'z_index' => 1],
            ['element_type' => 'static_text', 'data' => ['text' => 'This is to certify that'], 'pos_x' => 421, 'pos_y' => 160, 'font' => 'Georgia', 'font_size' => 16, 'colour' => '#333333', 'align' => 'CENTER', 'z_index' => 1],
            ['element_type' => 'recipient_name', 'data' => [], 'pos_x' => 421, 'pos_y' => 210, 'font' => 'Georgia', 'font_size' => 36, 'colour' => '#1a365d', 'align' => 'CENTER', 'z_index' => 2],
            ['element_type' => 'static_text', 'data' => ['text' => 'has successfully completed the requirements for'], 'pos_x' => 421, 'pos_y' => 270, 'font' => 'Georgia', 'font_size' => 14, 'colour' => '#333333', 'align' => 'CENTER', 'z_index' => 1],
            ['element_type' => 'program_name', 'data' => [], 'pos_x' => 421, 'pos_y' => 310, 'font' => 'Georgia', 'font_size' => 22, 'colour' => '#1a365d', 'align' => 'CENTER', 'z_index' => 2],
            ['element_type' => 'static_text', 'data' => ['text' => 'with a grade of'], 'pos_x' => 421, 'pos_y' => 360, 'font' => 'Georgia', 'font_size' => 14, 'colour' => '#333333', 'align' => 'CENTER', 'z_index' => 1],
            ['element_type' => 'grade', 'data' => [], 'pos_x' => 421, 'pos_y' => 390, 'font' => 'Georgia', 'font_size' => 24, 'colour' => '#c8861a', 'align' => 'CENTER', 'z_index' => 2],
            ['element_type' => 'date', 'data' => [], 'pos_x' => 250, 'pos_y' => 490, 'font' => 'Georgia', 'font_size' => 14, 'colour' => '#333333', 'align' => 'CENTER', 'z_index' => 1],
            ['element_type' => 'static_text', 'data' => ['text' => 'Date Issued'], 'pos_x' => 250, 'pos_y' => 510, 'font' => 'Georgia', 'font_size' => 10, 'colour' => '#999999', 'align' => 'CENTER', 'z_index' => 1],
            ['element_type' => 'verification_code', 'data' => [], 'pos_x' => 600, 'pos_y' => 490, 'font' => 'Courier', 'font_size' => 12, 'colour' => '#666666', 'align' => 'CENTER', 'z_index' => 1],
            ['element_type' => 'static_text', 'data' => ['text' => 'Verification Code'], 'pos_x' => 600, 'pos_y' => 510, 'font' => 'Georgia', 'font_size' => 10, 'colour' => '#999999', 'align' => 'CENTER', 'z_index' => 1],
            ['element_type' => 'serial_number', 'data' => [], 'pos_x' => 421, 'pos_y' => 540, 'font' => 'Courier', 'font_size' => 10, 'colour' => '#999999', 'align' => 'CENTER', 'z_index' => 1],
        ];

        $this->syncElements($page->id, $elements);
    }

    private function createAwardTemplate(): void
    {
        $template = MCertificateTemplate::firstOrCreate(
            ['name' => 'Excellence Award Certificate'],
            [
                'type' => 'AWARD',
                'page_size' => 'A4',
                'orientation' => 'LANDSCAPE',
                'trigger_type' => 'MANUAL',
                'requires_fees_cleared' => false,
                'is_active' => true,
            ]
        );

        $page = MCertificatePage::firstOrCreate(
            ['template_id' => $template->id, 'page_no' => 1],
            ['width' => 842, 'height' => 595, 'margin' => 40]
        );

        $elements = [
            ['element_type' => 'static_text', 'data' => ['text' => 'BUTIMBA TEACHERS COLLEGE'], 'pos_x' => 421, 'pos_y' => 60, 'font' => 'Georgia', 'font_size' => 26, 'colour' => '#1a365d', 'align' => 'CENTER', 'z_index' => 1],
            ['element_type' => 'static_text', 'data' => ['text' => 'CERTIFICATE OF EXCELLENCE'], 'pos_x' => 421, 'pos_y' => 110, 'font' => 'Georgia', 'font_size' => 32, 'colour' => '#c8861a', 'align' => 'CENTER', 'z_index' => 2],
            ['element_type' => 'static_text', 'data' => ['text' => 'This award is proudly presented to'], 'pos_x' => 421, 'pos_y' => 180, 'font' => 'Georgia', 'font_size' => 16, 'colour' => '#333333', 'align' => 'CENTER', 'z_index' => 1],
            ['element_type' => 'recipient_name', 'data' => [], 'pos_x' => 421, 'pos_y' => 230, 'font' => 'Georgia', 'font_size' => 36, 'colour' => '#1a365d', 'align' => 'CENTER', 'z_index' => 2],
            ['element_type' => 'static_text', 'data' => ['text' => 'in recognition of outstanding academic achievement'], 'pos_x' => 421, 'pos_y' => 300, 'font' => 'Georgia', 'font_size' => 14, 'colour' => '#333333', 'align' => 'CENTER', 'z_index' => 1],
            ['element_type' => 'static_text', 'data' => ['text' => 'and exceptional dedication to the teaching profession'], 'pos_x' => 421, 'pos_y' => 325, 'font' => 'Georgia', 'font_size' => 14, 'colour' => '#333333', 'align' => 'CENTER', 'z_index' => 1],
            ['element_type' => 'date', 'data' => [], 'pos_x' => 250, 'pos_y' => 460, 'font' => 'Georgia', 'font_size' => 14, 'colour' => '#333333', 'align' => 'CENTER', 'z_index' => 1],
            ['element_type' => 'static_text', 'data' => ['text' => 'Date Awarded'], 'pos_x' => 250, 'pos_y' => 480, 'font' => 'Georgia', 'font_size' => 10, 'colour' => '#999999', 'align' => 'CENTER', 'z_index' => 1],
            ['element_type' => 'verification_code', 'data' => [], 'pos_x' => 600, 'pos_y' => 460, 'font' => 'Courier', 'font_size' => 12, 'colour' => '#666666', 'align' => 'CENTER', 'z_index' => 1],
            ['element_type' => 'static_text', 'data' => ['text' => 'Verification Code'], 'pos_x' => 600, 'pos_y' => 480, 'font' => 'Georgia', 'font_size' => 10, 'colour' => '#999999', 'align' => 'CENTER', 'z_index' => 1],
            ['element_type' => 'serial_number', 'data' => [], 'pos_x' => 421, 'pos_y' => 530, 'font' => 'Courier', 'font_size' => 10, 'colour' => '#999999', 'align' => 'CENTER', 'z_index' => 1],
        ];

        $this->syncElements($page->id, $elements);
    }

    private function createParticipationTemplate(): void
    {
        $template = MCertificateTemplate::firstOrCreate(
            ['name' => 'Course Participation Certificate'],
            [
                'type' => 'PARTICIPATION',
                'page_size' => 'A4',
                'orientation' => 'LANDSCAPE',
                'trigger_type' => 'COURSE_COMPLETION',
                'requires_fees_cleared' => false,
                'is_active' => true,
            ]
        );

        $page = MCertificatePage::firstOrCreate(
            ['template_id' => $template->id, 'page_no' => 1],
            ['width' => 842, 'height' => 595, 'margin' => 40]
        );

        $elements = [
            ['element_type' => 'static_text', 'data' => ['text' => 'BUTIMBA TEACHERS COLLEGE'], 'pos_x' => 421, 'pos_y' => 60, 'font' => 'Georgia', 'font_size' => 26, 'colour' => '#1a365d', 'align' => 'CENTER', 'z_index' => 1],
            ['element_type' => 'static_text', 'data' => ['text' => 'CERTIFICATE OF PARTICIPATION'], 'pos_x' => 421, 'pos_y' => 110, 'font' => 'Georgia', 'font_size' => 28, 'colour' => '#2d6a4f', 'align' => 'CENTER', 'z_index' => 2],
            ['element_type' => 'static_text', 'data' => ['text' => 'This is to certify that'], 'pos_x' => 421, 'pos_y' => 170, 'font' => 'Georgia', 'font_size' => 16, 'colour' => '#333333', 'align' => 'CENTER', 'z_index' => 1],
            ['element_type' => 'recipient_name', 'data' => [], 'pos_x' => 421, 'pos_y' => 220, 'font' => 'Georgia', 'font_size' => 34, 'colour' => '#1a365d', 'align' => 'CENTER', 'z_index' => 2],
            ['element_type' => 'static_text', 'data' => ['text' => 'has actively participated in'], 'pos_x' => 421, 'pos_y' => 290, 'font' => 'Georgia', 'font_size' => 14, 'colour' => '#333333', 'align' => 'CENTER', 'z_index' => 1],
            ['element_type' => 'course_name', 'data' => [], 'pos_x' => 421, 'pos_y' => 330, 'font' => 'Georgia', 'font_size' => 22, 'colour' => '#2d6a4f', 'align' => 'CENTER', 'z_index' => 2],
            ['element_type' => 'date', 'data' => [], 'pos_x' => 250, 'pos_y' => 470, 'font' => 'Georgia', 'font_size' => 14, 'colour' => '#333333', 'align' => 'CENTER', 'z_index' => 1],
            ['element_type' => 'static_text', 'data' => ['text' => 'Date Issued'], 'pos_x' => 250, 'pos_y' => 490, 'font' => 'Georgia', 'font_size' => 10, 'colour' => '#999999', 'align' => 'CENTER', 'z_index' => 1],
            ['element_type' => 'verification_code', 'data' => [], 'pos_x' => 600, 'pos_y' => 470, 'font' => 'Courier', 'font_size' => 12, 'colour' => '#666666', 'align' => 'CENTER', 'z_index' => 1],
            ['element_type' => 'static_text', 'data' => ['text' => 'Verification Code'], 'pos_x' => 600, 'pos_y' => 490, 'font' => 'Georgia', 'font_size' => 10, 'colour' => '#999999', 'align' => 'CENTER', 'z_index' => 1],
            ['element_type' => 'serial_number', 'data' => [], 'pos_x' => 421, 'pos_y' => 530, 'font' => 'Courier', 'font_size' => 10, 'colour' => '#999999', 'align' => 'CENTER', 'z_index' => 1],
        ];

        $this->syncElements($page->id, $elements);
    }

    private function syncElements(string $pageId, array $elements): void
    {
        MCertificateElement::where('page_id', $pageId)->delete();
        foreach ($elements as $el) {
            MCertificateElement::create(array_merge($el, [
                'page_id' => $pageId,
                'width' => $el['width'] ?? null,
                'refpoint' => $el['refpoint'] ?? 'TOP_CENTER',
            ]));
        }
    }
}
