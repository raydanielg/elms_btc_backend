<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MModule;
use App\Models\MCourseCategory;
use App\Models\MCourseSection;

class CourseStructureSeeder extends Seeder
{
    public function run(): void
    {
        // Module registry (spec §5.1)
        $modules = [
            'assign', 'quiz', 'forum', 'resource', 'page', 'url',
            'folder', 'book', 'label', 'lesson', 'choice', 'feedback',
            'survey', 'chat', 'data', 'h5pactivity', 'bigbluebutton',
        ];

        foreach ($modules as $name) {
            MModule::firstOrCreate(['name' => $name], ['active' => true]);
        }

        // Default top-level category for Diploma programs
        $diplomaCat = MCourseCategory::firstOrCreate(
            ['idnumber' => 'DIPLOMA'],
            [
                'name' => 'Diploma Programs',
                'description' => 'All diploma-level programs at Butimba Teachers College',
                'visible' => true,
                'sortorder' => 1,
            ]
        );
        $diplomaCat->path = '/' . $diplomaCat->id;
        $diplomaCat->depth = 1;
        $diplomaCat->save();

        // Create a default section (Section 0 = General) for existing courses
        $courses = \App\Models\Course::all();
        foreach ($courses as $course) {
            MCourseSection::firstOrCreate(
                ['course_id' => $course->id, 'section' => 0],
                ['name' => 'General', 'visible' => true]
            );
        }
    }
}
